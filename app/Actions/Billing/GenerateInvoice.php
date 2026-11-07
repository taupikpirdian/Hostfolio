<?php

namespace App\Actions\Billing;

use App\Models\BillingContract;
use App\Models\ComputeInstance;
use App\Models\Invoice;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class GenerateInvoice
{
    public function execute(BillingContract $contract, array $data): Invoice
    {
        Gate::authorize('create', Invoice::class);

        return DB::transaction(function () use ($contract, $data): Invoice {
            $contract = BillingContract::query()->lockForUpdate()->findOrFail($contract->id);
            if ($contract->status !== 'active') {
                throw ValidationException::withMessages(['contract' => 'Only active contracts can generate invoices.']);
            }
            $data = Validator::make($data, ['number' => 'required|string|max:255|unique:invoices,number', 'issue_date' => 'required|date', 'due_date' => 'required|date', 'period_start' => 'nullable|date', 'period_end' => 'nullable|date|after_or_equal:period_start'])->validate();
            $items = $contract->items()->whereDate('effective_from', '<=', $data['issue_date'])->get();
            if ($items->isEmpty() && filled($contract->amount)) {
                $items = collect([(object) [
                    'description' => $contract->name ?: $contract->description,
                    'quantity' => '1.0000',
                    'unit_price' => $contract->amount,
                    'target_type' => $contract->service_id ? Service::class : ($contract->compute_instance_id ? ComputeInstance::class : null),
                    'target_id' => $contract->service_id ?? $contract->compute_instance_id,
                ]]);
            }
            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Contract has no effective price.']);
            }
            $invoice = Invoice::create($data + ['currency' => $contract->currency ?: 'IDR', 'source_currency' => $contract->currency ?: 'IDR', 'source_amount' => ($contract->currency ?? 'IDR') === 'IDR' ? null : $contract->amount, 'party_id' => $contract->party_id, 'billing_contract_id' => $contract->id, 'type' => $contract->direction === 'income' ? 'receivable' : 'payable', 'status' => 'draft']);
            $totals = ['subtotal' => '0.00', 'discount' => '0.00', 'tax' => '0.00', 'total' => '0.00'];
            foreach ($items as $item) {
                $line = Money::line($item->quantity, $item->unit_price);
                foreach ($totals as $key => $value) {
                    $totals[$key] = bcadd($value, $line[$key], 2);
                    Money::amount($totals[$key]);
                }
                $invoice->items()->create(['description' => $item->description, 'quantity' => $item->quantity, 'unit_price' => $item->unit_price, 'discount' => '0', 'tax' => '0', 'total' => $line['total'], 'target_type' => $item->target_type, 'target_id' => $item->target_id]);
            }

            $invoice->update($totals);

            return $invoice;
        });
    }
}
