<?php

namespace App\Actions\Billing;

use App\Models\BillingContract;
use App\Models\ComputeInstance;
use App\Models\Invoice;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GenerateScheduledInvoices
{
    public function run(int $maxBackfillPerContract = 100): array
    {
        $created = 0;
        $backlogs = [];

        BillingContract::query()->where('status', 'active')->where('auto_generate', true)->whereNotNull('first_billing_date')->orderBy('id')->chunkById(100, function ($contracts) use (&$created, &$backlogs, $maxBackfillPerContract): void {
            foreach ($contracts as $candidate) {
                DB::transaction(function () use ($candidate, &$created, &$backlogs, $maxBackfillPerContract): void {
                    $contract = BillingContract::query()->lockForUpdate()->find($candidate->id);
                    if (! $contract || $contract->status !== 'active' || ! $contract->auto_generate || ! $contract->amount) {
                        return;
                    }
                    $today = CarbonImmutable::today(config('app.timezone'));
                    $count = 0;
                    $activePeriods = Invoice::query()
                        ->where('billing_contract_id', $contract->id)
                        ->where('status', '!=', 'void')
                        ->whereNotNull('period_start')
                        ->pluck('period_start')
                        ->mapWithKeys(fn ($date): array => [CarbonImmutable::parse($date)->toDateString() => true])
                        ->all();
                    $hasHistoricalBacklog = false;
                    for ($sequence = 0; $sequence < (int) $contract->billing_sequence; $sequence++) {
                        if ($contract->billing_cycle === 'one_time' && $sequence > 0) {
                            break;
                        }
                        $start = $this->periodStart($contract, $sequence);
                        if (($contract->ends_at && $start->gt(CarbonImmutable::parse($contract->ends_at))) || $start->subDays((int) $contract->generate_days_before)->gt($today)) {
                            break;
                        }
                        $periodStart = $start->toDateString();
                        if (isset($activePeriods[$periodStart])) {
                            continue;
                        }
                        if ($count >= $maxBackfillPerContract) {
                            $hasHistoricalBacklog = true;
                            break;
                        }
                        $this->createInvoice($contract, $start, $this->periodEnd($contract, $start, $sequence), $today);
                        $activePeriods[$periodStart] = true;
                        $created++;
                        $count++;
                    }

                    while ($count < $maxBackfillPerContract) {
                        $sequence = (int) $contract->billing_sequence;
                        $start = $this->periodStart($contract, $sequence);
                        if ($contract->ends_at && $start->gt(CarbonImmutable::parse($contract->ends_at))) {
                            break;
                        }
                        if ($start->subDays((int) $contract->generate_days_before)->gt($today)) {
                            break;
                        }

                        $periodStart = $start->toDateString();
                        if (! isset($activePeriods[$periodStart])) {
                            $this->createInvoice($contract, $start, $this->periodEnd($contract, $start, $sequence), $today);
                            $activePeriods[$periodStart] = true;
                            $created++;
                        }

                        $contract->billing_sequence = $sequence + 1;
                        $contract->next_billing_date = $this->periodStart($contract, $sequence + 1)->toDateString();
                        $contract->save();
                        $count++;
                        if ($contract->billing_cycle === 'one_time') {
                            break;
                        }
                    }
                    if ($hasHistoricalBacklog || ($count >= $maxBackfillPerContract && $this->periodStart($contract, (int) $contract->billing_sequence)->subDays((int) $contract->generate_days_before)->lte($today))) {
                        $backlogs[] = $contract->number;
                    }
                });
            }
        });

        return ['created' => $created, 'backlogs' => $backlogs];
    }

    private function createInvoice(BillingContract $contract, CarbonImmutable $start, CarbonImmutable $end, CarbonImmutable $today): void
    {
        $invoice = Invoice::create([
            'number' => 'INV-'.now()->format('YmdHis').'-'.strtoupper(Str::random(5)),
            'type' => $contract->direction === 'income' ? 'receivable' : 'payable',
            'party_id' => $contract->party_id,
            'billing_contract_id' => $contract->id,
            'issue_date' => $today->toDateString(),
            'due_date' => $start->addDays((int) $contract->payment_terms_days)->toDateString(),
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'status' => 'draft',
            'currency' => $contract->currency,
            'source_currency' => $contract->currency,
            'source_amount' => $contract->currency === 'IDR' ? null : $contract->amount,
            'subtotal' => $contract->amount,
            'total' => $contract->amount,
            'discount' => '0.00',
            'tax' => '0.00',
            'notes' => 'Invoice diterbitkan otomatis dari kontrak.',
        ]);
        $invoice->items()->create([
            'description' => $contract->name,
            'quantity' => '1.0000',
            'unit_price' => $contract->amount,
            'discount' => '0.00',
            'tax' => '0.00',
            'total' => $contract->amount,
            'target_type' => $contract->service_id ? Service::class : ($contract->compute_instance_id ? ComputeInstance::class : null),
            'target_id' => $contract->service_id ?? $contract->compute_instance_id,
        ]);
        $invoice->issueAutomatically();
    }

    private function periodEnd(BillingContract $contract, CarbonImmutable $start, int $sequence): CarbonImmutable
    {
        $following = $contract->billing_cycle === 'one_time' ? $start : $this->periodStart($contract, $sequence + 1);
        $end = $contract->billing_cycle === 'one_time' ? $start : $following->subDay();

        return $contract->ends_at && $end->gt(CarbonImmutable::parse($contract->ends_at))
            ? CarbonImmutable::parse($contract->ends_at)
            : $end;
    }

    private function periodStart(BillingContract $contract, int $sequence): CarbonImmutable
    {
        $anchor = CarbonImmutable::parse($contract->first_billing_date)->startOfDay();

        return match ($contract->billing_cycle) {
            'daily' => $anchor->addDays($sequence),
            'weekly' => $anchor->addWeeks($sequence),
            'monthly' => $anchor->addMonthsNoOverflow($sequence),
            'quarterly' => $anchor->addMonthsNoOverflow(3 * $sequence),
            'yearly' => $anchor->addYearsNoOverflow($sequence),
            default => $anchor,
        };
    }
}
