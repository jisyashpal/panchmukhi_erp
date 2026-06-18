<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\Product;
use App\Models\ProductRequest;
use App\Models\ProductRequestItem;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HoPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function hoOperator(): User
    {
        return User::factory()->create([
            'site_id' => null,
            'role_id' => Role::where('slug', Role::SLUG_HO_DATA_OPERATOR)->value('id'),
        ]);
    }

    public function test_ho_operator_can_access_dashboard(): void
    {
        $user = $this->hoOperator();

        $this->actingAs($user)->get(route('ho.dashboard'))
            ->assertOk()
            ->assertSee('Stock Lines');
    }

    public function test_ho_operator_can_update_product_request_billing(): void
    {
        $site = Site::factory()->create();
        $product = Product::factory()->create();
        $ho = $this->hoOperator();

        $request = ProductRequest::create([
            'site_id' => $site->id,
            'requested_by' => $ho->id,
            'status' => RequestStatus::Submitted,
        ]);

        $item = ProductRequestItem::create([
            'product_request_id' => $request->id,
            'product_id' => $product->id,
            'quantity' => 10,
        ]);

        $this->actingAs($ho)
            ->put(route('ho.product-requests.update', $request), [
                'items' => [
                    [
                        'id' => $item->id,
                        'quantity' => 12,
                        'unit_price' => 100,
                        'gst_rate' => 18,
                    ],
                ],
            ])
            ->assertRedirect();

        $item->refresh();
        $this->assertEquals(12, (float) $item->quantity);
        $this->assertEquals(100, (float) $item->unit_price);
    }

    public function test_login_redirects_ho_operator_to_ho_dashboard(): void
    {
        $user = $this->hoOperator();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('ho.dashboard', absolute: false));
    }
}
