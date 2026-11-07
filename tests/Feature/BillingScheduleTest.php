<?php

namespace Tests\Feature;

use App\Actions\Billing\GenerateScheduledInvoices;
use App\Actions\Billing\VoidInvoice;
use App\Models\BillingContract;
use App\Models\ComputeInstance;
use App\Models\Party;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_monthly_contract_keeps_january_31_anchor_and_does_not_duplicate(): void
    {
        $this->travelTo(now()->setDate(2026, 4, 1)->startOfDay());
        $contract = $this->contract(['first_billing_date' => '2026-01-31', 'starts_at' => '2026-01-01', 'billing_cycle' => 'monthly', 'generate_days_before' => 14]);

        $generator = app(GenerateScheduledInvoices::class);
        $this->assertSame(3, $generator->run()['created']);
        $this->assertSame(0, $generator->run()['created']);
        $this->assertSame(['2026-01-31', '2026-02-28', '2026-03-31'], $contract->invoices()->orderBy('period_start')->pluck('period_start')->map->toDateString()->all());
        $this->assertSame('125.50', $contract->invoices()->first()->items()->first()->unit_price);
    }

    public function test_paused_contracts_do_not_generate_and_one_time_generates_once(): void
    {
        $this->travelTo(now()->startOfDay());
        $this->contract(['status' => 'paused']);
        $once = $this->contract(['billing_cycle' => 'one_time', 'first_billing_date' => today()->toDateString(), 'starts_at' => today()->toDateString()]);
        $this->assertSame(1, app(GenerateScheduledInvoices::class)->run()['created']);
        $this->assertSame(0, app(GenerateScheduledInvoices::class)->run()['created']);
        $this->assertSame(1, $once->invoices()->count());
    }

    public function test_generate_invoices_command_issues_the_invoice_immediately(): void
    {
        $contract = $this->contract();

        $this->artisan('billing:generate-invoices')->assertSuccessful();

        $invoice = $contract->invoices()->firstOrFail();
        $this->assertSame('issued', $invoice->status);
        $this->assertSame('125.50', $invoice->outstanding);
        $this->assertSame('125.50', $invoice->items()->firstOrFail()->total);
    }

    public function test_void_invoice_is_replaced_for_its_original_period_without_duplicating_other_periods(): void
    {
        $this->travelTo(now()->setDate(2026, 4, 1)->startOfDay());
        $contract = $this->contract(['first_billing_date' => '2026-01-31', 'starts_at' => '2026-01-01']);
        $this->assertSame(3, app(GenerateScheduledInvoices::class)->run()['created']);

        $voided = $contract->invoices()->whereDate('period_start', '2026-02-28')->firstOrFail();
        app(VoidInvoice::class)->execute($voided);

        $this->artisan('billing:generate-invoices')->expectsOutput('Issued 1 invoice(s).')->assertSuccessful();
        $this->assertSame(0, app(GenerateScheduledInvoices::class)->run()['created']);
        $this->assertSame(4, $contract->invoices()->count());
        $this->assertSame('void', $voided->fresh()->status);
        $this->assertSame(1, $contract->invoices()->whereDate('period_start', '2026-02-28')->where('status', 'issued')->count());
        $this->assertSame(3, $contract->fresh()->billing_sequence);
    }

    public function test_void_one_time_invoice_can_be_generated_again(): void
    {
        $contract = $this->contract(['billing_cycle' => 'one_time']);
        $this->assertSame(1, app(GenerateScheduledInvoices::class)->run()['created']);
        app(VoidInvoice::class)->execute($contract->invoices()->firstOrFail());

        $this->assertSame(1, app(GenerateScheduledInvoices::class)->run()['created']);
        $this->assertSame(0, app(GenerateScheduledInvoices::class)->run()['created']);
        $this->assertSame(2, $contract->invoices()->count());
    }

    public function test_moving_first_billing_date_earlier_fills_missing_period_before_sequence_cursor(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 7)->startOfDay());
        $contract = $this->contract(['first_billing_date' => '2026-11-01', 'starts_at' => '2026-11-01', 'generate_days_before' => 3]);
        $this->assertSame(1, app(GenerateScheduledInvoices::class)->run()['created']);

        $contract->update(['first_billing_date' => '2026-10-01', 'starts_at' => '2026-10-01', 'start_date' => '2026-10-01']);

        $this->artisan('billing:generate-invoices')->expectsOutput('Issued 1 invoice(s).')->assertSuccessful();
        $this->assertSame(['2026-10-01', '2026-11-01'], $contract->invoices()->orderBy('period_start')->pluck('period_start')->map->toDateString()->all());
        $this->assertSame(0, app(GenerateScheduledInvoices::class)->run()['created']);
        $this->assertSame(2, $contract->fresh()->billing_sequence);
    }

    private function contract(array $overrides = []): BillingContract
    {
        $party = Party::factory()->create(['type' => 'client']);
        $instance = ComputeInstance::factory()->create();

        return BillingContract::create(array_merge([
            'number' => 'CT-'.uniqid(), 'name' => 'Hosting', 'party_id' => $party->id,
            'compute_instance_id' => $instance->id, 'direction' => 'income', 'amount' => '125.50',
            'currency' => 'IDR', 'billing_cycle' => 'monthly', 'starts_at' => today()->toDateString(),
            'start_date' => today()->toDateString(), 'first_billing_date' => today()->toDateString(),
            'next_billing_date' => today()->toDateString(), 'generate_days_before' => 14,
            'payment_terms_days' => 0, 'auto_generate' => true, 'status' => 'active',
        ], $overrides));
    }
}
