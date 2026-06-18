<?php

namespace Tests\Feature\Site;

use App\Enums\RequestStatus;
use App\Models\Product;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitePortalTest extends TestCase
{
    use RefreshDatabase;

    protected function siteInCharge(Site $site): User
    {
        return User::factory()->create([
            'site_id' => $site->id,
            'role_id' => Role::where('slug', Role::SLUG_SITE_IN_CHARGE)->value('id'),
        ]);
    }

    protected function purchaser(Site $site): User
    {
        return User::factory()->create([
            'site_id' => $site->id,
            'role_id' => Role::where('slug', Role::SLUG_PURCHASER)->value('id'),
        ]);
    }

    public function test_site_user_lands_on_dashboard(): void
    {
        $site = Site::factory()->create();
        $user = $this->siteInCharge($site);

        $this->actingAs($user)->get(route('site.dashboard'))
            ->assertOk()
            ->assertSee('Site Dashboard');
    }

    public function test_site_in_charge_can_create_product_request(): void
    {
        $site = Site::factory()->create();
        $inCharge = $this->siteInCharge($site);
        $product = Product::factory()->create();

        $this->actingAs($inCharge)
            ->post(route('site.product-requests.store'), [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 2],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('product_requests', [
            'site_id' => $site->id,
            'requested_by' => $inCharge->id,
            'status' => RequestStatus::Submitted->value,
        ]);
    }

    public function test_purchaser_can_access_suppliers(): void
    {
        $site = Site::factory()->create();
        $purchaser = $this->purchaser($site);

        $this->actingAs($purchaser)->get(route('site.suppliers.index'))->assertOk();
    }

    public function test_login_redirects_site_user_to_site_dashboard(): void
    {
        $site = Site::factory()->create();
        $user = $this->siteInCharge($site);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('site.dashboard', absolute: false));
    }
}
