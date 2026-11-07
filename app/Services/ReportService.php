<?php

namespace App\Services;

use App\Models\ComputeInstance;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PhysicalServer;
use App\Models\ResourceAllocation;
use App\Models\Service;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public int $totalRows = 0;

    public function sum(iterable $values): string
    {
        $total = '0.00';
        foreach ($values as $value) {
            $total = bcadd($total, (string) $value, 2);
        }

        return $total;
    }

    private function period($query, string $column, ?string $from, ?string $to): mixed
    {
        return $query->when($from, fn ($q) => $q->whereDate($column, '>=', $from))
            ->when($to, fn ($q) => $q->whereDate($column, '<=', $to));
    }

    private function amount(mixed $value): string
    {
        return bcadd((string) ($value ?? '0'), '0', 2);
    }

    public function financialSummary(?string $from = null, ?string $to = null): array
    {
        $invoices = $this->period(Invoice::where('status', 'issued'), 'issue_date', $from, $to);
        $payments = $this->period(Payment::whereNull('reversed_at')->whereHas('invoice', fn ($q) => $q->where('status', 'issued')), 'paid_at', $from, $to);
        $cashIn = $this->amount((clone $payments)->whereHas('invoice', fn ($q) => $q->where('type', 'receivable'))->sum('amount'));
        $cashOut = $this->amount((clone $payments)->whereHas('invoice', fn ($q) => $q->where('type', 'payable'))->sum('amount'));
        $income = $this->amount((clone $invoices)->where('type', 'receivable')->sum('total'));
        $expense = $this->amount((clone $invoices)->where('type', 'payable')->sum('total'));
        $settled = function (string $type) use ($from, $to): string {
            return $this->amount(Payment::whereNull('reversed_at')->whereHas('invoice', function ($query) use ($type, $from, $to) {
                $this->period($query->where('status', 'issued')->where('type', $type), 'issue_date', $from, $to);
            })->sum('amount'));
        };
        $contractValue = DB::table('contract_items')->join('billing_contracts', 'billing_contracts.id', '=', 'contract_items.billing_contract_id')
            ->where('billing_contracts.status', 'active')->whereDate('effective_from', '<=', today())->selectRaw('SUM(quantity * unit_price) as amount')->value('amount');

        return [
            'Active contract face value (mixed cycles)' => $this->amount($contractValue),
            'Total invoiced income' => $income,
            'Total billed expense' => $expense,
            'Income received' => $cashIn,
            'Expense paid' => $cashOut,
            'Accounts receivable' => bcsub($income, $settled('receivable'), 2),
            'Accounts payable' => bcsub($expense, $settled('payable'), 2),
            'Net cashflow' => bcsub($cashIn, $cashOut, 2),
        ];
    }

    public function inventorySummary(): array
    {
        $direct = ResourceAllocation::whereNull('effective_to')->whereHas('instance', fn ($q) => $q->whereNotNull('physical_server_id')->whereNull('parent_id')->whereIn('type', ['vm', 'lxc']));

        return [
            'Physical servers' => PhysicalServer::count(), 'Compute instances' => ComputeInstance::count(),
            'Running services' => Service::where('status', 'running')->count(), 'Archived services' => Service::where('status', 'archived')->count(),
            'Physical CPU cores' => PhysicalServer::sum('cpu_cores'), 'Physical RAM (MB)' => PhysicalServer::sum('ram_mb'),
            'Physical storage (GB)' => PhysicalServer::sum('storage_gb'), 'Direct allocated vCPU' => (clone $direct)->sum('vcpu'),
            'Direct allocated RAM (MB)' => (clone $direct)->sum('ram_mb'), 'Provisioned storage (GB)' => (clone $direct)->sum('storage_gb'),
        ];
    }

    private function fetch(mixed $query, ?int $page, int $perPage): Collection
    {
        if ($page === null) {
            return $query->get();
        }
        $paginator = $query->paginate($perPage, ['*'], 'page', max(1, $page));
        $this->totalRows = $paginator->total();

        return $paginator->getCollection();
    }

    public function rows(string $report, ?string $from = null, ?string $to = null, int $days = 30, ?int $page = null, int $perPage = 25): array
    {
        if ($report === 'inventory') {
            return $this->fetch(Service::with(['client', 'deployments.computeInstance', 'domains'])->orderBy('name')->orderBy('id'), $page, $perPage)->map(fn ($s) => [
                'Code' => $s->code, 'Service' => $s->name, 'Type' => $s->type, 'Status' => $s->status,
                'Client' => $s->client?->name, 'Domains' => $s->domains->pluck('fqdn')->implode(', '),
                'Active deployments' => $s->deployments->whereNull('removed_at')->map(fn ($d) => $d->computeInstance?->name)->implode(', '),
            ])->all();
        }
        if ($report === 'resources') {
            return $this->fetch(ComputeInstance::with(['physicalServer', 'parent', 'provider', 'currentAllocation'])->orderBy('name')->orderBy('id'), $page, $perPage)->map(fn ($i) => [
                'Instance' => $i->name, 'Type' => $i->type, 'Physical server' => $i->physicalServer?->name, 'Parent' => $i->parent?->name,
                'Provider' => $i->provider?->name, 'vCPU' => $i->currentAllocation?->vcpu, 'RAM MB' => $i->currentAllocation?->ram_mb,
                'Provisioned GB' => $i->currentAllocation?->storage_gb,
                'Counted directly on host' => $i->physical_server_id && ! $i->parent_id && in_array($i->type, ['vm', 'lxc']) ? 'yes' : 'no',
            ])->all();
        }
        if ($report === 'renewals') {
            $cutoff = today()->addDays(max(0, $days));
            $contracts = DB::table('billing_contracts')->join('parties', 'parties.id', '=', 'billing_contracts.party_id')
                ->where('billing_contracts.status', 'active')->whereNotNull('next_billing_date')
                ->selectRaw("'Contract' as kind, billing_contracts.number as reference, parties.name as party, next_billing_date as renewal_date")
                ->selectSub(DB::table('contract_items')->whereColumn('billing_contract_id', 'billing_contracts.id')->whereColumn('effective_from', '<=', 'billing_contracts.next_billing_date')->selectRaw('SUM(quantity * unit_price)'), 'estimated');
            $domains = DB::table('domains')->whereNotNull('expires_at')->selectRaw("'Domain' as kind, fqdn as reference, registrar as party, expires_at as renewal_date, NULL as estimated");
            $query = DB::query()->fromSub($contracts->unionAll($domains), 'renewals')
                ->when($days === 0, fn ($q) => $q->whereDate('renewal_date', '<', today()), fn ($q) => $q->whereDate('renewal_date', '<=', $cutoff))
                ->orderBy('renewal_date')->orderBy('reference');

            return $this->fetch($query, $page, $perPage)->map(fn ($row) => [
                'Kind' => $row->kind, 'Reference' => $row->reference, 'Party' => $row->party, 'Renewal date' => $row->renewal_date,
                'Estimated IDR' => $row->estimated === null ? null : $this->amount($row->estimated),
                'Days remaining' => (int) today()->diffInDays(Carbon::parse($row->renewal_date), false),
                'Status' => Carbon::parse($row->renewal_date)->lt(today()) ? 'expired' : 'upcoming',
            ])->all();
        }
        if ($report === 'cashflow') {
            $query = $this->period(Payment::with('invoice.party')->whereNull('reversed_at')->whereHas('invoice', fn ($q) => $q->where('status', 'issued')), 'paid_at', $from, $to)->orderBy('paid_at')->orderBy('id');

            return $this->fetch($query, $page, $perPage)->map(fn ($p) => [
                'Payment date' => $p->paid_at->toDateString(), 'Invoice' => $p->invoice->number, 'Party' => $p->invoice->party?->name,
                'Cash in' => $p->invoice->type === 'receivable' ? $p->amount : '0.00', 'Cash out' => $p->invoice->type === 'payable' ? $p->amount : '0.00',
                'Method' => $p->method, 'Reference' => $p->reference,
            ])->all();
        }
        if (in_array($report, ['income_by_service', 'expense_by_provider'])) {
            $direction = $report === 'income_by_service' ? 'receivable' : 'payable';
            $query = DB::table('invoice_items')->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
                ->where('invoices.status', 'issued')->where('invoices.type', $direction);
            $this->period($query, 'invoices.issue_date', $from, $to);
            if ($direction === 'receivable') {
                $query->leftJoin('services', function ($join) {
                    $join->on('services.id', '=', 'invoice_items.target_id')->where('invoice_items.target_type', Service::class);
                })
                    ->selectRaw('invoice_items.target_type, invoice_items.target_id, services.code, services.name, SUM(invoice_items.total) as amount')
                    ->groupBy('invoice_items.target_type', 'invoice_items.target_id', 'services.code', 'services.name')->orderBy('services.name');
            } else {
                $query->leftJoin('parties', 'parties.id', '=', 'invoices.party_id')->selectRaw('parties.id as party_id, parties.name, SUM(invoice_items.total) as amount')->groupBy('parties.id', 'parties.name')->orderBy('parties.name');
            }

            return $this->fetch($query, $page, $perPage)->map(fn ($row) => [
                'Group' => $direction === 'payable' ? 'Vendor #'.$row->party_id.' · '.$row->name : ($row->target_type === Service::class ? 'Service #'.$row->target_id.' · '.$row->name : 'Other / general'),
                'Invoiced IDR' => $this->amount($row->amount),
            ])->all();
        }
        $query = $this->period(Invoice::with(['party', 'payments'])->where('status', 'issued'), 'issue_date', $from, $to);
        if ($report === 'income') {
            $query->where('type', 'receivable');
        } elseif ($report === 'expenses') {
            $query->where('type', 'payable');
        } elseif (in_array($report, ['aging', 'unpaid', 'overdue'])) {
            $query->whereRaw('total > (select COALESCE(SUM(amount),0) from payments where payments.invoice_id=invoices.id and payments.reversed_at is null)');
            if ($report === 'overdue') {
                $query->whereDate('due_date', '<', today());
            }
        }

        return $this->fetch($query->orderBy('due_date')->orderBy('id'), $page, $perPage)->map(fn ($i) => [
            'Invoice' => $i->number, 'Type' => $i->type, 'Party' => $i->party?->name, 'Issued' => $i->issue_date->toDateString(),
            'Due' => $i->due_date->toDateString(), 'Total IDR' => $i->total, 'Paid IDR' => $i->paid_total,
            'Outstanding IDR' => $i->outstanding, 'Payment status' => $i->payment_status, 'Overdue' => $i->is_overdue ? 'yes' : 'no',
            'Days overdue' => max(0, (int) $i->due_date->diffInDays(today(), false)),
        ])->values()->all();
    }
}
