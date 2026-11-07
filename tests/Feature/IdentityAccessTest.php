<?php

namespace Tests\Feature;

use App\Models\ApplicationSetting;
use App\Models\ComputeInstance;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ResourceAllocation;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceDeployment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IdentityAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_cannot_manage_billing(): void
    {
        $user = User::factory()->create(['role' => 'operator']);
        $this->assertTrue($user->canManage('inventory'));
        $this->assertFalse($user->canManage('billing'));
        $this->assertFalse($user->canView('billing'));
    }

    public function test_viewer_cannot_change_records(): void
    {
        $user = User::factory()->create(['role' => 'viewer']);
        $this->assertTrue($user->canView('inventory'));
        $this->assertFalse($user->canManage('inventory'));
    }

    public function test_inactive_admin_has_no_access(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => false]);
        $this->assertFalse($user->canManage('billing'));
    }

    public function test_last_admin_cannot_be_deactivated(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->expectException(ValidationException::class);
        $user->update(['is_active' => false]);
    }

    public function test_admin_permissions_cannot_be_removed_from_admin_role(): void
    {
        $this->expectException(ValidationException::class);
        Role::where('name', 'admin')->first()->update(['permissions' => []]);
    }

    public function test_organization_setting_changes_panel_brand(): void
    {
        ApplicationSetting::create(['key' => 'organization_name', 'value' => 'My Infrastructure']);
        $this->get('/login')->assertOk()->assertSee('My Infrastructure');
    }

    public function test_root_is_the_authenticated_dashboard(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get('/')
            ->assertOk();
    }

    public function test_hosted_services_show_their_instance(): void
    {
        $service = Service::factory()->create(['name' => 'Aplikasi Keuangan']);
        $instance = ComputeInstance::factory()->create(['name' => 'VM Keuangan']);
        ServiceDeployment::factory()->create([
            'service_id' => $service->id,
            'compute_instance_id' => $instance->id,
            'is_primary' => true,
        ]);
        ResourceAllocation::factory()->create(['compute_instance_id' => $instance->id, 'ram_mb' => 2048]);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get('/')
            ->assertOk()
            ->assertSee('<th class="px-4 py-3">Instance</th>', false)
            ->assertSee('VM Keuangan')
            ->assertSee('2 GB RAM')
            ->assertDontSee('Resource (legacy)')
            ->assertDontSee('Periode Akhir')
            ->assertDontSee('Amount (legacy)')
            ->assertSee('/compute-instances/'.$instance->id, false);
    }

    public function test_dashboard_lists_unpaid_invoices_instead_of_renewals(): void
    {
        Invoice::factory()->create(['number' => 'INV-BELUM-001', 'status' => 'issued', 'total' => '150000.00']);
        Invoice::factory()->create(['number' => 'INV-NOL-002', 'status' => 'issued', 'total' => '0.00']);
        $paid = Invoice::factory()->create(['number' => 'INV-LUNAS-003', 'status' => 'issued', 'total' => '50000.00']);
        Payment::factory()->create(['invoice_id' => $paid->id, 'amount' => '50000.00']);
        Invoice::factory()->create(['number' => 'INV-BATAL-004', 'status' => 'void', 'total' => '90000.00']);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get('/')
            ->assertOk()
            ->assertSee('Invoice Belum Dibayar')
            ->assertSee('INV-BELUM-001')
            ->assertSee('INV-NOL-002')
            ->assertDontSee('INV-LUNAS-003')
            ->assertDontSee('INV-BATAL-004')
            ->assertDontSee('Upcoming Renewals');
    }

    public function test_root_redirects_guests_to_panel_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }
}
