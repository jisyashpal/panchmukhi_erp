<?php

namespace Tests\Feature\Plant;

use App\Models\Role;
use App\Models\Plant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlantProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_plant_user_can_view_and_update_profile(): void
    {
        $plant = Plant::factory()->create();
        $user = User::factory()->create([
            'plant_id' => $plant->id,
            'role_id' => Role::where('slug', Role::SLUG_PLANT_IN_CHARGE)->value('id'),
        ]);

        $this->actingAs($user)
            ->get(route('plant.profile.edit'))
            ->assertOk()
            ->assertSee('Profile');

        $this->actingAs($user)
            ->patch(route('plant.profile.update'), [
                'name' => 'Updated Name',
                'email' => 'updated@example.com',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('plant.profile.edit', absolute: false));

        $user->refresh();
        $this->assertSame('Updated Name', $user->name);
        $this->assertSame('updated@example.com', $user->email);
    }
}
