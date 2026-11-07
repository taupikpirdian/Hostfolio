<?php

namespace App\Actions\Billing;

use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class IssueInvoice
{
    public function execute(Invoice $invoice): Invoice
    {
        Gate::authorize('update', $invoice);

        return DB::transaction(function () use ($invoice): Invoice {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if ($invoice->status !== 'draft') {
                throw ValidationException::withMessages(['invoice' => 'Only draft invoices can be issued.']);
            }
            $items = $invoice->items()->lockForUpdate()->get();
            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Add at least one invoice item.']);
            }
            $totals = ['subtotal' => '0.00', 'discount' => '0.00', 'tax' => '0.00', 'total' => '0.00'];
            foreach ($items as $item) {
                $line = Money::line($item->quantity, $item->unit_price, $item->discount, $item->tax);
                $item->update(['total' => $line['total']]);
                foreach ($totals as $key => $value) {
                    $totals[$key] = bcadd($value, $line[$key], 2);
                    Money::amount($totals[$key]);
                }
            }
            $invoice->transitionTo('issued', $totals);

            return $invoice->refresh();
        });
    }
}
