<?php

namespace App\Services;

use App\Models\ComputeInstance;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PhysicalServer;
use App\Models\Service;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function data(string $period, string $search, string $status, int $page): array
    {
        $search = mb_substr($search, 0, 120);
        $status = in_array($status, ['all', 'running', 'stopped', 'draft', 'archived'], true) ? $status : 'all';
        $user = auth()->user();
        $canViewInventory = $user?->canView('inventory') ?? false;
        $canViewBilling = $user?->canView('billing') ?? false;
        $now = now();
        $from = $period === 'last_12_months' ? $now->copy()->startOfMonth()->subMonths(11) : $now->copy()->startOfYear();
        $to = $now->copy()->endOfDay();
        $reports = app(ReportService::class);

        $servicesTotal = $canViewInventory ? Service::count() : 0;
        $servicesRunning = $canViewInventory ? Service::where('status', 'running')->count() : 0;
        $servicesArchived = $canViewInventory ? Service::where('status', 'archived')->count() : 0;

        $unpaidQuery = Invoice::query()
            ->where('status', 'issued')
            ->whereRaw('total > (select COALESCE(SUM(amount), 0) from payments where payments.invoice_id = invoices.id and payments.reversed_at is null)');
        $unpaidCount = $canViewBilling ? (clone $unpaidQuery)->count() : 0;
        $unpaidAmount = $canViewBilling
            ? (string) (clone $unpaidQuery)->selectRaw('COALESCE(SUM(total - (select COALESCE(SUM(amount), 0) from payments where payments.invoice_id = invoices.id and payments.reversed_at is null)), 0) as outstanding')->value('outstanding')
            : '0';

        $financial = $canViewBilling ? $reports->financialSummary($from->toDateString(), $to->toDateString()) : [];
        $activeContractCount = $canViewBilling ? DB::table('billing_contracts')->where('status', 'active')->count() : 0;
        $monthlyCashflow = $canViewBilling ? $this->monthlyCashflow($from, $to) : [];
        $renewalRows = $canViewBilling ? $reports->rows('renewals', days: 365, page: 1, perPage: 4) : [];
        $renewalsWithin30Days = 0;
        if ($canViewBilling) {
            $reports->rows('renewals', days: 30, page: 1, perPage: 1);
            $renewalsWithin30Days = $reports->totalRows;
        }

        $physicalServer = $canViewInventory
            ? PhysicalServer::query()->with('provider')->withCount('directInstances')->where('status', 'active')->orderBy('name')->first()
            : null;
        $instanceCounts = $canViewInventory
            ? ComputeInstance::query()->where('status', 'active')->select('type', DB::raw('COUNT(*) as total'))->groupBy('type')->pluck('total', 'type')
            : collect();

        $services = $canViewInventory
            ? Service::query()
                ->with(['client', 'domains', 'deployments' => fn ($query) => $query->whereNull('removed_at')->with('instance.provider', 'instance.currentAllocation', 'instance.physicalServer.provider')])
                ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('name', 'like', '%'.$search.'%')->orWhere('code', 'like', '%'.$search.'%')->orWhere('slug', 'like', '%'.$search.'%')))
                ->when($status !== 'all', fn ($query) => $query->where('status', $status))
                ->orderBy('name')->paginate(6, page: max(1, $page))
            : new LengthAwarePaginator([], 0, 6, 1);

        return [
            'period' => $period,
            'today' => $now->locale('id')->translatedFormat('l, j F Y'),
            'canViewInventory' => $canViewInventory,
            'canViewBilling' => $canViewBilling,
            'canManageInventory' => $user?->canManage('inventory') ?? false,
            'canManageBilling' => $user?->canManage('billing') ?? false,
            'servicesTotal' => $servicesTotal,
            'servicesRunning' => $servicesRunning,
            'servicesArchived' => $servicesArchived,
            'physicalServerCount' => $canViewInventory ? PhysicalServer::count() : 0,
            'computeInstanceCount' => $canViewInventory ? ComputeInstance::count() : 0,
            'unpaidCount' => $unpaidCount,
            'unpaidAmount' => $unpaidAmount,
            'financial' => $financial,
            'activeContractCount' => $activeContractCount,
            'monthlyCashflow' => $monthlyCashflow,
            'renewalRows' => $renewalRows,
            'renewalsWithin30Days' => $renewalsWithin30Days,
            'physicalServer' => $physicalServer,
            'instanceCounts' => $instanceCounts,
            'services' => $services,
        ];
    }

    /** @return array<int, array{label: string, income: float, expense: float}> */
    private function monthlyCashflow(Carbon $from, Carbon $to): array
    {
        $rows = Payment::query()
            ->join('invoices', 'invoices.id', '=', 'payments.invoice_id')
            ->whereNull('payments.reversed_at')
            ->where('invoices.status', 'issued')
            ->whereBetween('payments.paid_at', [$from->toDateString(), $to->toDateString()])
            ->selectRaw("SUBSTR(payments.paid_at, 1, 7) as month_key, SUM(CASE WHEN invoices.type = 'receivable' THEN payments.amount ELSE 0 END) as income, SUM(CASE WHEN invoices.type = 'payable' THEN payments.amount ELSE 0 END) as expense")
            ->groupByRaw('SUBSTR(payments.paid_at, 1, 7)')
            ->get()
            ->keyBy('month_key');

        $months = [];
        $cursor = $from->copy()->startOfMonth();
        $lastMonth = $to->copy()->startOfMonth();

        while ($cursor->lte($lastMonth)) {
            $key = $cursor->format('Y-m');
            $row = $rows->get($key);
            $months[] = [
                'label' => $cursor->locale('id')->translatedFormat('M'),
                'income' => (float) ($row?->income ?? 0),
                'expense' => (float) ($row?->expense ?? 0),
            ];
            $cursor->addMonth();
        }

        return $months;
    }
}
