<?php

namespace Tests\Feature\Site;

use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_user_can_view_and_update_profile(): void
    {
        $site = Site::factory()->create();
        $user = User::factory()->create([
            'site_id' => $site->id,
            'role_id' => Role::where('slug', Role::SLUG_SITE_IN_CHARGE)->value('id'),
        ]);

        $this->actingAs($user)
            ->get(route('site.profile.edit'))
            ->assertOk()
            ->assertSee('Profile');

        $this->actingAs($user)
            ->patch(route('site.profile.update'), [
                'name' => 'Updated Name',
                'email' => 'updated@example.com',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('site.profile.edit', absolute: false));

        $user->refresh();
        $this->assertSame('Updated Name', $user->name);
        $this->assertSame('updated@example.com', $user->email);
    }
}
