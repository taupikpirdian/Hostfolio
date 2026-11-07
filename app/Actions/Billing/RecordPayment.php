<?php

namespace App\Actions\Billing;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RecordPayment
{
    public function execute(Invoice $invoice, array $data): Payment
    {
        Gate::authorize('update', $invoice);

        return DB::transaction(function () use ($invoice, $data): Payment {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if ($invoice->status !== 'issued') {
                throw ValidationException::withMessages(['invoice' => 'Payments require an issued invoice.']);
            }
            $data = Validator::make($data, ['paid_at' => 'required|date', 'amount' => 'required|numeric|gt:0', 'method' => 'required|string|max:255', 'reference' => 'nullable|string|max:255', 'proof_path' => 'nullable|string|max:255', 'notes' => 'nullable|string'])->validate();
            $data['amount'] = Money::amount((string) $data['amount']);
            if (bccomp($data['amount'], $invoice->outstanding, 2) > 0) {
                throw ValidationException::withMessages(['amount' => 'Payment exceeds the outstanding amount.']);
            }

            return $invoice->payments()->create($data);
        });
    }
}
