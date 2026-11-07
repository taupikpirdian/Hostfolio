<?php

namespace Tests\Feature;

use App\Models\ApplicationSetting;
use App\Models\Role;
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

    public function test_root_redirects_guests_to_panel_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }
}
