<?php

namespace App\Http\Controllers;

use App\Models\BillingContract;
use App\Models\ComputeInstance;
use App\Models\ApplicationSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ContractUnpaidInvoicePdfController
{
    public function __invoke(Request $request, ComputeInstance $computeInstance)
    {
        Gate::authorize('view', $computeInstance);
        $validated = $request->validate([
            'contracts' => ['required', 'array', 'min:1'],
            'contracts.*' => ['required', 'integer', 'distinct'],
            'period_month' => ['nullable', 'date_format:Y-m'],
        ]);
        $contractIds = array_map('intval', $validated['contracts']);
        $periodStart = filled($validated['period_month'] ?? null)
            ? CarbonImmutable::parse($validated['period_month'].'-01')
            : null;
        $contracts = BillingContract::query()
            ->whereIn('id', $contractIds)
            ->where(function ($query) use ($computeInstance): void {
                $query->where('compute_instance_id', $computeInstance->id)
                    ->orWhereIn('service_id', $computeInstance->deployments()->select('service_id'));
            })
            ->with('party')
            ->orderBy('number')
            ->get();

        abort_unless($contracts->count() === count($contractIds), 404);

        foreach ($contracts as $contract) {
            Gate::authorize('view', $contract);

            $unpaidInvoices = $contract->invoices()
                ->where('status', 'issued')
                ->when($periodStart, fn ($query) => $query->whereBetween('period_start', [$periodStart->toDateString(), $periodStart->endOfMonth()->toDateString()]))
                ->with(['party', 'items.target', 'payments'])
                ->orderBy('issue_date')
                ->orderBy('id')
                ->get()
                ->filter(fn ($invoice): bool => bccomp((string) $invoice->total, '0.00', 2) === 0 || ($invoice->status === 'issued' && bccomp($invoice->outstanding, '0.00', 2) > 0))
                ->values();

            $contract->setRelation('unpaidInvoices', $unpaidInvoices);
        }

        $invoices = $contracts->flatMap(fn (BillingContract $contract) => $contract->unpaidInvoices);
        $invoiceSettings = ApplicationSetting::query()
            ->whereIn('key', ['organization_name', 'contact_email', 'address', 'payment_information', 'invoice_footer'])
            ->pluck('value', 'key')
            ->all();

        return Pdf::loadView('pdf.contract-unpaid-invoices', [
            'invoices' => $invoices,
            'invoiceSettings' => $invoiceSettings,
            'currencyTotals' => $invoices->groupBy(fn ($invoice): string => $invoice->currency ?? $invoice->source_currency ?? 'IDR')
                ->map(fn ($currencyInvoices) => [
                    'billed' => $currencyInvoices->reduce(fn (string $total, $invoice): string => bcadd($total, (string) $invoice->total, 2), '0.00'),
                    'paid' => $currencyInvoices->reduce(fn (string $total, $invoice): string => bcadd($total, (string) $invoice->paid_total, 2), '0.00'),
                    'outstanding' => $currencyInvoices->reduce(fn (string $total, $invoice): string => bcadd($total, (string) $invoice->outstanding, 2), '0.00'),
                ]),
        ])->setPaper('a4')->stream('compute-instance-'.$computeInstance->id.'-unpaid-contract-invoices.pdf');
    }
}
