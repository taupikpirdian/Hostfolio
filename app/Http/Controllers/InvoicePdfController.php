<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\ApplicationSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;

class InvoicePdfController
{
    public function __invoke(Invoice $invoice)
    {
        Gate::authorize('view', $invoice);
        $invoice->load(['party', 'contract.computeInstance.provider', 'contract.service.deployments.instance', 'items.target', 'payments']);
        $invoiceSettings = ApplicationSetting::query()
            ->whereIn('key', ['organization_name', 'contact_email', 'address', 'payment_information', 'invoice_footer'])
            ->pluck('value', 'key')
            ->all();

        return Pdf::loadView('pdf.invoice', ['invoice' => $invoice, 'invoiceSettings' => $invoiceSettings])
            ->setPaper('a4')
            ->stream($invoice->number.'.pdf');
    }
}
