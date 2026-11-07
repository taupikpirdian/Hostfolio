<?php

namespace Tests\Feature;

use App\Actions\Billing\GenerateInvoice;
use App\Actions\Billing\IssueInvoice;
use App\Actions\Billing\RecordPayment;
use App\Actions\Billing\ReversePayment;
use App\Actions\Billing\VoidInvoice;
use App\Filament\Resources\ComputeInstances\Pages\ViewComputeInstance;
use App\Filament\Pages\BillingOverview;
use App\Filament\Resources\BillingContracts\Pages\ManageBillingContracts;
use App\Filament\Resources\Invoices\Pages\ManageInvoices;
use App\Models\BillingContract;
use App\Models\ComputeInstance;
use App\Models\Invoice;
use App\Models\Party;
use App\Models\Service;
use App\Models\User;
use App\Services\ReportService;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Tests\TestCase;

class BillingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_billing_overview_shows_report_dashboard_with_live_data(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $invoice = Invoice::factory()->create([
            'party_id' => Party::factory(), 'status' => 'issued', 'type' => 'receivable',
            'issue_date' => today(), 'due_date' => today()->addDays(14), 'total' => '350000.00',
        ]);

        Livewire::test(BillingOverview::class)
            ->assertSee('Total Invoiced Income')
            ->assertSee('Outstanding Receivable')
            ->assertSee('Tren Cashflow')
            ->assertSee('Hasil Laporan')
            ->assertSee($invoice->number)
            ->assertSee('350.000,00');
    }

    public function test_manual_invoice_can_be_created_from_compute_instance_detail(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $instance = ComputeInstance::factory()->create();
        $party = Party::factory()->create(['type' => 'client']);
        $contract = BillingContract::create([
            'number' => 'MANUAL-'.uniqid(), 'name' => 'Instance Hosting', 'party_id' => $party->id,
            'compute_instance_id' => $instance->id, 'direction' => 'income', 'amount' => '250.00', 'currency' => 'IDR',
            'start_date' => today(), 'starts_at' => today(), 'first_billing_date' => today()->subDays(30),
            'next_billing_date' => today()->subDays(30), 'billing_cycle' => 'monthly', 'status' => 'active',
        ]);

        Livewire::test(ViewComputeInstance::class, ['record' => $instance->id])
            ->callAction('generateManualInvoice', data: [
                'billing_contract_id' => $contract->id, 'number' => 'MANUAL-INV-1',
                'issue_date' => today()->toDateString(), 'due_date' => today()->subDays(10)->toDateString(),
                'period_start' => today()->subDays(30)->toDateString(), 'period_end' => today()->subDay()->toDateString(),
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('invoices', ['number' => 'MANUAL-INV-1', 'total' => '250.00', 'status' => 'draft']);
    }

    public function test_billing_contract_can_be_edited_from_list(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $instance = ComputeInstance::factory()->create();
        $party = Party::factory()->create(['type' => 'client']);
        $contract = BillingContract::create([
            'number' => 'EDIT-1', 'name' => 'Old plan', 'party_id' => $party->id,
            'compute_instance_id' => $instance->id, 'direction' => 'income', 'amount' => '250.00',
            'currency' => 'IDR', 'start_date' => today(), 'starts_at' => today(),
            'first_billing_date' => today(), 'next_billing_date' => today(),
            'billing_cycle' => 'monthly', 'status' => 'active', 'auto_generate' => true,
        ]);

        Livewire::test(ManageBillingContracts::class)
            ->callTableAction('edit', $contract, data: [
                'number' => 'EDIT-1', 'name' => 'New plan', 'party_id' => $party->id,
                'direction' => 'income', 'amount' => '300.00', 'currency' => 'IDR',
                'starts_at' => today()->toDateString(), 'first_billing_date' => today()->toDateString(),
                'billing_cycle' => 'monthly', 'auto_generate' => false, 'status' => 'active',
                'generate_days_before' => 7, 'payment_terms_days' => 14,
            ])->assertHasNoTableActionErrors();

        $this->assertSame('New plan', $contract->fresh()->name);
        $this->assertSame('300.00', $contract->fresh()->amount);
        $this->assertFalse($contract->fresh()->auto_generate);
        $this->assertSame(7, $contract->fresh()->generate_days_before);
    }

    public function test_billing_contract_can_be_edited_from_compute_instance_tab(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $instance = ComputeInstance::factory()->create();
        $party = Party::factory()->create(['type' => 'client']);
        $contract = BillingContract::create([
            'number' => 'TAB-EDIT-1', 'name' => 'Old tab plan', 'party_id' => $party->id,
            'compute_instance_id' => $instance->id, 'direction' => 'income', 'amount' => '250.00',
            'currency' => 'IDR', 'start_date' => today(), 'starts_at' => today(),
            'first_billing_date' => today(), 'next_billing_date' => today(),
            'billing_cycle' => 'monthly', 'status' => 'active', 'auto_generate' => true,
        ]);

        Livewire::test(ViewComputeInstance::class, ['record' => $instance->id])
            ->assertSee('Edit')
            ->callAction('editBillingContract', arguments: ['contract_id' => $contract->id], data: [
                'number' => 'TAB-EDIT-1', 'name' => 'New tab plan', 'party_id' => $party->id,
                'direction' => 'income', 'amount' => '350.00', 'currency' => 'IDR',
                'starts_at' => today()->toDateString(), 'first_billing_date' => today()->toDateString(),
                'billing_cycle' => 'monthly', 'auto_generate' => false, 'status' => 'active',
                'generate_days_before' => 7, 'payment_terms_days' => 14,
            ])->assertHasNoActionErrors();

        $this->assertSame('New tab plan', $contract->fresh()->name);
        $this->assertSame('350.00', $contract->fresh()->amount);
    }

    public function test_compute_instance_contract_actions_show_invoices_in_modal(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $instance = ComputeInstance::factory()->create();
        $party = Party::factory()->create(['type' => 'client']);
        $contract = BillingContract::create([
            'number' => 'TAB-VIEW-1', 'name' => 'Modal plan', 'party_id' => $party->id,
            'compute_instance_id' => $instance->id, 'direction' => 'income', 'amount' => '250.00',
            'currency' => 'IDR', 'start_date' => today(), 'starts_at' => today(),
            'first_billing_date' => today(), 'next_billing_date' => today(),
            'billing_cycle' => 'monthly', 'status' => 'active',
        ]);
        Invoice::factory()->create([
            'number' => 'INV-TAB-1', 'billing_contract_id' => $contract->id, 'party_id' => $party->id,
        ]);

        $page = Livewire::test(ViewComputeInstance::class, ['record' => $instance->id])
            ->assertSee('Aksi kontrak TAB-VIEW-1')
            ->assertSee('Lihat Invoice')
            ->assertDontSee('INV-TAB-1')
            ->mountAction('viewContractInvoices', ['contract_id' => $contract->id])
            ->assertSet('mountedActions.0.name', 'viewContractInvoices');

        $this->assertStringContainsString('INV-TAB-1', $page->instance()->getMountedAction()->getModalContent()->render());
    }

    public function test_invoice_pdf_contains_item_and_instance_details(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $instance = ComputeInstance::factory()->create(['name' => 'PDF Compute']);
        $invoice = Invoice::factory()->create(['party_id' => Party::factory(), 'status' => 'draft']);
        $invoice->items()->create([
            'description' => 'Compute hosting', 'quantity' => '1.0000', 'unit_price' => '150.00',
            'discount' => '0.00', 'tax' => '0.00', 'total' => '150.00',
            'target_type' => ComputeInstance::class, 'target_id' => $instance->id,
        ]);

        $response = $this->get(route('invoices.pdf', $invoice));
        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_invoice_pdf_uses_customer_facing_payment_status_instead_of_issued(): void
    {
        $invoice = Invoice::factory()->create(['party_id' => Party::factory()]);
        $invoice->items()->create(['description' => 'Hosting', 'quantity' => '1', 'unit_price' => '250.00']);
        app(IssueInvoice::class)->execute($invoice);

        $html = view('pdf.invoice', ['invoice' => $invoice->fresh()->load(['party', 'items.target', 'payments']), 'invoiceSettings' => []])->render();

        $this->assertStringContainsString('BELUM LUNAS', $html);
        $this->assertStringNotContainsString('>ISSUED<', $html);
    }

    public function test_contract_invoice_pdf_filters_by_optional_period_month(): void
    {
        $instance = ComputeInstance::factory()->create();
        $party = Party::factory()->create(['type' => 'client']);
        $contract = BillingContract::create([
            'number' => 'MONTH-FILTER-1', 'name' => 'Monthly hosting', 'party_id' => $party->id,
            'compute_instance_id' => $instance->id, 'direction' => 'income', 'amount' => '250.00',
            'currency' => 'IDR', 'start_date' => '2026-10-01', 'starts_at' => '2026-10-01',
            'first_billing_date' => '2026-10-01', 'next_billing_date' => '2026-12-01',
            'billing_cycle' => 'monthly', 'status' => 'active',
        ]);
        foreach (['2026-10-01' => 'INV-OCT', '2026-11-01' => 'INV-NOV'] as $periodStart => $number) {
            $invoice = Invoice::factory()->create([
                'number' => $number, 'party_id' => $party->id, 'billing_contract_id' => $contract->id,
                'period_start' => $periodStart, 'period_end' => $periodStart === '2026-10-01' ? '2026-10-31' : '2026-11-30',
            ]);
            $invoice->items()->create(['description' => 'Hosting', 'quantity' => '1', 'unit_price' => '250.00']);
            app(IssueInvoice::class)->execute($invoice);
        }

        $shownNumbers = [];
        View::composer('pdf.contract-unpaid-invoices', function ($view) use (&$shownNumbers): void {
            $shownNumbers = $view->getData()['invoices']->pluck('number')->all();
        });

        $this->get(route('invoices.contract-unpaid-pdf', ['computeInstance' => $instance, 'contracts' => [$contract->id], 'period_month' => '2026-10']))->assertOk();
        $this->assertSame(['INV-OCT'], $shownNumbers);

        $this->get(route('invoices.contract-unpaid-pdf', ['computeInstance' => $instance, 'contracts' => [$contract->id]]))->assertOk();
        $this->assertSame(['INV-OCT', 'INV-NOV'], $shownNumbers);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ViewComputeInstance::class, ['record' => $instance->id])
            ->callAction('generateContractInvoicePdf', data: ['billing_contract_ids' => [$contract->id], 'period_month' => '2026-10'])
            ->assertHasNoActionErrors()
            ->assertRedirect(route('invoices.contract-unpaid-pdf', ['computeInstance' => $instance, 'contracts' => [$contract->id], 'period_month' => '2026-10']));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function contract(): BillingContract
    {
        $party = Party::create(['name' => 'Client', 'type' => 'client']);
        $contract = BillingContract::create(['number' => 'CTR-001', 'party_id' => $party->id, 'direction' => 'income', 'start_date' => '2026-01-01', 'billing_cycle' => 'yearly', 'status' => 'active']);
        $contract->items()->create(['description' => 'Hosting', 'quantity' => '1', 'unit_price' => '800000.00', 'effective_from' => '2026-01-01']);

        return $contract;
    }

    private function invoice(): Invoice
    {
        $invoice = app(GenerateInvoice::class)->execute($this->contract(), ['number' => 'INV-001', 'issue_date' => '2026-01-01', 'due_date' => '2026-01-14']);
        app(IssueInvoice::class)->execute($invoice);

        return $invoice;
    }

    public function test_contract_snapshot_and_partial_payments(): void
    {
        $invoice = $this->invoice();
        $invoice->contract->items()->first()->update(['unit_price' => '900000.00']);
        $this->assertSame('800000.00', $invoice->fresh()->total);
        app(RecordPayment::class)->execute($invoice, ['paid_at' => '2026-01-05', 'amount' => '300000.00', 'method' => 'transfer']);
        $this->assertSame('500000.00', $invoice->fresh()->outstanding);
        $this->assertSame('partial', $invoice->fresh()->payment_status);
        app(RecordPayment::class)->execute($invoice, ['paid_at' => '2026-01-06', 'amount' => '500000.00', 'method' => 'transfer']);
        $this->assertSame('paid', $invoice->fresh()->payment_status);
    }

    public function test_overpayment_rejected(): void
    {
        $invoice = $this->invoice();
        try {
            app(RecordPayment::class)->execute($invoice, ['paid_at' => '2026-01-05', 'amount' => '800000.01', 'method' => 'transfer']);
            $this->fail('Expected rejection');
        } catch (ValidationException) {
            $this->assertSame(0, $invoice->payments()->count());
        }
    }

    public function test_reversal_preserves_history(): void
    {
        $invoice = $this->invoice();
        $payment = app(RecordPayment::class)->execute($invoice, ['paid_at' => '2026-01-05', 'amount' => '800000.00', 'method' => 'transfer']);
        app(ReversePayment::class)->execute($payment, 'Incorrect reference');
        $this->assertNotNull($payment->fresh()->reversed_at);
        $this->assertSame('800000.00', $invoice->fresh()->outstanding);
        $this->assertSame(1, $invoice->payments()->count());
    }

    public function test_void_has_no_outstanding(): void
    {
        $invoice = $this->invoice();
        app(VoidInvoice::class)->execute($invoice);
        $this->assertSame('0.00', $invoice->fresh()->outstanding);
        $this->assertFalse($invoice->fresh()->is_overdue);
    }

    public function test_operator_cannot_issue(): void
    {
        $invoice = $this->invoice();
        $this->actingAs(User::factory()->create(['role' => 'operator']));
        $this->expectException(AuthorizationException::class);
        app(IssueInvoice::class)->execute($invoice);
    }

    public function test_issued_items_are_immutable(): void
    {
        $invoice = $this->invoice();
        $this->expectException(ValidationException::class);
        $invoice->items()->first()->update(['unit_price' => '1.00']);
    }

    public function test_financial_documents_cannot_be_deleted(): void
    {
        $invoice = $this->invoice();
        $this->expectException(ValidationException::class);
        $invoice->delete();
    }

    public function test_draft_cannot_receive_payments(): void
    {
        $invoice = Invoice::factory()->create();
        $this->expectException(ValidationException::class);
        app(RecordPayment::class)->execute($invoice, ['paid_at' => today()->toDateString(), 'amount' => '1.00', 'method' => 'cash']);
    }

    public function test_quantity_rounding_uses_decimal_arithmetic(): void
    {
        $invoice = Invoice::factory()->create();
        $invoice->items()->create(['description' => 'Fractional', 'quantity' => '1.0050', 'unit_price' => '1.00', 'discount' => '0', 'tax' => '0']);
        app(IssueInvoice::class)->execute($invoice);
        $this->assertSame('1.01', $invoice->fresh()->total);
    }

    public function test_billing_pages_render_and_operator_is_denied(): void
    {
        foreach (['/parties', '/billing-contracts', '/invoices', '/payments'] as $path) {
            $this->get($path)->assertOk();
        } $this->actingAs(User::factory()->create(['role' => 'operator']));
        $this->get('/invoices')->assertForbidden();
    }

    public function test_direct_status_mutation_cannot_bypass_issuance(): void
    {
        $invoice = Invoice::factory()->create();
        $this->expectException(ValidationException::class);
        $invoice->update(['status' => 'issued']);
    }

    public function test_cashflow_uses_payment_date_instead_of_invoice_issue_date(): void
    {
        $invoice = $this->invoice();
        app(RecordPayment::class)->execute($invoice, ['paid_at' => '2026-02-05', 'amount' => '300000.00', 'method' => 'transfer']);
        $reports = app(ReportService::class);
        $january = $reports->financialSummary('2026-01-01', '2026-01-31');
        $february = $reports->financialSummary('2026-02-01', '2026-02-28');
        $this->assertSame('800000.00', $january['Total invoiced income']);
        $this->assertSame('0.00', $january['Income received']);
        $this->assertSame('300000.00', $february['Income received']);
        $this->assertSame('300000.00', $february['Net cashflow']);
        $rows = $reports->rows('cashflow', '2026-02-01', '2026-02-28');
        $this->assertCount(1, $rows);
        $this->assertSame('2026-02-05', $rows[0]['Payment date']);
    }

    public function test_foreign_currency_requires_manual_rate_source(): void
    {
        $this->expectException(ValidationException::class);
        Invoice::factory()->create(['source_currency' => 'USD', 'source_amount' => '50.00', 'exchange_rate' => '16000.00']);
    }

    public function test_foreign_currency_stores_idr_equivalent_and_rate_snapshot(): void
    {
        $invoice = Invoice::factory()->create(['type' => 'payable', 'source_currency' => 'USD', 'source_amount' => '50.00', 'exchange_rate' => '16000.00', 'exchange_rate_source' => 'Bank statement 2026-01-01']);
        $invoice->items()->create(['description' => 'VPS USD50', 'quantity' => '1', 'unit_price' => '800000.00']);
        app(IssueInvoice::class)->execute($invoice);
        $this->assertSame('800000.00', $invoice->fresh()->total);
        $this->assertSame('16000.000000', $invoice->fresh()->exchange_rate);
    }

    public function test_ad_hoc_invoice_can_target_a_service_and_preserves_issued_snapshot(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $service = Service::factory()->create();
        $party = Party::factory()->create();
        Livewire::test(ManageInvoices::class)
            ->callAction('create', data: [
                'number' => 'ADHOC-001', 'type' => 'receivable', 'party_id' => $party->id,
                'source_currency' => 'IDR', 'issue_date' => '2026-01-01', 'due_date' => '2026-01-14',
                'items' => [['description' => 'Ad hoc hosting', 'quantity' => 1, 'unit_price' => '800000.00', 'discount' => 0, 'tax' => 0, 'target_type' => Service::class, 'target_id' => $service->id]],
            ])->assertHasNoActionErrors();
        $invoice = Invoice::where('number', 'ADHOC-001')->firstOrFail();
        $item = $invoice->items()->firstOrFail();
        $this->assertSame(Service::class, $item->target_type);
        $this->assertSame($service->id, $item->target_id);
        app(IssueInvoice::class)->execute($invoice);
        $service->update(['status' => 'archived']);
        $rows = app(ReportService::class)->rows('income_by_service');
        $this->assertSame('Service #'.$service->id.' · '.$service->name, $rows[0]['Group']);
        $this->assertSame('800000.00', $rows[0]['Invoiced IDR']);
        $this->expectException(ValidationException::class);
        $item->fresh()->update(['target_id' => Service::factory()->create()->id]);
    }
}
