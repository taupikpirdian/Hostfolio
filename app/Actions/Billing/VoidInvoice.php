<?php

namespace App\Actions\Billing;

use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class VoidInvoice
{
    public function execute(Invoice $invoice): Invoice
    {
        Gate::authorize('update', $invoice);

        return DB::transaction(function () use ($invoice): Invoice {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if ($invoice->status === 'void' || $invoice->payments()->whereNull('reversed_at')->exists()) {
                throw ValidationException::withMessages(['invoice' => 'Reverse all payments before voiding this invoice.']);
            }
            $invoice->transitionTo('void');

            return $invoice;
        });
    }
}
