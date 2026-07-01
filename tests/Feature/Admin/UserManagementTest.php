<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\Plant;
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

    public function test_super_admin_can_create_plant_user_plant_wise(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $plant = Plant::factory()->create();
        $roleId = Role::where('slug', Role::SLUG_PLANT_IN_CHARGE)->value('id');

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Plant User',
            'email' => 'plantuser@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role_id' => $roleId,
            'plant_id' => $plant->id,
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'plantuser@test.com',
            'plant_id' => $plant->id,
            'role_id' => $roleId,
        ]);
    }

    public function test_super_admin_user_clears_plant_when_head_office_role(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $plant = Plant::factory()->create();
        $superAdminRoleId = Role::where('slug', Role::SLUG_SUPER_ADMIN)->value('id');

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Another Admin',
            'email' => 'admin2@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role_id' => $superAdminRoleId,
            'plant_id' => $plant->id,
            'is_active' => 1,
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'admin2@test.com',
            'plant_id' => null,
            'role_id' => $superAdminRoleId,
        ]);
    }

    public function test_users_index_filters_by_plant(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $plantA = Plant::factory()->create(['name' => 'Alpha Plant']);
        $plantB = Plant::factory()->create(['name' => 'Beta Plant']);
        $roleId = Role::where('slug', Role::SLUG_PURCHASER)->value('id');

        User::factory()->create(['plant_id' => $plantA->id, 'role_id' => $roleId, 'name' => 'User Alpha']);
        User::factory()->create(['plant_id' => $plantB->id, 'role_id' => $roleId, 'name' => 'User Beta']);

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['plant_id' => $plantA->id]))
            ->assertOk()
            ->assertSee('User Alpha')
            ->assertDontSee('User Beta');
    }

    public function test_plant_user_can_access_plant_routes(): void
    {
        $plant = Plant::factory()->create();
        $user = User::factory()->create([
            'plant_id' => $plant->id,
            'role_id' => Role::where('slug', Role::SLUG_PLANT_IN_CHARGE)->value('id'),
        ]);

        $this->actingAs($user)
            ->get(route('plant.dashboard'))
            ->assertOk()
            ->assertSee('Plant Dashboard');
    }

    public function test_plant_user_cannot_access_admin(): void
    {
        $user = User::factory()->create([
            'plant_id' => Plant::factory()->create()->id,
            'role_id' => Role::where('slug', Role::SLUG_PLANT_IN_CHARGE)->value('id'),
        ]);

        $this->actingAs($user)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }
}
