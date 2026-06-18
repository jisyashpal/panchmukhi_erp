<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_users_index(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Users');
    }

    public function test_super_admin_can_create_site_user_plant_wise(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $site = Site::factory()->create();
        $roleId = Role::where('slug', 'site_in_charge')->value('id');

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Plant User',
            'email' => 'plantuser@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role_id' => $roleId,
            'site_id' => $site->id,
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'plantuser@test.com',
            'site_id' => $site->id,
            'role_id' => $roleId,
        ]);
    }

    public function test_super_admin_user_clears_site_when_head_office_role(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $site = Site::factory()->create();
        $superAdminRoleId = Role::where('slug', Role::SLUG_SUPER_ADMIN)->value('id');

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Another Admin',
            'email' => 'admin2@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role_id' => $superAdminRoleId,
            'site_id' => $site->id,
            'is_active' => 1,
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'admin2@test.com',
            'site_id' => null,
            'role_id' => $superAdminRoleId,
        ]);
    }

    public function test_users_index_filters_by_site(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $siteA = Site::factory()->create(['name' => 'Alpha Plant']);
        $siteB = Site::factory()->create(['name' => 'Beta Plant']);
        $roleId = Role::where('slug', Role::SLUG_PURCHASER)->value('id');

        User::factory()->create(['site_id' => $siteA->id, 'role_id' => $roleId, 'name' => 'User Alpha']);
        User::factory()->create(['site_id' => $siteB->id, 'role_id' => $roleId, 'name' => 'User Beta']);

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['site_id' => $siteA->id]))
            ->assertOk()
            ->assertSee('User Alpha')
            ->assertDontSee('User Beta');
    }

    public function test_site_user_can_access_site_routes(): void
    {
        $site = Site::factory()->create();
        $user = User::factory()->create([
            'site_id' => $site->id,
            'role_id' => Role::where('slug', 'site_in_charge')->value('id'),
        ]);

        $this->actingAs($user)
            ->get(route('site.dashboard'))
            ->assertOk()
            ->assertSee('Site Dashboard');
    }

    public function test_site_user_cannot_access_admin(): void
    {
        $user = User::factory()->create([
            'site_id' => Site::factory()->create()->id,
            'role_id' => Role::where('slug', 'site_in_charge')->value('id'),
        ]);

        $this->actingAs($user)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }
}
