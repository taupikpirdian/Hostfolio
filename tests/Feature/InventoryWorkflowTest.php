<?php

namespace Tests\Feature;

use App\Actions\Inventory\DeployService;
use App\Actions\Inventory\SaveComputeInstance;
use App\Actions\Inventory\SetResourceAllocation;
use App\Filament\Resources\ComputeInstances\Pages\ManageComputeInstances;
use App\Filament\Resources\ComputeInstances\Pages\ViewComputeInstance;
use App\Filament\Resources\Domains\Pages\ManageDomains;
use App\Filament\Resources\ServiceDeployments\Pages\ManageServiceDeployments;
use App\Filament\Resources\Services\Pages\ManageServices;
use App\Models\AuditLog;
use App\Models\ComputeInstance;
use App\Models\Domain;
use App\Models\Invoice;
use App\Models\PhysicalServer;
use App\Models\Provider;
use App\Models\Service;
use App\Models\User;
use App\Services\ReportService;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class InventoryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $user->forceFill(['role' => 'admin'])->save();
        $this->actingAs($user);
    }

    public function test_external_vps_can_exist_without_a_physical_server(): void
    {
        $instance = app(SaveComputeInstance::class)->execute(['code' => 'VPS-1', 'name' => 'External', 'type' => 'vps', 'status' => 'active']);
        $this->assertNull($instance->physical_server_id);
    }

    public function test_parent_cycle_is_rejected(): void
    {
        $save = app(SaveComputeInstance::class);
        $parent = $save->execute(['code' => 'VM-1', 'name' => 'VM', 'type' => 'vm', 'status' => 'active']);
        $child = $save->execute(['code' => 'DOCKER-1', 'name' => 'Docker', 'type' => 'docker', 'status' => 'active', 'parent_id' => $parent->id]);
        $this->expectException(ValidationException::class);
        $save->execute(['parent_id' => $child->id], $parent);
    }

    public function test_allocation_replacement_closes_history_and_preserves_unknown_values(): void
    {
        $instance = ComputeInstance::factory()->create();
        $first = app(SetResourceAllocation::class)->execute($instance, ['vcpu' => 2, 'ram_mb' => 2048, 'storage_gb' => null]);
        $second = app(SetResourceAllocation::class)->execute($instance, ['vcpu' => 4, 'ram_mb' => 4096, 'storage_gb' => 100]);
        $this->assertNotNull($first->fresh()->effective_to);
        $this->assertNull($first->storage_gb);
        $this->assertSame($second->id, $instance->currentAllocation()->first()->id);
        $this->assertSame(2, $instance->allocations()->count());
    }

    public function test_duplicate_active_deployment_is_rejected_but_multiple_hosts_are_allowed(): void
    {
        $service = Service::factory()->create();
        $first = ComputeInstance::factory()->create();
        $second = ComputeInstance::factory()->create();
        app(DeployService::class)->execute($service, $first);
        app(DeployService::class)->execute($service, $second);
        $this->assertSame(2, $service->deployments()->count());
        $this->expectException(ValidationException::class);
        app(DeployService::class)->execute($service, $first);
    }

    public function test_only_direct_allocations_are_counted_on_physical_server(): void
    {
        $host = PhysicalServer::factory()->create(['cpu_cores' => 4]);
        $vm = ComputeInstance::factory()->create(['physical_server_id' => $host->id, 'type' => 'vm']);
        $docker = ComputeInstance::factory()->create(['physical_server_id' => $host->id, 'parent_id' => $vm->id, 'type' => 'docker']);
        app(SetResourceAllocation::class)->execute($vm, ['vcpu' => 6, 'ram_mb' => 4096]);
        app(SetResourceAllocation::class)->execute($docker, ['vcpu' => 2, 'ram_mb' => 1024]);
        $summary = $host->allocationSummary();
        $this->assertSame(6.0, $summary['vcpu']);
        $this->assertTrue($summary['cpu_overcommitted']);
    }

    public function test_finance_cannot_modify_inventory(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'finance']));
        $this->expectException(AuthorizationException::class);
        app(SaveComputeInstance::class)->execute(['code' => 'NO', 'name' => 'No', 'type' => 'vps', 'status' => 'active']);
    }

    public function test_inventory_pages_render_for_admin_and_deny_finance(): void
    {
        $host = PhysicalServer::factory()->create();
        $instance = ComputeInstance::factory()->create(['physical_server_id' => $host->id]);
        $service = Service::factory()->create();
        app(SetResourceAllocation::class)->execute($instance, ['vcpu' => 2]);
        app(DeployService::class)->execute($service, $instance);
        Domain::factory()->create();
        Provider::factory()->create();
        foreach (['providers', 'physical-servers', 'compute-instances', 'services', 'service-deployments', 'domains'] as $path) {
            $this->get('/'.$path)->assertOk();
        }
        $this->actingAs(User::factory()->create(['role' => 'finance']));
        $this->get('/compute-instances')->assertForbidden();
    }

    public function test_database_rejects_two_active_allocations_even_outside_the_workflow(): void
    {
        $instance = ComputeInstance::factory()->create();
        app(SetResourceAllocation::class)->execute($instance, ['vcpu' => 1]);
        $this->expectException(QueryException::class);
        $instance->allocations()->create(['effective_from' => now()]);
    }

    public function test_negative_allocation_is_rejected_without_closing_current_history(): void
    {
        $instance = ComputeInstance::factory()->create();
        $first = app(SetResourceAllocation::class)->execute($instance, ['vcpu' => 2]);
        try {
            app(SetResourceAllocation::class)->execute($instance, ['vcpu' => -1]);
            $this->fail('Negative allocation was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('vcpu', $exception->errors());
        }
        $this->assertNull($first->fresh()->effective_to);
        $this->assertSame(1, $instance->allocations()->count());
    }

    public function test_inventory_create_actions_persist_valid_data(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ManageComputeInstances::class)
            ->callAction('create', data: ['name' => 'UI virtual machine', 'type' => 'vm', 'status' => 'active'])
            ->assertHasNoActionErrors();
        $instance = ComputeInstance::query()->where('name', 'UI virtual machine')->firstOrFail();
        $this->assertStringStartsWith('CMP-', $instance->code);
        $service = Service::factory()->create();
        Livewire::test(ManageServiceDeployments::class)
            ->callAction('create', data: ['service_id' => $service->id, 'compute_instance_id' => $instance->id, 'environment' => 'production', 'deployed_at' => now()->subMinute()->toDateTimeString()])
            ->assertHasNoActionErrors();
        $this->assertSame($instance->id, $service->deployments()->firstOrFail()->compute_instance_id);
    }

    public function test_compute_instance_form_saves_allocation_and_keeps_its_history(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ManageComputeInstances::class)
            ->callAction('create', data: ['name' => 'VM Alokasi', 'type' => 'vm', 'status' => 'active', 'allocation_vcpu' => 2, 'allocation_ram_gb' => 1.5])
            ->assertHasNoActionErrors();

        $instance = ComputeInstance::query()->where('name', 'VM Alokasi')->firstOrFail();
        $this->assertEquals(2, $instance->currentAllocation()->firstOrFail()->vcpu);
        $this->assertSame(1536, $instance->currentAllocation()->firstOrFail()->ram_mb);

        Livewire::test(ManageComputeInstances::class)
            ->mountTableAction('edit', $instance)
            ->assertTableActionDataSet(['allocation_ram_gb' => 1.5]);

        Livewire::test(ManageComputeInstances::class)
            ->callTableAction('edit', $instance, data: ['name' => 'VM Alokasi Baru', 'type' => 'vm', 'status' => 'active', 'allocation_vcpu' => 4, 'allocation_ram_gb' => 2.25])
            ->assertHasNoTableActionErrors();

        $this->assertSame(2, $instance->allocations()->count());
        $this->assertEquals(4, $instance->currentAllocation()->firstOrFail()->vcpu);
        $this->assertSame(2304, $instance->currentAllocation()->firstOrFail()->ram_mb);

        Livewire::test(ManageComputeInstances::class)
            ->callTableAction('edit', $instance, data: ['name' => 'VM Alokasi Terbaru', 'type' => 'vm', 'status' => 'active', 'allocation_vcpu' => 4, 'allocation_ram_gb' => 2.25])
            ->assertHasNoTableActionErrors();

        $this->assertSame(2, $instance->allocations()->count());
    }

    public function test_compute_instance_detail_displays_allocation_history(): void
    {
        $instance = ComputeInstance::factory()->create(['name' => 'VM Detail']);
        app(SetResourceAllocation::class)->execute($instance, ['vcpu' => 2, 'ram_mb' => 2048]);
        app(SetResourceAllocation::class)->execute($instance, ['vcpu' => 4, 'ram_mb' => 4096]);

        $this->get('/compute-instances/'.$instance->id)
            ->assertOk()
            ->assertSee('VM Detail')
            ->assertSee('Riwayat Alokasi')
            ->assertSee('4096');
    }

    public function test_service_created_from_instance_is_deployed_atomically(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $instance = ComputeInstance::factory()->create();

        Livewire::test(ViewComputeInstance::class, ['record' => $instance->id])
            ->callAction('createService', data: ['name' => 'Service Instance', 'type' => 'website', 'status' => 'running', 'environment' => 'production'])
            ->assertHasNoActionErrors();

        $service = Service::query()->where('name', 'Service Instance')->firstOrFail();
        $this->assertSame($instance->id, $service->deployments()->firstOrFail()->compute_instance_id);
    }

    public function test_service_form_generates_slug_from_name_and_epoch(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->travelTo(now()->startOfSecond());
        $timestamp = now()->getTimestamp();
        $instance = ComputeInstance::factory()->create();

        Livewire::test(ViewComputeInstance::class, ['record' => $instance->id])
            ->callAction('createService', data: ['name' => 'Aplikasi Internal', 'type' => 'website', 'status' => 'running', 'environment' => 'production'])
            ->assertHasNoActionErrors();

        $service = Service::query()->where('name', 'Aplikasi Internal')->firstOrFail();
        $this->assertSame('aplikasi-internal-'.$timestamp, $service->slug);

        $originalSlug = $service->slug;
        $service->update(['name' => 'Nama Baru']);
        $this->assertSame($originalSlug, $service->fresh()->slug);
    }

    public function test_domain_is_normalized_before_unique_validation(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ManageDomains::class)
            ->callAction('create', data: ['fqdn' => 'EXAMPLE.COM.', 'status' => 'active'])
            ->assertHasNoActionErrors();
        $this->assertDatabaseHas('domains', ['fqdn' => 'example.com']);
        Livewire::test(ManageDomains::class)
            ->callAction('create', data: ['fqdn' => 'Example.Com', 'status' => 'active'])
            ->assertHasActionErrors(['fqdn' => 'unique']);
    }

    public function test_direct_model_update_cannot_introduce_parent_cycle(): void
    {
        $parent = ComputeInstance::factory()->create();
        $child = ComputeInstance::factory()->create(['parent_id' => $parent->id]);
        $this->expectException(ValidationException::class);
        $parent->update(['parent_id' => $child->id]);
    }

    public function test_child_cannot_claim_another_physical_host(): void
    {
        $first = PhysicalServer::factory()->create();
        $second = PhysicalServer::factory()->create();
        $parent = ComputeInstance::factory()->create(['physical_server_id' => $first->id]);
        $this->expectException(ValidationException::class);
        app(SaveComputeInstance::class)->execute(['code' => 'DOCKER-X', 'name' => 'Docker', 'type' => 'docker', 'status' => 'active', 'parent_id' => $parent->id, 'physical_server_id' => $second->id]);
    }

    public function test_ended_deployment_is_retained_when_service_is_redeployed(): void
    {
        $service = Service::factory()->create();
        $instance = ComputeInstance::factory()->create();
        $old = app(DeployService::class)->execute($service, $instance);
        $old->update(['removed_at' => now()]);
        $new = app(DeployService::class)->execute($service, $instance);
        $this->assertNotSame($old->id, $new->id);
        $this->assertNotNull($old->fresh()->removed_at);
        $this->assertSame(2, $service->deployments()->count());
    }

    public function test_archive_keeps_invoice_snapshot_and_resource_summary_does_not_double_count(): void
    {
        $host = PhysicalServer::factory()->create();
        $vm = ComputeInstance::factory()->create(['physical_server_id' => $host->id, 'type' => 'vm']);
        $docker = ComputeInstance::factory()->create(['parent_id' => $vm->id, 'physical_server_id' => $host->id, 'type' => 'docker']);
        app(SetResourceAllocation::class)->execute($vm, ['vcpu' => 4, 'ram_mb' => 4096]);
        app(SetResourceAllocation::class)->execute($docker, ['vcpu' => 2, 'ram_mb' => 1024]);
        $service = Service::factory()->create();
        $invoice = Invoice::factory()->create();
        $item = $invoice->items()->create(['description' => 'Hosting', 'quantity' => 1, 'unit_price' => 800000, 'target_type' => Service::class, 'target_id' => $service->id]);
        $service->update(['status' => 'archived']);
        $this->assertNotNull($service->fresh()->archived_at);
        $this->assertSame($service->id, $item->fresh()->target_id);
        $this->assertNotNull($invoice->fresh());
        $summary = app(ReportService::class)->inventorySummary();
        $this->assertEquals(4, $summary['Direct allocated vCPU']);
        $this->assertEquals(4096, $summary['Direct allocated RAM (MB)']);
    }

    public function test_domain_service_association_changes_are_audited_from_both_editors(): void
    {
        $domain = Domain::factory()->create();
        $first = Service::factory()->create();
        $second = Service::factory()->create();
        $domain->services()->sync([$first->id]);
        $attached = AuditLog::query()->where('event', 'domain.service_attached')->latest('id')->firstOrFail();
        $this->assertSame(Domain::class, $attached->subject_type);
        $this->assertSame($domain->id, $attached->subject_id);
        $this->assertSame([], $attached->changes['before']['service_ids']);
        $this->assertSame([$first->id], $attached->changes['after']['service_ids']);
        $second->domains()->sync([$domain->id]);
        $domain->services()->sync([$second->id]);
        $detached = AuditLog::query()->where('event', 'domain.service_detached')->latest('id')->firstOrFail();
        $this->assertSame([$first->id, $second->id], $detached->changes['before']['service_ids']);
        $this->assertSame([$second->id], $detached->changes['after']['service_ids']);
    }

    public function test_service_filters_follow_only_active_deployments(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $provider = Provider::factory()->create();
        $host = PhysicalServer::factory()->create();
        $vm = ComputeInstance::factory()->create(['physical_server_id' => $host->id, 'provider_id' => $provider->id, 'type' => 'vm']);
        $docker = ComputeInstance::factory()->create(['type' => 'docker']);
        $active = Service::factory()->create();
        $old = Service::factory()->create();
        $other = Service::factory()->create();
        app(DeployService::class)->execute($active, $vm);
        app(DeployService::class)->execute($old, $vm)->update(['removed_at' => now()]);
        app(DeployService::class)->execute($other, $docker);
        foreach (['physical_server' => $host->id, 'provider' => $provider->id, 'instance_type' => 'vm'] as $filter => $value) {
            Livewire::test(ManageServices::class)
                ->filterTable($filter, $value)
                ->assertCanSeeTableRecords([$active])
                ->assertCanNotSeeTableRecords([$old, $other]);
        }
    }
}
