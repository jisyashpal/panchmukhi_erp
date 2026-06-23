<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_roles_index(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get(route('admin.roles.index'))
            ->assertOk()
            ->assertSee('Roles');
    }

    public function test_super_admin_can_view_permissions_index(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get(route('admin.permissions.index'))
            ->assertOk()
            ->assertSee('Permissions');
    }

    public function test_super_admin_can_create_role_with_permissions(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $permission = Permission::where('slug', 'site.manage_stocks')->first();

        $response = $this->actingAs($admin)->post(route('admin.roles.store'), [
            'name' => 'Stock Keeper',
            'slug' => 'stock_keeper',
            'description' => 'Manages site stock only',
            'requires_site' => 1,
            'is_active' => 1,
            'sort_order' => 20,
            'permission_ids' => [$permission->id],
        ]);

        $response->assertRedirect(route('admin.roles.index'));

        $role = Role::where('slug', 'stock_keeper')->first();
        $this->assertNotNull($role);
        $this->assertTrue($role->hasPermission('site.manage_stocks'));
    }

    public function test_super_admin_can_create_custom_permission(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)->post(route('admin.permissions.store'), [
            'name' => 'View Site Reports',
            'slug' => 'site.view_reports',
            'module' => 'site',
            'description' => 'Custom report access',
            'sort_order' => 50,
        ]);

        $response->assertRedirect(route('admin.permissions.index'));

        $this->assertDatabaseHas('permissions', [
            'slug' => 'site.view_reports',
            'module' => 'site',
            'is_system' => false,
        ]);
    }

    public function test_system_role_cannot_be_deleted(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $role = Role::where('slug', Role::SLUG_SUPER_ADMIN)->first();

        $this->actingAs($admin)
            ->delete(route('admin.roles.destroy', $role))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_site_in_charge_has_create_product_request_permission(): void
    {
        $user = User::factory()->create([
            'role_id' => Role::where('slug', Role::SLUG_SITE_IN_CHARGE)->value('id'),
        ]);

        $this->assertTrue($user->canCreateProductRequests());
        $this->assertTrue($user->canViewSitePurchases());
        $this->assertFalse($user->canManageSitePurchases());
        $this->assertFalse($user->canManagePurchaseRequests());
    }

    public function test_purchaser_has_purchase_request_permission(): void
    {
        $user = User::factory()->create([
            'role_id' => Role::where('slug', Role::SLUG_PURCHASER)->value('id'),
        ]);

        $this->assertTrue($user->canManagePurchaseRequests());
        $this->assertFalse($user->canCreateProductRequests());
    }
}
