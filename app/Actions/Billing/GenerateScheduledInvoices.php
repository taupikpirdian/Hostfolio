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
                    while ($count < $maxBackfillPerContract) {
                        $sequence = (int) $contract->billing_sequence;
                        $start = $this->periodStart($contract, $sequence);
                        if ($contract->ends_at && $start->gt(CarbonImmutable::parse($contract->ends_at))) {
                            break;
                        }
                        if ($start->subDays((int) $contract->generate_days_before)->gt($today)) {
                            break;
                        }

                        $following = $contract->billing_cycle === 'one_time' ? $start : $this->periodStart($contract, $sequence + 1);
                        $end = $contract->billing_cycle === 'one_time' ? $start : $following->subDay();
                        if ($contract->ends_at && $end->gt(CarbonImmutable::parse($contract->ends_at))) {
                            $end = CarbonImmutable::parse($contract->ends_at);
                        }
                        $periodStart = $start->toDateString();
                        $invoice = Invoice::query()->where('billing_contract_id', $contract->id)->whereDate('period_start', $periodStart)->first();
                        if (! $invoice) {
                            $invoice = Invoice::create([
                                'number' => 'INV-'.now()->format('YmdHis').'-'.strtoupper(Str::random(5)),
                                'type' => $contract->direction === 'income' ? 'receivable' : 'payable',
                                'party_id' => $contract->party_id,
                                'billing_contract_id' => $contract->id,
                                'issue_date' => $today->toDateString(),
                                'due_date' => $start->addDays((int) $contract->payment_terms_days)->toDateString(),
                                'period_start' => $periodStart,
                                'period_end' => $end->toDateString(),
                                'status' => 'draft',
                                'currency' => $contract->currency,
                                'source_currency' => $contract->currency,
                                'source_amount' => $contract->currency === 'IDR' ? null : $contract->amount,
                                'subtotal' => $contract->amount,
                                'total' => $contract->amount,
                                'discount' => '0.00',
                                'tax' => '0.00',
                                'notes' => 'Draft otomatis. Periksa sebelum diterbitkan.',
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
                    if ($count >= $maxBackfillPerContract && $this->periodStart($contract, (int) $contract->billing_sequence)->subDays((int) $contract->generate_days_before)->lte($today)) {
                        $backlogs[] = $contract->number;
                    }
                });
            }
        });

        return ['created' => $created, 'backlogs' => $backlogs];
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
