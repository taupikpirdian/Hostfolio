<?php

namespace Tests\Feature;

use App\Actions\Billing\IssueInvoice;
use App\Actions\Billing\RecordPayment;
use App\Actions\Inventory\ConfirmInventoryImport;
use App\Actions\Inventory\StageInventoryImport;
use App\Filament\Pages\ImportInventory;
use App\Filament\Pages\Reports;
use App\Models\BillingContract;
use App\Models\ComputeInstance;
use App\Models\Domain;
use App\Models\ImportBatch;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ReportingImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleted_csv_is_staged_then_archived_without_creating_money_or_allocations(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $batch = app(StageInventoryImport::class)->execute("Service,Status,Core,Amount\nLegacy website,DELETED,-,800000\n", ['name' => 'Service', 'status' => 'Status']);
        $this->assertDatabaseCount('services', 0);
        $instance = ComputeInstance::factory()->create();
        app(ConfirmInventoryImport::class)->execute($batch, [$batch->rows()->first()->id => $instance->id]);
        $this->assertDatabaseHas('services', ['name' => 'Legacy website', 'status' => 'archived']);
        $this->assertDatabaseHas('service_deployments', ['compute_instance_id' => $instance->id]);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('resource_allocation_history', 0);
        $this->assertSame('800000', $batch->rows()->first()->raw['Amount']);
    }

    public function test_duplicate_csv_is_blocked_before_confirmation(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $batch = app(StageInventoryImport::class)->execute("Service\nDuplicate\nDuplicate\n", ['name' => 'Service']);
        $this->expectException(ValidationException::class);
        app(ConfirmInventoryImport::class)->execute($batch);
    }

    public function test_import_requires_instance_mapping_before_creating_service(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $batch = app(StageInventoryImport::class)->execute("Service\nUnmapped service\n", ['name' => 'Service']);

        try {
            app(ConfirmInventoryImport::class)->execute($batch);
            $this->fail('Import tanpa Compute Instance diterima.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('batch', $exception->errors());
        }

        $this->assertDatabaseCount('services', 0);
    }

    public function test_finance_cannot_import_inventory(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'finance']));
        $this->expectException(AuthorizationException::class);
        app(StageInventoryImport::class)->execute("Service\nExample\n", ['name' => 'Service']);
    }

    public function test_report_exports_require_authorization(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'operator']));
        $this->get('/reports/export?report=cashflow')->assertForbidden();
    }

    public function test_reports_and_settings_render_for_admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach (['/', '/reports', '/import-inventory', '/users', '/roles', '/categories', '/application-settings', '/audit-logs'] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_finance_reports_render_without_inventory_access(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'finance']));
        $this->get('/reports')->assertOk();
        $this->get('/users')->assertForbidden();
        $this->get('/import-inventory')->assertForbidden();
    }

    public function test_reporting_separates_invoices_from_cash_received_by_payment_date(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $invoice = Invoice::factory()->create(['issue_date' => '2026-01-01', 'due_date' => '2026-01-14', 'type' => 'receivable']);
        $invoice->items()->create(['description' => 'Hosting', 'quantity' => '1', 'unit_price' => '800000', 'discount' => '0', 'tax' => '0']);
        app(IssueInvoice::class)->execute($invoice);
        app(RecordPayment::class)->execute($invoice, ['paid_at' => '2026-02-01', 'amount' => '300000', 'method' => 'transfer']);
        $report = app(ReportService::class);
        $january = $report->financialSummary('2026-01-01', '2026-01-31');
        $february = $report->financialSummary('2026-02-01', '2026-02-28');
        $this->assertSame('800000.00', $january['Total invoiced income']);
        $this->assertSame('0.00', $january['Income received']);
        $this->assertSame('0.00', $february['Total invoiced income']);
        $this->assertSame('300000.00', $february['Income received']);
        $this->assertSame('500000.00', $january['Accounts receivable']);
        $this->assertSame('300000.00', $february['Net cashflow']);
    }

    public function test_renewals_keep_domain_expiration_separate(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Domain::factory()->create(['fqdn' => 'example.test', 'expires_at' => today()->addDays(7)]);
        $rows = app(ReportService::class)->rows('renewals', null, null, 14);
        $this->assertCount(1, $rows);
        $this->assertSame('Domain', $rows[0]['Kind']);
        $this->assertSame(7, $rows[0]['Days remaining']);
    }

    public function test_inventory_csv_escapes_spreadsheet_formulas(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Service::factory()->create(['name' => '=DANGEROUS()']);
        $response = $this->get('/reports/export?report=inventory')->assertOk();
        $this->assertStringContainsString("'=DANGEROUS()", $response->streamedContent());
    }

    public function test_expired_renewals_exclude_today(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Domain::factory()->create(['expires_at' => today()]);
        $this->assertCount(0, app(ReportService::class)->rows('renewals', null, null, 0));
    }

    public function test_service_income_groups_by_identity_instead_of_shared_name(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach (['a', 'b'] as $slug) {
            $service = Service::factory()->create(['name' => 'Same name', 'slug' => $slug]);
            $invoice = Invoice::factory()->create(['type' => 'receivable']);
            $invoice->items()->create(['description' => 'Hosting', 'quantity' => 1, 'unit_price' => 100, 'discount' => 0, 'tax' => 0, 'target_type' => Service::class, 'target_id' => $service->id]);
            app(IssueInvoice::class)->execute($invoice);
        }
        $this->assertCount(2, app(ReportService::class)->rows('income_by_service'));
    }

    public function test_inventory_reports_paginate_in_database(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Service::factory()->count(30)->create();
        $reports = app(ReportService::class);
        $this->assertCount(5, $reports->rows('inventory', page: 2));
        $this->assertSame(30, $reports->totalRows);
    }

    public function test_csv_can_be_filtered_with_only_end_date(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get('/reports/export?report=cashflow&to=2026-12-31')->assertOk();
    }

    public function test_renewal_estimate_excludes_future_contract_prices(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $contract = BillingContract::factory()->create(['status' => 'active', 'next_billing_date' => today()->addDays(7)]);
        $contract->items()->create(['description' => 'Current', 'quantity' => 1, 'unit_price' => 100, 'effective_from' => today()]);
        $contract->items()->create(['description' => 'Future', 'quantity' => 1, 'unit_price' => 900, 'effective_from' => today()->addDays(30)]);
        $rows = app(ReportService::class)->rows('renewals', days: 14, page: 1);
        $this->assertCount(1, $rows);
        $this->assertSame('100.00', $rows[0]['Estimated IDR']);
    }

    public function test_livewire_import_upload_preview_and_confirmation(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $file = UploadedFile::fake()->createWithContent('inventory.csv', "Service,Status\nImported service,ACTIVE\n");
        $instance = ComputeInstance::factory()->create();
        Livewire::test(ImportInventory::class)
            ->set('csv', $file)->call('preview')->assertHasNoErrors()
            ->assertSee('Imported service')
            ->set('instances.'.ImportBatch::latest()->first()->rows()->first()->id, $instance->id)
            ->call('confirm')->assertHasNoErrors();
        $this->assertDatabaseHas('services', ['name' => 'Imported service', 'status' => 'running']);
    }

    public function test_all_report_views_and_exports_render_with_records(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $service = Service::factory()->create();
        $invoice = Invoice::factory()->create(['type' => 'receivable']);
        $invoice->items()->create(['description' => 'Hosting', 'quantity' => 1, 'unit_price' => 100, 'discount' => 0, 'tax' => 0, 'target_type' => Service::class, 'target_id' => $service->id]);
        app(IssueInvoice::class)->execute($invoice);
        Domain::factory()->create(['expires_at' => today()->addDays(1)]);
        foreach (['inventory', 'resources', 'income', 'expenses', 'cashflow', 'aging', 'unpaid', 'overdue', 'income_by_service', 'expense_by_provider', 'renewals'] as $report) {
            Livewire::test(Reports::class)->set('report', $report)->assertHasNoErrors()->assertStatus(200);
            $this->get('/reports/export?report='.$report)->assertOk()->streamedContent();
        }
    }

    public function test_legacy_unknown_resource_is_null_and_amount_is_unverified_metadata(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $batch = app(StageInventoryImport::class)->execute("Service,Core,Amount\nMetadata service,-,0\n", ['name' => 'Service', 'vcpu' => 'Core', 'amount' => 'Amount']);
        $instance = ComputeInstance::factory()->create();
        app(ConfirmInventoryImport::class)->execute($batch, [$batch->rows()->first()->id => $instance->id]);
        $legacy = Service::first()->legacy_data;
        $this->assertNull($legacy['vcpu']);
        $this->assertSame('0', $legacy['amount']);
        $this->assertFalse($legacy['verified']);
        $this->assertDatabaseCount('payments', 0);
    }
}
