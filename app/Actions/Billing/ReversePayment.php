<?php

namespace App\Actions\Billing;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ReversePayment
{
    public function execute(Payment $payment, string $reason): Payment
    {
        Gate::authorize('update', $payment);

        return DB::transaction(function () use ($payment, $reason): Payment {
            Invoice::query()->lockForUpdate()->findOrFail($payment->invoice_id);
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($payment->reversed_at !== null || trim($reason) === '') {
                throw ValidationException::withMessages(['reason' => 'A valid reason and unreversed payment are required.']);
            }
            $payment->update(['reversed_at' => now(), 'reversal_reason' => $reason]);

            return $payment;
        });
    }
}
