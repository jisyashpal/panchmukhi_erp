<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\Product;
use App\Models\ProductRequest;
use App\Models\ProductRequestItem;
use App\Models\Role;
use App\Models\Plant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HoPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function hoOperator(): User
    {
        return User::factory()->create([
            'plant_id' => null,
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
        $plant = Plant::factory()->create();
        $product = Product::factory()->create();
        $ho = $this->hoOperator();

        $request = ProductRequest::create([
            'plant_id' => $plant->id,
            'requested_by' => $ho->id,
            'requester_name' => $ho->name,
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

    public function test_ho_create_purchase_route_is_not_available(): void
    {
        $ho = $this->hoOperator();

        $this->actingAs($ho)
            ->get('/ho/purchases/create')
            ->assertNotFound();
    }

    public function test_ho_operator_can_view_purchases_list(): void
    {
        $ho = $this->hoOperator();

        $this->actingAs($ho)
            ->get(route('ho.purchases.index'))
            ->assertOk()
            ->assertSee('Purchases')
            ->assertSee('Search PO / Supplier');

        $this->actingAs($ho)
            ->get(route('ho.purchases.index', ['search' => 'PO-TEST', 'status' => 'ordered']))
            ->assertOk();
    }

    public function test_ho_operator_can_comment_on_purchase(): void
    {
        $ho = $this->hoOperator();
        $plant = Plant::factory()->create();
        $supplier = \App\Models\Supplier::factory()->create();
        $purchase = \App\Models\Purchase::create([
            'plant_id' => $plant->id,
            'supplier_id' => $supplier->id,
            'reference_no' => 'PO-HO-COMMENT',
            'status' => \App\Enums\PurchaseStatus::Ordered,
            'subtotal' => 100,
            'grand_total' => 118,
            'ordered_at' => now(),
        ]);

        $this->actingAs($ho)
            ->post(route('ho.purchases.comments.store', $purchase), [
                'body' => 'Verify delivery timeline with supplier.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('purchase_comments', [
            'purchase_id' => $purchase->id,
            'user_id' => $ho->id,
            'body' => 'Verify delivery timeline with supplier.',
        ]);

        $this->actingAs($ho)
            ->get(route('ho.purchases.index'))
            ->assertOk()
            ->assertSee('Verify delivery timeline', false)
            ->assertSee('Comment', false);
    }

    public function test_ho_operator_can_access_create_product_request_page(): void
    {
        $ho = $this->hoOperator();

        $this->actingAs($ho)->get(route('ho.product-requests.create'))
            ->assertOk()
            ->assertSee('New Product Request')
            ->assertSee('Plant');
    }

    public function test_ho_operator_can_manage_products(): void
    {
        $ho = $this->hoOperator();
        $category = \App\Models\Category::factory()->create();
        $unit = \App\Models\Unit::factory()->create();

        $this->actingAs($ho)->get(route('ho.categories.index'))
            ->assertOk()
            ->assertSee('Categories');

        $this->actingAs($ho)->get(route('ho.brands.index'))
            ->assertOk()
            ->assertSee('Brands');

        $this->actingAs($ho)->get(route('ho.units.index'))
            ->assertOk()
            ->assertSee('Units');

        $this->actingAs($ho)->get(route('ho.products.index'))
            ->assertOk()
            ->assertSee('Products');

        $this->actingAs($ho)->get(route('ho.products.create'))
            ->assertOk()
            ->assertSee('Add Product');

        $response = $this->actingAs($ho)->post(route('ho.products.store'), [
            'products' => [
                [
                    'name' => 'Test Cement Bag',
                    'size' => '50KG',
                    'category_id' => $category->id,
                    'unit_id' => $unit->id,
                    'reorder_level' => 15,
                    'is_active' => '1',
                ]
            ]
        ]);

        $response->assertRedirect(route('ho.products.index'));

        $this->assertDatabaseHas('products', [
            'name' => 'Test Cement Bag',
            'size' => '50KG',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'reorder_level' => 15,
            'is_active' => true,
        ]);
    }

    public function test_ho_operator_can_manage_category(): void
    {
        $ho = $this->hoOperator();

        $response = $this->actingAs($ho)->post(route('ho.categories.store'), [
            'name' => 'HO Test Category',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('ho.categories.index'));

        $this->assertDatabaseHas('categories', [
            'name' => 'HO Test Category',
            'slug' => 'ho-test-category',
        ]);
    }

    public function test_ho_operator_can_store_product_request(): void
    {
        $plant = Plant::factory()->create();
        $product = Product::factory()->create();
        $ho = $this->hoOperator();

        $response = $this->actingAs($ho)
            ->post(route('ho.product-requests.store'), [
                'plant_id' => $plant->id,
                'remarks' => 'Test remarks from HO',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 5,
                    ]
                ]
            ]);

        $response->assertRedirect();
        
        $this->assertDatabaseHas('product_requests', [
            'plant_id' => $plant->id,
            'requested_by' => $ho->id,
            'remarks' => 'Test remarks from HO',
        ]);
        
        $this->assertDatabaseHas('product_request_items', [
            'product_id' => $product->id,
            'quantity' => 5,
        ]);
    }

    public function test_ho_operator_can_view_profile(): void
    {
        $ho = $this->hoOperator();

        $this->actingAs($ho)->get(route('ho.profile.edit'))
            ->assertOk()
            ->assertSee('Profile Information')
            ->assertSee('Update Password');
    }

    public function test_ho_operator_can_update_profile(): void
    {
        $ho = $this->hoOperator();

        $response = $this->actingAs($ho)
            ->patch(route('ho.profile.update'), [
                'name' => 'New HO Name',
                'email' => 'newho@example.com',
            ]);

        $response->assertRedirect(route('ho.profile.edit'));
        $ho->refresh();
        $this->assertEquals('New HO Name', $ho->name);
        $this->assertEquals('newho@example.com', $ho->email);
    }

    public function test_ho_operator_can_delete_profile(): void
    {
        $ho = $this->hoOperator();

        $response = $this->actingAs($ho)
            ->delete(route('ho.profile.destroy'), [
                'password' => 'password',
            ]);

        $response->assertRedirect('/');
        $this->assertGuest();
        $this->assertNull($ho->fresh());
    }
}
