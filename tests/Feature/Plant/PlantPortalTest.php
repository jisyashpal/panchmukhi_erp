<?php

namespace Tests\Feature\Plant;

use App\Enums\RequestStatus;
use App\Models\Product;
use App\Models\Role;
use App\Models\Plant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use \App\Models\ProductRequest;
use Tests\TestCase;

class PlantPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionSeeder::class);
    }

    protected function plantInCharge(Plant $plant): User
    {
        return User::factory()->create([
            'plant_id' => $plant->id,
            'role_id' => Role::where('slug', Role::SLUG_PLANT_IN_CHARGE)->value('id'),
        ]);
    }

    protected function purchaser(?Plant $plant = null): User
    {
        return User::factory()->create([
            'plant_id' => null,
            'role_id' => Role::where('slug', Role::SLUG_PURCHASER)->value('id'),
        ]);
    }

    public function test_plant_user_lands_on_dashboard(): void
    {
        $plant = Plant::factory()->create();
        $user = $this->plantInCharge($plant);

        $this->actingAs($user)->get(route('plant.dashboard'))
            ->assertOk()
            ->assertSee('Plant Dashboard');
    }

    public function test_plant_in_charge_can_create_product_request(): void
    {
        $plant = Plant::factory()->create();
        $inCharge = $this->plantInCharge($plant);
        $product = Product::factory()->create();

        $this->actingAs($inCharge)
            ->post(route('plant.product-requests.store'), [
                'requester_name' => 'Site Supervisor',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'item_name' => $product->name,
                        'quantity' => 2,
                        'priority' => 'urgent',
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('product_requests', [
            'plant_id' => $plant->id,
            'requested_by' => $inCharge->id,
            'status' => RequestStatus::Submitted->value,
        ]);

        $this->assertDatabaseHas('product_request_items', [
            'product_id' => $product->id,
            'priority' => 'urgent',
        ]);
    }

    public function test_plant_in_charge_can_create_product_request_with_decimal_quantity(): void
    {
        $plant = Plant::factory()->create();
        $inCharge = $this->plantInCharge($plant);
        $product = Product::factory()->create();

        $this->actingAs($inCharge)
            ->get(route('plant.product-requests.create'))
            ->assertOk()
            ->assertSee('step="0.01"', false)
            ->assertSee('min="0.01"', false);

        $this->actingAs($inCharge)
            ->post(route('plant.product-requests.store'), [
                'requester_name' => 'Site Supervisor',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'item_name' => $product->name,
                        'quantity' => 2.5,
                        'priority' => 'medium',
                    ],
                ],
            ])
            ->assertRedirect();

        $item = \App\Models\ProductRequestItem::where('product_id', $product->id)->first();
        $this->assertNotNull($item);
        $this->assertEquals(2.5, (float) $item->quantity);
    }

    public function test_site_incharge_creates_request_with_priority_and_purchaser_sees_it(): void
    {
        $plant = Plant::factory()->create();
        $inCharge = $this->plantInCharge($plant);
        $purchaser = $this->purchaser();
        $product = Product::factory()->create();

        $this->actingAs($inCharge)
            ->post(route('plant.product-requests.store'), [
                'requester_name' => 'Site Incharge',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'item_name' => $product->name,
                        'quantity' => 10,
                        'priority' => 'high',
                    ],
                ],
            ])
            ->assertRedirect();

        $this->actingAs($purchaser)
            ->get(route('plant.product-requests.index'))
            ->assertOk()
            ->assertSee('High')
            ->assertSee($product->name);
    }

    public function test_plant_in_charge_can_create_product_request_with_custom_item_name(): void
    {
        $plant = Plant::factory()->create();
        $inCharge = $this->plantInCharge($plant);
        $category = \App\Models\Category::factory()->create();
        $subCategory = \App\Models\SubCategory::create([
            'category_id' => $category->id,
            'name' => 'Cement',
            'slug' => 'cement-test',
            'is_active' => true,
        ]);

        $this->actingAs($inCharge)
            ->post(route('plant.product-requests.store'), [
                'requester_name' => 'Site Supervisor',
                'items' => [
                    [
                        'item_name' => 'Custom Cement Bag',
                        'sub_category_id' => $subCategory->id,
                        'size' => '50kg',
                        'unit' => 'bag',
                        'quantity' => 3,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('product_request_items', [
            'item_name' => 'Custom Cement Bag',
            'product_id' => null,
            'size' => '50kg',
            'unit' => 'bag',
            'sub_category_id' => $subCategory->id,
            'quantity' => 3,
        ]);
    }

    public function test_purchaser_can_access_suppliers(): void
    {
        $plant = Plant::factory()->create();
        $purchaser = $this->purchaser($plant);

        $this->actingAs($purchaser)->get(route('plant.suppliers.index'))->assertOk();
    }

    public function test_login_redirects_plant_user_to_plant_dashboard(): void
    {
        $plant = Plant::factory()->create();
        $user = $this->plantInCharge($plant);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('plant.dashboard', absolute: false));
    }

    public function test_plant_purchase_requests_page_is_removed(): void
    {
        $plant = Plant::factory()->create();
        $inCharge = $this->plantInCharge($plant);
        $purchaser = $this->purchaser();

        $this->actingAs($inCharge)->get('/plant/purchase-requests')->assertNotFound();
        $this->actingAs($purchaser)->get('/plant/purchase-requests')->assertNotFound();
    }

    public function test_plant_in_charge_can_access_purchases(): void
    {
        $plant = Plant::factory()->create();
        $inCharge = $this->plantInCharge($plant);

        $this->actingAs($inCharge)->get(route('plant.purchases.index'))
            ->assertStatus(200)
            ->assertSee('Purchases', false)
            ->assertDontSee('Print', false)
            ->assertDontSee('Receive', false);
    }

    public function test_plant_in_charge_can_receive_purchase(): void
    {
        $plant = Plant::factory()->create();
        $inCharge = $this->plantInCharge($plant);
        $supplier = \App\Models\Supplier::factory()->create();
        $product = Product::factory()->create();
        $purchase = \App\Models\Purchase::create([
            'plant_id' => $plant->id,
            'supplier_id' => $supplier->id,
            'reference_no' => 'PO-RECEIVE-TEST',
            'status' => \App\Enums\PurchaseStatus::Ordered,
            'subtotal' => 100,
            'grand_total' => 118,
            'ordered_at' => now(),
            'dispatched_at' => now(),
        ]);

        \App\Models\PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 100,
            'taxable_amount' => 500,
            'line_total' => 500,
        ]);

        $this->actingAs($inCharge)
            ->get(route('plant.purchases.receive', $purchase))
            ->assertOk()
            ->assertSee('PO-RECEIVE-TEST', false);
    }

    public function test_purchaser_cannot_receive_purchase(): void
    {
        $plant = Plant::factory()->create();
        $purchaser = $this->purchaser($plant);
        $supplier = \App\Models\Supplier::factory()->create();
        $purchase = \App\Models\Purchase::create([
            'plant_id' => $plant->id,
            'supplier_id' => $supplier->id,
            'reference_no' => 'PO-PURCHASER-RECEIVE',
            'status' => \App\Enums\PurchaseStatus::Ordered,
            'subtotal' => 100,
            'grand_total' => 118,
            'ordered_at' => now(),
        ]);

        $this->actingAs($purchaser)
            ->get(route('plant.purchases.receive', $purchase))
            ->assertStatus(403);
    }

    public function test_purchaser_can_print_purchase_from_index(): void
    {
        $plant = Plant::factory()->create();
        $purchaser = $this->purchaser($plant);
        $supplier = \App\Models\Supplier::factory()->create();
        $purchase = \App\Models\Purchase::create([
            'plant_id' => $plant->id,
            'supplier_id' => $supplier->id,
            'reference_no' => 'PO-PRINT-TEST',
            'status' => \App\Enums\PurchaseStatus::Ordered,
            'subtotal' => 100,
            'grand_total' => 118,
            'ordered_at' => now(),
        ]);

        $this->actingAs($purchaser)
            ->get(route('plant.purchases.index'))
            ->assertOk()
            ->assertSee('Print', false)
            ->assertSee('PO-PRINT-TEST', false)
            ->assertDontSee('Receive', false);
    }

    public function test_plant_in_charge_can_search_purchases(): void
    {
        $plant = Plant::factory()->create();
        $inCharge = $this->plantInCharge($plant);
        $supplierA = \App\Models\Supplier::factory()->create(['name' => 'Alpha Supplies']);
        $supplierB = \App\Models\Supplier::factory()->create(['name' => 'Beta Traders']);

        \App\Models\Purchase::create([
            'plant_id' => $plant->id,
            'supplier_id' => $supplierA->id,
            'reference_no' => 'PO-ALPHA-001',
            'status' => \App\Enums\PurchaseStatus::Ordered,
            'subtotal' => 100,
            'grand_total' => 118,
            'ordered_at' => now()->subDays(2),
        ]);

        \App\Models\Purchase::create([
            'plant_id' => $plant->id,
            'supplier_id' => $supplierB->id,
            'reference_no' => 'PO-BETA-002',
            'status' => \App\Enums\PurchaseStatus::Delivered,
            'subtotal' => 200,
            'grand_total' => 236,
            'ordered_at' => now()->subDays(5),
        ]);

        $response = $this->actingAs($inCharge)
            ->get(route('plant.purchases.index', ['search' => 'Alpha']))
            ->assertStatus(200);

        $response->assertViewHas('purchases', function ($purchases) {
            return $purchases->count() === 1
                && $purchases->first()->reference_no === 'PO-ALPHA-001';
        });
    }

    public function test_plant_in_charge_can_view_purchase_products(): void
    {
        $plant = Plant::factory()->create();
        $inCharge = $this->plantInCharge($plant);
        $supplier = \App\Models\Supplier::factory()->create();
        $product = Product::factory()->create(['name' => 'Hydraulic Oil 68']);

        $purchase = \App\Models\Purchase::create([
            'plant_id' => $plant->id,
            'supplier_id' => $supplier->id,
            'reference_no' => 'PO-PRODUCT-VIEW',
            'status' => \App\Enums\PurchaseStatus::Ordered,
            'subtotal' => 100,
            'grand_total' => 118,
            'ordered_at' => now(),
        ]);

        \App\Models\PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 100,
        ]);

        $this->actingAs($inCharge)
            ->get(route('plant.purchases.show', $purchase))
            ->assertStatus(200)
            ->assertSee('Hydraulic Oil 68', false)
            ->assertSee('PO-PRODUCT-VIEW', false)
            ->assertDontSee('Generate PDF', false);

        $this->actingAs($inCharge)
            ->get(route('plant.purchases.pdf', [
                'purchase' => $purchase,
                'company_name' => 'Test Co',
            ]))
            ->assertForbidden();

        $this->actingAs($inCharge)
            ->get(route('plant.purchases.index', ['search' => 'Hydraulic']))
            ->assertStatus(200)
            ->assertSee('Hydraulic Oil 68', false)
            ->assertDontSee('>Print<', false);
    }

    public function test_plant_in_charge_receives_notification_when_stock_updated(): void
    {
        $plant = Plant::factory()->create();
        $inCharge = $this->plantInCharge($plant);
        $purchaser = $this->purchaser($plant);
        $product = Product::factory()->create(['name' => 'Notify Test Product']);

        app(\App\Services\StockService::class)->recordIn(
            $plant->id,
            $product->id,
            12,
            $purchaser->id,
            \App\Enums\StockReferenceType::StockIn,
            null,
            ['notes' => 'Test stock in']
        );

        $notification = $inCharge->fresh()->notifications->first();

        $this->assertNotNull($notification);
        $this->assertSame('stock_updated', $notification->data['type'] ?? null);
        $this->assertStringContainsString('Notify Test Product', $notification->data['body'] ?? '');
        $this->assertStringContainsString('/plant/stocks?product_id='.$product->id, $notification->data['url'] ?? '');
    }

    public function test_notification_click_redirects_to_current_host_stocks_page(): void
    {
        $plant = Plant::factory()->create();
        $inCharge = $this->plantInCharge($plant);
        $product = Product::factory()->create(['name' => 'Redirect Test Product']);

        $inCharge->notify(new \App\Notifications\ErpDatabaseNotification(
            type: 'stock_updated',
            title: 'Stock updated',
            body: 'Test',
            url: 'http://127.0.0.1:8000/plant/stocks?product_id='.$product->id,
            plantId: $plant->id,
        ));

        $notification = $inCharge->fresh()->notifications->first();

        $this->actingAs($inCharge)
            ->post(route('plant.notifications.read', $notification->id))
            ->assertRedirect('/plant/stocks?product_id='.$product->id);
    }

    public function test_stocks_page_shows_filtered_product_by_id(): void
    {
        $plant = Plant::factory()->create();
        $inCharge = $this->plantInCharge($plant);
        $product = Product::factory()->create(['name' => 'Filtered Stock Product']);

        \App\Models\StockBalance::create([
            'plant_id' => $plant->id,
            'product_id' => $product->id,
            'quantity' => 25,
        ]);

        $this->actingAs($inCharge)
            ->get(route('plant.stocks.index', ['product_id' => $product->id]))
            ->assertOk()
            ->assertSee('Filtered Stock Product', false)
            ->assertSee('25', false);
    }

    public function test_plant_in_charge_can_access_stocks(): void
    {
        $plant = Plant::factory()->create();
        $inCharge = $this->plantInCharge($plant);

        $this->actingAs($inCharge)->get(route('plant.stocks.index'))
            ->assertStatus(200);
    }

    public function test_purchaser_can_access_stocks(): void
    {
        $plant = Plant::factory()->create();
        $purchaser = $this->purchaser($plant);

        $this->actingAs($purchaser)->get(route('plant.stocks.index'))
            ->assertStatus(200);
    }

    public function test_plant_user_can_search_stocks(): void
    {
        $plant = Plant::factory()->create();
        $inCharge = $this->plantInCharge($plant);
        $productA = Product::factory()->create(['name' => 'Searchable Widget']);
        $productB = Product::factory()->create(['name' => 'Unrelated Item']);

        \App\Models\StockBalance::create([
            'plant_id' => $plant->id,
            'product_id' => $productA->id,
            'quantity' => 10,
        ]);

        \App\Models\StockBalance::create([
            'plant_id' => $plant->id,
            'product_id' => $productB->id,
            'quantity' => 20,
        ]);

        $response = $this->actingAs($inCharge)->get(route('plant.stocks.index', ['search' => 'Searchable']))
            ->assertStatus(200);
 
        $response->assertViewHas('balances', function ($balances) use ($productA, $productB) {
            return $balances->contains('product_id', $productA->id) 
                && !$balances->contains('product_id', $productB->id);
        });
    }

    public function test_plant_in_charge_stock_filters_are_scoped_to_their_plant(): void
    {
        $plant = Plant::factory()->create();
        $otherPlant = Plant::factory()->create();
        $inCharge = $this->plantInCharge($plant);
        $ownCategory = \App\Models\Category::factory()->create(['name' => 'Own Category']);
        $otherCategory = \App\Models\Category::factory()->create(['name' => 'Other Category']);
        $ownProduct = Product::factory()->create([
            'name' => 'Own Plant Item',
            'sku' => 'OWN-SKU-1',
            'category_id' => $ownCategory->id,
        ]);
        $otherProduct = Product::factory()->create([
            'name' => 'Other Plant Item',
            'sku' => 'OTHER-SKU-1',
            'category_id' => $otherCategory->id,
        ]);

        \App\Models\StockBalance::create([
            'plant_id' => $plant->id,
            'product_id' => $ownProduct->id,
            'quantity' => 11,
        ]);

        \App\Models\StockBalance::create([
            'plant_id' => $otherPlant->id,
            'product_id' => $otherProduct->id,
            'quantity' => 99,
        ]);

        $response = $this->actingAs($inCharge)
            ->get(route('plant.stocks.index', ['plant_id' => $otherPlant->id]))
            ->assertOk()
            ->assertSee('Own Plant Item', false)
            ->assertDontSee('Other Plant Item', false)
            ->assertSee('Own Category', false)
            ->assertDontSee('Other Category', false);

        $response->assertViewHas('balances', function ($balances) use ($ownProduct, $otherProduct) {
            return $balances->contains('product_id', $ownProduct->id)
                && ! $balances->contains('product_id', $otherProduct->id);
        });

        $response->assertViewHas('products', function ($products) use ($ownProduct, $otherProduct) {
            return $products->contains('id', $ownProduct->id)
                && ! $products->contains('id', $otherProduct->id);
        });

        $searchResponse = $this->actingAs($inCharge)
            ->get(route('plant.stocks.index', ['search' => 'OTHER-SKU-1']))
            ->assertOk()
            ->assertDontSee('Other Plant Item', false);

        $searchResponse->assertViewHas('balances', fn ($balances) => $balances->isEmpty());
    }

    public function test_plant_in_charge_can_filter_stocks_by_category(): void
    {
        $plant = Plant::factory()->create();
        $inCharge = $this->plantInCharge($plant);
        $categoryA = \App\Models\Category::factory()->create(['name' => 'Hyva Parts']);
        $categoryB = \App\Models\Category::factory()->create(['name' => 'JCB Parts']);
        $productA = Product::factory()->create(['name' => 'Hyva Valve', 'category_id' => $categoryA->id]);
        $productB = Product::factory()->create(['name' => 'JCB Lamp', 'category_id' => $categoryB->id]);

        \App\Models\StockBalance::create([
            'plant_id' => $plant->id,
            'product_id' => $productA->id,
            'quantity' => 4,
        ]);
        \App\Models\StockBalance::create([
            'plant_id' => $plant->id,
            'product_id' => $productB->id,
            'quantity' => 7,
        ]);

        $response = $this->actingAs($inCharge)
            ->get(route('plant.stocks.index', ['category_id' => $categoryA->id]))
            ->assertOk()
            ->assertSee('Hyva Valve', false)
            ->assertDontSee('JCB Lamp', false);

        $response->assertViewHas('products', function ($products) use ($productA, $productB) {
            return $products->contains('id', $productA->id)
                && ! $products->contains('id', $productB->id);
        });
    }
 
    public function test_plant_in_charge_cannot_edit_purchase(): void
    {
        $plant = Plant::factory()->create();
        $inCharge = $this->plantInCharge($plant);
        
        $supplier = \App\Models\Supplier::factory()->create();
        $purchase = \App\Models\Purchase::create([
            'plant_id' => $plant->id,
            'supplier_id' => $supplier->id,
            'reference_no' => 'PO-123456',
            'status' => \App\Enums\PurchaseStatus::Ordered,
            'subtotal' => 100,
            'grand_total' => 118,
        ]);
 
        $this->actingAs($inCharge)
            ->get(route('plant.purchases.edit', $purchase))
            ->assertStatus(403);
 
        $this->actingAs($inCharge)
            ->put(route('plant.purchases.update', $purchase), [
                'supplier_id' => $supplier->id,
                'items' => [],
            ])
            ->assertStatus(403);
    }
 
    public function test_purchaser_can_edit_and_update_purchase(): void
    {
        $plant = Plant::factory()->create();
        $purchaser = $this->purchaser($plant);
        
        $supplierA = \App\Models\Supplier::factory()->create();
        $supplierB = \App\Models\Supplier::factory()->create();
        $product = Product::factory()->create();
 
        $purchase = \App\Models\Purchase::create([
            'plant_id' => $plant->id,
            'supplier_id' => $supplierA->id,
            'reference_no' => 'PO-123456',
            'status' => \App\Enums\PurchaseStatus::Ordered,
            'subtotal' => 100,
            'grand_total' => 118,
            'is_interstate' => false,
        ]);
 
        $item = \App\Models\PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 50,
            'gst_rate' => 18,
            'taxable_amount' => 100,
            'cgst_amount' => 9,
            'sgst_amount' => 9,
            'igst_amount' => 0,
            'line_total' => 118,
        ]);
 
        // Access edit form
        $this->actingAs($purchaser)
            ->get(route('plant.purchases.edit', $purchase))
            ->assertStatus(200)
            ->assertSee($purchase->reference_no)
            ->assertSee($supplierA->name);
 
        // Update purchase details (change to supplierB, unit price 60, toggle interstate)
        $response = $this->actingAs($purchaser)
            ->put(route('plant.purchases.update', $purchase), [
                'supplier_id' => $supplierB->id,
                'is_interstate' => 1,
                'supplier_invoice_no' => 'INV-999',
                'supplier_invoice_date' => '2026-06-18',
                'notes' => 'Some update notes',
                'items' => [
                    [
                        'id' => $item->id,
                        'unit_price' => 60,
                    ]
                ],
            ]);
 
        $response->assertRedirect(route('plant.purchases.index'));
        
        // Assert updated values in DB
        // New calculations: qty=2, unit_price=60, is_interstate=true, gst=18
        // taxable = 2 * 60 = 120
        // igst = 120 * 18/100 = 21.6
        // grand total = 141.6
        $this->assertDatabaseHas('purchases', [
            'id' => $purchase->id,
            'supplier_id' => $supplierB->id,
            'is_interstate' => 1,
            'supplier_invoice_no' => 'INV-999',
            'subtotal' => 120,
            'total_igst' => 21.6,
            'total_cgst' => 0,
            'total_sgst' => 0,
            'total_gst' => 21.6,
            'grand_total' => 141.6,
            'notes' => 'Some update notes',
        ]);
        $this->assertEquals('2026-06-18', $purchase->refresh()->supplier_invoice_date->format('Y-m-d'));
 
        $this->assertDatabaseHas('purchase_items', [
            'id' => $item->id,
            'unit_price' => 60,
            'taxable_amount' => 120,
            'igst_amount' => 21.6,
            'cgst_amount' => 0,
            'sgst_amount' => 0,
            'line_total' => 141.6,
        ]);
    }
 
    public function test_plant_in_charge_cannot_edit_product_request(): void
    {
        $plant = Plant::factory()->create();
        $inCharge = $this->plantInCharge($plant);
        $productRequest = \App\Models\ProductRequest::create([
            'plant_id' => $plant->id,
            'requested_by' => $inCharge->id,
            'requester_name' => $inCharge->name,
            'status' => \App\Enums\RequestStatus::Submitted,
        ]);
 
        $this->actingAs($inCharge)
            ->get(route('plant.product-requests.edit', $productRequest))
            ->assertStatus(403);
    }
 
    public function test_purchaser_can_edit_and_update_product_request(): void
    {
        $plant = Plant::factory()->create();
        $inCharge = $this->plantInCharge($plant);
        $purchaser = $this->purchaser($plant);
        $product = Product::factory()->create();
 
        $productRequest = \App\Models\ProductRequest::create([
            'plant_id' => $plant->id,
            'requested_by' => $inCharge->id,
            'requester_name' => $inCharge->name,
            'status' => \App\Enums\RequestStatus::Submitted,
            'remarks' => 'Original Remarks',
        ]);
 
        $item = \App\Models\ProductRequestItem::create([
            'product_request_id' => $productRequest->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'remarks' => 'Original Item Remarks',
        ]);
 
        $this->actingAs($purchaser)
            ->get(route('plant.product-requests.edit', $productRequest))
            ->assertStatus(200)
            ->assertSee($product->name);
 
        $response = $this->actingAs($purchaser)
            ->put(route('plant.product-requests.update', $productRequest), [
                'remarks' => 'Updated Remarks',
                'items' => [
                    [
                        'id' => $item->id,
                        'quantity' => 20,
                        'remarks' => 'Updated Item Remarks',
                    ]
                ]
            ]);
 
        $response->assertRedirect(route('plant.product-requests.show', $productRequest));
 
        $this->assertDatabaseHas('product_requests', [
            'id' => $productRequest->id,
            'remarks' => 'Updated Remarks',
        ]);
 
        $this->assertDatabaseHas('product_request_items', [
            'id' => $item->id,
            'quantity' => 20,
            'remarks' => 'Updated Item Remarks',
        ]);
    }

    public function test_purchaser_sees_edit_pr_button_on_add_quotation_tab(): void
    {
        $plant = Plant::factory()->create();
        $inCharge = $this->plantInCharge($plant);
        $purchaser = $this->purchaser($plant);
        $product = Product::factory()->create();

        $productRequest = \App\Models\ProductRequest::create([
            'plant_id' => $plant->id,
            'requested_by' => $inCharge->id,
            'requester_name' => $inCharge->name,
            'status' => \App\Enums\RequestStatus::Submitted,
        ]);

        $item = \App\Models\ProductRequestItem::create([
            'product_request_id' => $productRequest->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'exported_at' => now(),
        ]);

        $this->actingAs($purchaser)
            ->get(route('plant.product-requests.index', ['status' => 'add_quotation']))
            ->assertStatus(200)
            ->assertSee('Edit');

        $this->actingAs($purchaser)
            ->get(route('plant.product-requests.edit', [$productRequest, 'item_ids' => [$item->id]]))
            ->assertStatus(200)
            ->assertSee($product->name);
    }

    public function test_purchaser_can_edit_specific_inquiry_group_items(): void
    {
        $plant = Plant::factory()->create();
        $inCharge = $this->plantInCharge($plant);
        $purchaser = $this->purchaser($plant);
        $productA = Product::factory()->create(['name' => 'Product Alpha']);
        $productB = Product::factory()->create(['name' => 'Product Beta']);
        $supplier = \App\Models\Supplier::factory()->create();

        $productRequest = \App\Models\ProductRequest::create([
            'plant_id' => $plant->id,
            'requested_by' => $inCharge->id,
            'requester_name' => $inCharge->name,
            'status' => \App\Enums\RequestStatus::Submitted,
        ]);

        $itemA = \App\Models\ProductRequestItem::create([
            'product_request_id' => $productRequest->id,
            'product_id' => $productA->id,
            'supplier_id' => $supplier->id,
            'quantity' => 10,
            'exported_at' => now(),
        ]);

        $itemB = \App\Models\ProductRequestItem::create([
            'product_request_id' => $productRequest->id,
            'product_id' => $productB->id,
            'quantity' => 5,
        ]);

        // When editing Inquiry for itemA group
        $this->actingAs($purchaser)
            ->get(route('plant.product-requests.edit', [$productRequest, 'item_ids' => [$itemA->id]]))
            ->assertStatus(200)
            ->assertSee('Product Alpha');
    }

    public function test_purchaser_can_access_create_purchase_page(): void
    {
        $plant = Plant::factory()->create();
        $purchaser = $this->purchaser($plant);
        $product = Product::factory()->create();
        $supplier = \App\Models\Supplier::factory()->create();

        $purchaseRequest = \App\Models\PurchaseRequest::create([
            'plant_id' => $plant->id,
            'requested_by' => $purchaser->id,
            'status' => RequestStatus::Submitted,
            'plant_status' => RequestStatus::Submitted,
        ]);

        \App\Models\PurchaseRequestItem::create([
            'purchase_request_id' => $purchaseRequest->id,
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'quantity' => 5,
            'unit_price' => 100,
        ]);

        $this->actingAs($purchaser)
            ->get(route('plant.purchases.create', ['purchase_request_id' => $purchaseRequest->id]))
            ->assertOk()
            ->assertSee('Create Quotation')
            ->assertSee($product->name);
    }

    public function test_purchaser_can_create_po_from_purchase_request_with_custom_item(): void
    {
        $plant = Plant::factory()->create();
        $purchaser = $this->purchaser($plant);
        $supplier = \App\Models\Supplier::factory()->create();
        $category = \App\Models\Category::factory()->create();
        $subCategory = \App\Models\SubCategory::create([
            'category_id' => $category->id,
            'name' => 'Steel',
            'slug' => 'steel-test',
            'is_active' => true,
        ]);
        $catalogProduct = Product::factory()->create();

        $purchaseRequest = \App\Models\PurchaseRequest::create([
            'plant_id' => $plant->id,
            'requested_by' => $purchaser->id,
            'status' => RequestStatus::Submitted,
            'plant_status' => RequestStatus::Submitted,
        ]);

        \App\Models\PurchaseRequestItem::create([
            'purchase_request_id' => $purchaseRequest->id,
            'product_id' => null,
            'item_name' => 'Custom TMT Bar',
            'size' => '12mm',
            'sub_category_id' => $subCategory->id,
            'supplier_id' => $supplier->id,
            'quantity' => 4,
            'unit_price' => 0,
        ]);

        $this->actingAs($purchaser)
            ->get(route('plant.purchases.create', ['purchase_request_id' => $purchaseRequest->id]))
            ->assertOk()
            ->assertSee('Item Name')
            ->assertSee('Search item name or SKU');

        $this->actingAs($purchaser)
            ->post(route('plant.purchases.store'), [
                'plant_id' => $plant->id,
                'purchase_request_id' => $purchaseRequest->id,
                'is_interstate' => 0,
                'items' => [
                    [
                        'product_id' => $catalogProduct->id,
                        'supplier_id' => $supplier->id,
                        'quantity' => 4,
                        'unit_price' => 500,
                        'gst_rate' => 18,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('purchase_items', [
            'product_id' => $catalogProduct->id,
            'quantity' => 4,
        ]);
    }

    public function test_linked_catalog_product_appears_on_existing_purchase_request_po_form(): void
    {
        $plant = Plant::factory()->create();
        $purchaser = $this->purchaser();
        $category = \App\Models\Category::factory()->create();
        $unit = \App\Models\Unit::factory()->create();
        $productRequest = \App\Models\ProductRequest::create([
            'plant_id' => $plant->id,
            'requested_by' => $purchaser->id,
            'requester_name' => 'Site',
            'status' => RequestStatus::Submitted,
        ]);
        $item = \App\Models\ProductRequestItem::create([
            'product_request_id' => $productRequest->id,
            'item_name' => 'Custom TMT Bar',
            'size' => '12mm',
            'quantity' => 4,
        ]);

        $this->actingAs($purchaser)
            ->post(route('plant.products.store'), [
                'product_request_id' => $productRequest->id,
                'product_request_item_id' => $item->id,
                'products' => [
                    [
                        'name' => 'Linked TMT Bar 12mm',
                        'size' => '12mm',
                        'category_id' => $category->id,
                        'unit_id' => $unit->id,
                        'reorder_level' => 10,
                        'is_active' => '1',
                    ],
                ],
            ])
            ->assertRedirect(route('plant.product-requests.show', $productRequest));

        $catalogProduct = Product::where('name', 'Linked TMT Bar 12mm')->first();
        $this->assertNotNull($catalogProduct);

        $this->actingAs($purchaser)
            ->post(route('plant.product-requests.bulk-convert'), [
                'product_request_item_ids' => [$item->id],
            ])
            ->assertRedirect();

        $purchaseRequest = \App\Models\PurchaseRequest::first();
        $this->assertNotNull($purchaseRequest);
        $this->assertDatabaseHas('purchase_request_items', [
            'purchase_request_id' => $purchaseRequest->id,
            'product_request_item_id' => $item->id,
            'product_id' => $catalogProduct->id,
        ]);

        $this->actingAs($purchaser)
            ->get(route('plant.purchases.create', ['purchase_request_id' => $purchaseRequest->id]))
            ->assertOk()
            ->assertSee('Linked TMT Bar 12mm');
    }

    public function test_purchaser_receives_notification_when_product_request_submitted(): void
    {
        $plant = Plant::factory()->create();
        $inCharge = $this->plantInCharge($plant);
        $purchaser = $this->purchaser($plant);
        $product = Product::factory()->create();

        $initialCount = $purchaser->notifications()->count();

        $this->actingAs($inCharge)
            ->post(route('plant.product-requests.store'), [
                'requester_name' => 'Site Supervisor',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'item_name' => $product->name,
                        'quantity' => 10,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertEquals($initialCount + 1, $purchaser->fresh()->notifications()->count());
        $notification = $purchaser->fresh()->notifications()->first();
        $this->assertEquals('product_request_new', $notification->data['type']);
    }

    public function test_purchaser_can_filter_product_requests_by_plant(): void
    {
        $plantA = Plant::factory()->create(['name' => 'Piska', 'code' => 'PISKA']);
        $plantB = Plant::factory()->create(['name' => 'Other Site', 'code' => 'OTHER']);
        $purchaser = $this->purchaser();
        $product = Product::factory()->create();

        $requestA = \App\Models\ProductRequest::create([
            'plant_id' => $plantA->id,
            'requested_by' => $purchaser->id,
            'requester_name' => 'A',
            'status' => RequestStatus::Submitted,
        ]);
        \App\Models\ProductRequestItem::create([
            'product_request_id' => $requestA->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $requestB = \App\Models\ProductRequest::create([
            'plant_id' => $plantB->id,
            'requested_by' => $purchaser->id,
            'requester_name' => 'B',
            'status' => RequestStatus::Submitted,
        ]);
        \App\Models\ProductRequestItem::create([
            'product_request_id' => $requestB->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $this->actingAs($purchaser)
            ->get(route('plant.product-requests.index', ['plant_id' => $plantA->id]))
            ->assertOk()
            ->assertSee('Piska')
            ->assertSee('#'.$requestA->id)
            ->assertDontSee('#'.$requestB->id);
    }

    public function test_purchaser_can_bulk_convert_same_plant_requests_for_quotation(): void
    {
        $plant = Plant::factory()->create(['name' => 'Piska', 'code' => 'PISKA']);
        $purchaser = $this->purchaser();
        $product = Product::factory()->create();

        $requests = collect([1, 2])->map(function () use ($plant, $purchaser, $product) {
            $pr = \App\Models\ProductRequest::create([
                'plant_id' => $plant->id,
                'requested_by' => $purchaser->id,
                'requester_name' => 'Site',
                'status' => RequestStatus::Submitted,
            ]);
            \App\Models\ProductRequestItem::create([
                'product_request_id' => $pr->id,
                'product_id' => $product->id,
                'quantity' => 2,
            ]);

            return $pr;
        });

        $response = $this->actingAs($purchaser)
            ->post(route('plant.product-requests.bulk-convert'), [
                'product_request_item_ids' => \App\Models\ProductRequestItem::whereIn('product_request_id', $requests->pluck('id'))->pluck('id')->all(),
            ]);

        $purchaseRequest = \App\Models\PurchaseRequest::first();
        $this->assertNotNull($purchaseRequest);
        $response->assertRedirect(route('plant.purchases.create', [
            'inquiry_id' => $requests->pluck('id')->implode(','),
        ]));

        $this->assertEquals(2, $purchaseRequest->items()->count());
        $this->assertEquals(
            [RequestStatus::Completed->value, RequestStatus::Completed->value],
            $requests->map(fn ($r) => $r->fresh()->status->value)->all()
        );
    }

    public function test_bulk_convert_rejects_requests_from_different_plants(): void
    {
        $plantA = Plant::factory()->create();
        $plantB = Plant::factory()->create();
        $purchaser = $this->purchaser();
        $product = Product::factory()->create();

        $requestA = \App\Models\ProductRequest::create([
            'plant_id' => $plantA->id,
            'requested_by' => $purchaser->id,
            'requester_name' => 'A',
            'status' => RequestStatus::Submitted,
        ]);
        $itemA = \App\Models\ProductRequestItem::create([
            'product_request_id' => $requestA->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $requestB = \App\Models\ProductRequest::create([
            'plant_id' => $plantB->id,
            'requested_by' => $purchaser->id,
            'requester_name' => 'B',
            'status' => RequestStatus::Submitted,
        ]);
        $itemB = \App\Models\ProductRequestItem::create([
            'product_request_id' => $requestB->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $this->actingAs($purchaser)
            ->from(route('plant.product-requests.index'))
            ->post(route('plant.product-requests.bulk-convert'), [
                'product_request_item_ids' => [$itemA->id, $itemB->id],
            ])
            ->assertRedirect(route('plant.product-requests.index'))
            ->assertSessionHas('error');
    }

    public function test_purchaser_can_convert_selected_products_only(): void
    {
        $plant = Plant::factory()->create();
        $purchaser = $this->purchaser();
        $productA = Product::factory()->create(['name' => 'Cement']);
        $productB = Product::factory()->create(['name' => 'Steel']);

        $productRequest = \App\Models\ProductRequest::create([
            'plant_id' => $plant->id,
            'requested_by' => $purchaser->id,
            'requester_name' => 'Site',
            'status' => RequestStatus::Submitted,
        ]);
        $itemA = \App\Models\ProductRequestItem::create([
            'product_request_id' => $productRequest->id,
            'product_id' => $productA->id,
            'quantity' => 5,
        ]);
        $itemB = \App\Models\ProductRequestItem::create([
            'product_request_id' => $productRequest->id,
            'product_id' => $productB->id,
            'quantity' => 8,
        ]);

        $this->actingAs($purchaser)
            ->post(route('plant.product-requests.bulk-convert'), [
                'product_request_item_ids' => [$itemA->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('purchase_request_items', [
            'product_request_item_id' => $itemA->id,
        ]);
        $this->assertDatabaseMissing('purchase_request_items', [
            'product_request_item_id' => $itemB->id,
        ]);
        $this->assertEquals(RequestStatus::InProgress, $productRequest->fresh()->status);
    }

    public function test_purchaser_can_merge_products_from_different_purchase_requests(): void
    {
        $plant = Plant::factory()->create();
        $purchaser = $this->purchaser();
        $productA = Product::factory()->create(['name' => 'Bolt']);
        $productB = Product::factory()->create(['name' => 'Nut']);

        $productRequest = \App\Models\ProductRequest::create([
            'plant_id' => $plant->id,
            'requested_by' => $purchaser->id,
            'requester_name' => 'Site',
            'status' => RequestStatus::Submitted,
        ]);
        $itemA = \App\Models\ProductRequestItem::create([
            'product_request_id' => $productRequest->id,
            'product_id' => $productA->id,
            'quantity' => 2,
        ]);
        $itemB = \App\Models\ProductRequestItem::create([
            'product_request_id' => $productRequest->id,
            'product_id' => $productB->id,
            'quantity' => 3,
        ]);

        $this->actingAs($purchaser)
            ->post(route('plant.product-requests.bulk-convert'), [
                'product_request_item_ids' => [$itemA->id],
            ])
            ->assertRedirect();

        $this->actingAs($purchaser)
            ->post(route('plant.product-requests.bulk-convert'), [
                'product_request_item_ids' => [$itemB->id],
            ])
            ->assertRedirect();

        $this->assertEquals(2, \App\Models\PurchaseRequest::count());

        $response = $this->actingAs($purchaser)
            ->post(route('plant.product-requests.bulk-convert'), [
                'product_request_item_ids' => [$itemA->id, $itemB->id],
            ]);

        $this->assertEquals(1, \App\Models\PurchaseRequest::count());
        $merged = \App\Models\PurchaseRequest::first();
        $this->assertEquals(2, $merged->items()->count());
        $response->assertRedirect(route('plant.purchases.create', [
            'inquiry_id' => $itemA->product_request_id,
        ]));
    }

    public function test_purchaser_can_export_selected_products_only(): void
    {
        $plant = Plant::factory()->create(['name' => 'Piska']);
        $purchaser = $this->purchaser();
        $productA = Product::factory()->create(['name' => 'UniqueCementBag']);
        $productB = Product::factory()->create(['name' => 'UniqueSteelRod']);

        $productRequest = \App\Models\ProductRequest::create([
            'plant_id' => $plant->id,
            'requested_by' => $purchaser->id,
            'requester_name' => 'Site',
            'status' => RequestStatus::Submitted,
        ]);
        $itemA = \App\Models\ProductRequestItem::create([
            'product_request_id' => $productRequest->id,
            'product_id' => $productA->id,
            'quantity' => 5,
        ]);
        \App\Models\ProductRequestItem::create([
            'product_request_id' => $productRequest->id,
            'product_id' => $productB->id,
            'quantity' => 8,
        ]);

        $supplier = \App\Models\Supplier::factory()->create();

        $response = $this->actingAs($purchaser)
            ->post(route('plant.product-requests.bulk-export-supplier'), [
                'product_request_item_ids' => [$itemA->id],
                'supplier_id' => $supplier->id,
            ]);

        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('UniqueCementBag', $csv);
        $this->assertStringNotContainsString('UniqueSteelRod', $csv);
    }

    public function test_purchaser_product_request_list_shows_workflow_status(): void
    {
        $plant = Plant::factory()->create();
        $purchaser = $this->purchaser();
        $supplier = \App\Models\Supplier::factory()->create();
        $productPending = Product::factory()->create(['name' => 'PendingItem']);
        $productQuoted = Product::factory()->create(['name' => 'QuotedItem']);
        $productOrdered = Product::factory()->create(['name' => 'OrderedItem']);
        $productDispatched = Product::factory()->create(['name' => 'DispatchedItem']);

        $pendingRequest = \App\Models\ProductRequest::create([
            'plant_id' => $plant->id,
            'requested_by' => $purchaser->id,
            'requester_name' => 'Site',
            'status' => RequestStatus::Submitted,
        ]);
        \App\Models\ProductRequestItem::create([
            'product_request_id' => $pendingRequest->id,
            'product_id' => $productPending->id,
            'quantity' => 1,
        ]);

        $productExported = Product::factory()->create(['name' => 'ExportedItem']);
        $exportedRequest = \App\Models\ProductRequest::create([
            'plant_id' => $plant->id,
            'requested_by' => $purchaser->id,
            'requester_name' => 'Site',
            'status' => RequestStatus::Submitted,
        ]);
        $exportedItem = \App\Models\ProductRequestItem::create([
            'product_request_id' => $exportedRequest->id,
            'product_id' => $productExported->id,
            'quantity' => 1,
            'exported_at' => now(),
        ]);

        $quotedRequest = \App\Models\ProductRequest::create([
            'plant_id' => $plant->id,
            'requested_by' => $purchaser->id,
            'requester_name' => 'Site',
            'status' => RequestStatus::Submitted,
        ]);
        $quotedItem = \App\Models\ProductRequestItem::create([
            'product_request_id' => $quotedRequest->id,
            'product_id' => $productQuoted->id,
            'quantity' => 1,
            'exported_at' => now(),
        ]);
        $quotedPurchaseRequest = \App\Models\PurchaseRequest::create([
            'plant_id' => $plant->id,
            'product_request_id' => $quotedRequest->id,
            'requested_by' => $purchaser->id,
            'plant_status' => RequestStatus::Completed,
            'status' => RequestStatus::Completed,
        ]);
        \App\Models\PurchaseRequestItem::create([
            'purchase_request_id' => $quotedPurchaseRequest->id,
            'product_request_item_id' => $quotedItem->id,
            'product_id' => $productQuoted->id,
            'quantity' => 1,
            'unit_price' => 10,
        ]);
        \App\Models\Purchase::create([
            'plant_id' => $plant->id,
            'supplier_id' => $supplier->id,
            'purchase_request_id' => $quotedPurchaseRequest->id,
            'document_type' => \App\Enums\PurchaseDocumentType::Quotation,
            'reference_no' => 'QUOTE-TEST-1',
            'status' => \App\Enums\PurchaseStatus::Ordered,
            'subtotal' => 10,
            'grand_total' => 10,
        ]);

        $orderedRequest = \App\Models\ProductRequest::create([
            'plant_id' => $plant->id,
            'requested_by' => $purchaser->id,
            'requester_name' => 'Site',
            'status' => RequestStatus::Completed,
        ]);
        $orderedItem = \App\Models\ProductRequestItem::create([
            'product_request_id' => $orderedRequest->id,
            'product_id' => $productOrdered->id,
            'quantity' => 1,
        ]);
        $orderedPurchaseRequest = \App\Models\PurchaseRequest::create([
            'plant_id' => $plant->id,
            'product_request_id' => $orderedRequest->id,
            'requested_by' => $purchaser->id,
            'plant_status' => RequestStatus::Completed,
            'status' => RequestStatus::Completed,
        ]);
        \App\Models\PurchaseRequestItem::create([
            'purchase_request_id' => $orderedPurchaseRequest->id,
            'product_request_item_id' => $orderedItem->id,
            'product_id' => $productOrdered->id,
            'quantity' => 1,
            'unit_price' => 10,
        ]);
        $orderedPurchase = \App\Models\Purchase::create([
            'plant_id' => $plant->id,
            'supplier_id' => $supplier->id,
            'purchase_request_id' => $orderedPurchaseRequest->id,
            'reference_no' => 'PO-ORDERED-1',
            'status' => \App\Enums\PurchaseStatus::Ordered,
            'subtotal' => 10,
            'grand_total' => 10,
        ]);
        \App\Models\PurchaseItem::create([
            'purchase_id' => $orderedPurchase->id,
            'product_id' => $productOrdered->id,
            'quantity' => 1,
            'unit_price' => 10,
            'line_total' => 10,
        ]);

        $dispatchedRequest = \App\Models\ProductRequest::create([
            'plant_id' => $plant->id,
            'requested_by' => $purchaser->id,
            'requester_name' => 'Site',
            'status' => RequestStatus::Completed,
        ]);
        $dispatchedItem = \App\Models\ProductRequestItem::create([
            'product_request_id' => $dispatchedRequest->id,
            'product_id' => $productDispatched->id,
            'quantity' => 1,
        ]);
        $dispatchedPurchaseRequest = \App\Models\PurchaseRequest::create([
            'plant_id' => $plant->id,
            'product_request_id' => $dispatchedRequest->id,
            'requested_by' => $purchaser->id,
            'plant_status' => RequestStatus::Completed,
            'status' => RequestStatus::Completed,
        ]);
        \App\Models\PurchaseRequestItem::create([
            'purchase_request_id' => $dispatchedPurchaseRequest->id,
            'product_request_item_id' => $dispatchedItem->id,
            'product_id' => $productDispatched->id,
            'quantity' => 1,
            'unit_price' => 10,
        ]);
        $dispatchedPurchase = \App\Models\Purchase::create([
            'plant_id' => $plant->id,
            'supplier_id' => $supplier->id,
            'purchase_request_id' => $dispatchedPurchaseRequest->id,
            'reference_no' => 'PO-DISPATCH-1',
            'status' => \App\Enums\PurchaseStatus::Ordered,
            'subtotal' => 10,
            'grand_total' => 10,
            'dispatched_at' => now(),
        ]);
        \App\Models\PurchaseItem::create([
            'purchase_id' => $dispatchedPurchase->id,
            'product_id' => $productDispatched->id,
            'quantity' => 1,
            'unit_price' => 10,
            'line_total' => 10,
        ]);

        $this->actingAs($purchaser)
            ->get(route('plant.product-requests.index'))
            ->assertOk()
            ->assertSee('PENDING')
            ->assertViewHas('items', function ($items) use ($productPending, $productOrdered, $productDispatched) {
                $productIds = $items->pluck('product_id');

                return $productIds->contains($productPending->id)
                    && ! $productIds->contains($productOrdered->id)
                    && ! $productIds->contains($productDispatched->id);
            });

        $this->actingAs($purchaser)
            ->get(route('plant.product-requests.index', ['status' => 'all']))
            ->assertOk()
            ->assertViewHas('items', function ($items) use ($productPending, $productQuoted, $productOrdered, $productDispatched) {
                $productIds = $items->pluck('product_id');

                return $productIds->contains($productPending->id)
                    && $productIds->contains($productQuoted->id)
                    && $productIds->contains($productOrdered->id)
                    && $productIds->contains($productDispatched->id);
            });

        $this->actingAs($purchaser)
            ->get(route('plant.product-requests.index', ['status' => 'add_quotation']))
            ->assertOk()
            ->assertViewHas('items', function ($items) use ($productExported, $productQuoted, $productOrdered, $productDispatched) {
                $productIds = $items->pluck('product_id');

                return $productIds->contains($productExported->id)
                    && ! $productIds->contains($productQuoted->id)
                    && ! $productIds->contains($productOrdered->id)
                    && ! $productIds->contains($productDispatched->id);
            });

        $this->actingAs($purchaser)
            ->get(route('plant.product-requests.index', ['status' => 'create_po']))
            ->assertOk()
            ->assertViewHas('items', function ($items) use ($productQuoted, $productOrdered, $productDispatched) {
                $productIds = $items->pluck('product_id');

                return $productIds->contains($productQuoted->id)
                    && ! $productIds->contains($productOrdered->id)
                    && ! $productIds->contains($productDispatched->id);
            });

        $this->actingAs($purchaser)
            ->get(route('plant.product-requests.index', ['status' => 'dispatch']))
            ->assertOk()
            ->assertViewHas('items', function ($items) use ($productQuoted, $productOrdered, $productDispatched) {
                $productIds = $items->pluck('product_id');

                return $productIds->contains($productDispatched->id)
                    && $productIds->contains($productOrdered->id)
                    && ! $productIds->contains($productQuoted->id);
            });
    }

    public function test_quotation_then_po_flow_and_leftover_item_stays_pending(): void
    {
        $plant = Plant::factory()->create();
        $purchaser = $this->purchaser();
        $supplier = \App\Models\Supplier::factory()->create();
        $productA = Product::factory()->create(['name' => 'PendingPartA']);
        $productB = Product::factory()->create(['name' => 'QuotedPartB']);

        $productRequest = \App\Models\ProductRequest::create([
            'plant_id' => $plant->id,
            'requested_by' => $purchaser->id,
            'requester_name' => 'Site',
            'status' => RequestStatus::Submitted,
        ]);
        $itemA = \App\Models\ProductRequestItem::create([
            'product_request_id' => $productRequest->id,
            'product_id' => $productA->id,
            'quantity' => 2,
        ]);
        $itemB = \App\Models\ProductRequestItem::create([
            'product_request_id' => $productRequest->id,
            'product_id' => $productB->id,
            'quantity' => 3,
        ]);

        $this->actingAs($purchaser)
            ->post(route('plant.product-requests.bulk-convert'), [
                'product_request_item_ids' => [$itemB->id],
            ])
            ->assertRedirect();

        $purchaseRequest = \App\Models\PurchaseRequest::first();
        $this->assertNotNull($purchaseRequest);

        $this->actingAs($purchaser)
            ->post(route('plant.purchases.store'), [
                'plant_id' => $plant->id,
                'purchase_request_id' => $purchaseRequest->id,
                'document_type' => 'quotation',
                'is_interstate' => 0,
                'items' => [
                    [
                        'product_id' => $productB->id,
                        'supplier_id' => $supplier->id,
                        'quantity' => 3,
                        'unit_price' => 100,
                        'discount_percent' => 10,
                        'gst_rate' => 18,
                    ],
                ],
            ])
            ->assertRedirect();

        $quotation = \App\Models\Purchase::quotations()->first();
        $this->assertNotNull($quotation);
        $this->assertEquals('quotation', $quotation->document_type->value);
        $this->assertEquals(270, (float) $quotation->subtotal);
        $this->assertEquals(318.6, (float) $quotation->grand_total);

        $this->actingAs($purchaser)
            ->get(route('plant.product-requests.index', ['status' => 'pending']))
            ->assertOk()
            ->assertViewHas('items', function ($items) use ($productA, $productB) {
                $ids = $items->pluck('product_id');

                return $ids->contains($productA->id) && ! $ids->contains($productB->id);
            });

        $this->actingAs($purchaser)
            ->get(route('plant.product-requests.index', ['status' => 'create_po']))
            ->assertOk()
            ->assertViewHas('items', function ($items) use ($productA, $productB) {
                $ids = $items->pluck('product_id');

                return $ids->contains($productB->id) && ! $ids->contains($productA->id);
            });

        $this->actingAs($purchaser)
            ->get(route('plant.purchases.index'))
            ->assertOk()
            ->assertSee('Quotation Section')
            ->assertSee('PO Section')
            ->assertSee($quotation->reference_no);

        $this->actingAs($purchaser)
            ->post(route('plant.purchases.store'), [
                'plant_id' => $plant->id,
                'quotation_id' => $quotation->id,
                'is_interstate' => 0,
                'items' => [
                    [
                        'product_id' => $productB->id,
                        'supplier_id' => $supplier->id,
                        'quantity' => 3,
                        'unit_price' => 100,
                        'discount_percent' => 10,
                        'gst_rate' => 18,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertEquals(1, \App\Models\Purchase::purchaseOrders()->count());
        $po = \App\Models\Purchase::purchaseOrders()->first();
        $this->assertEquals($quotation->id, $po->quotation_id);

        $this->actingAs($purchaser)
            ->get(route('plant.product-requests.index', ['status' => 'pending']))
            ->assertOk()
            ->assertViewHas('items', function ($items) use ($productA, $productB) {
                $ids = $items->pluck('product_id');

                return $ids->contains($productA->id) && ! $ids->contains($productB->id);
            });
    }

    public function test_line_discount_applies_before_gst(): void
    {
        $service = new \App\Services\PurchasePricingService;
        $line = $service->calculateLine(10, 100, 18, false, 10);

        $this->assertEquals(900, $line['taxable_amount']);
        $this->assertEquals(81, $line['cgst_amount']);
        $this->assertEquals(81, $line['sgst_amount']);
        $this->assertEquals(1062, $line['line_total']);
    }

    public function test_quotation_number_is_plant_wise_like_piska_01(): void
    {
        $plant = Plant::factory()->create(['name' => 'Piska', 'code' => 'PISKA']);
        $purchaser = $this->purchaser();
        $supplier = \App\Models\Supplier::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($purchaser)
            ->post(route('plant.purchases.store'), [
                'plant_id' => $plant->id,
                'is_interstate' => 0,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'supplier_id' => $supplier->id,
                        'quantity' => 1,
                        'unit_price' => 100,
                        'gst_rate' => 18,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('purchases', [
            'plant_id' => $plant->id,
            'reference_no' => 'piska-01',
            'supplier_invoice_no' => 'piska-01',
        ]);

        $this->actingAs($purchaser)
            ->post(route('plant.purchases.store'), [
                'plant_id' => $plant->id,
                'is_interstate' => 0,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'supplier_id' => $supplier->id,
                        'quantity' => 1,
                        'unit_price' => 50,
                        'gst_rate' => 0,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('purchases', [
            'plant_id' => $plant->id,
            'reference_no' => 'piska-02',
        ]);
    }

    public function test_creating_category_from_request_product_returns_with_form_data(): void
    {
        $plant = Plant::factory()->create();
        $purchaser = $this->purchaser();
        $productRequest = \App\Models\ProductRequest::create([
            'plant_id' => $plant->id,
            'requested_by' => $purchaser->id,
            'requester_name' => 'Site',
            'status' => RequestStatus::Submitted,
        ]);
        $item = \App\Models\ProductRequestItem::create([
            'product_request_id' => $productRequest->id,
            'item_name' => 'Custom Cement Bag',
            'size' => '50kg',
            'quantity' => 3,
        ]);

        $response = $this->actingAs($purchaser)
            ->post(route('plant.categories.store'), [
                'name' => 'Returned Category',
                'is_active' => '1',
                'redirect' => 'products.create',
                'product_request_id' => $productRequest->id,
                'product_request_item_id' => $item->id,
                'pc_name' => 'Custom Cement Bag',
                'pc_size' => '50kg',
            ]);

        $category = \App\Models\Category::where('name', 'Returned Category')->first();
        $this->assertNotNull($category);

        $response->assertRedirect(route('plant.products.create', [
            'name' => 'Custom Cement Bag',
            'size' => '50kg',
            'product_request_id' => $productRequest->id,
            'product_request_item_id' => $item->id,
            'category_id' => $category->id,
        ]));

        $this->actingAs($purchaser)
            ->get($response->headers->get('Location'))
            ->assertOk()
            ->assertSee('Custom Cement Bag')
            ->assertSee('value="'.$productRequest->id.'"', false)
            ->assertSee('value="'.$item->id.'"', false);
    }

    public function test_creating_item_type_from_request_product_returns_with_form_data(): void
    {
        $plant = Plant::factory()->create();
        $purchaser = $this->purchaser();
        $category = \App\Models\Category::factory()->create();
        $productRequest = \App\Models\ProductRequest::create([
            'plant_id' => $plant->id,
            'requested_by' => $purchaser->id,
            'requester_name' => 'Site',
            'status' => RequestStatus::Submitted,
        ]);
        $item = \App\Models\ProductRequestItem::create([
            'product_request_id' => $productRequest->id,
            'item_name' => 'Custom Cement Bag',
            'size' => '50kg',
            'quantity' => 3,
        ]);

        $response = $this->actingAs($purchaser)
            ->post(route('plant.sub-categories.store'), [
                'category_id' => $category->id,
                'name' => 'Returned Item Type',
                'is_active' => '1',
                'redirect' => 'products.create',
                'product_request_id' => $productRequest->id,
                'product_request_item_id' => $item->id,
                'pc_name' => 'Custom Cement Bag',
                'pc_size' => '50kg',
                'pc_category_id' => $category->id,
            ]);

        $subCategory = \App\Models\SubCategory::where('name', 'Returned Item Type')->first();
        $this->assertNotNull($subCategory);

        $response->assertRedirect(route('plant.products.create', [
            'name' => 'Custom Cement Bag',
            'size' => '50kg',
            'category_id' => $category->id,
            'product_request_id' => $productRequest->id,
            'product_request_item_id' => $item->id,
            'sub_category_id' => $subCategory->id,
        ]));
    }

    public function test_editing_purchase_quantity_updates_item_quantity_and_totals(): void
    {
        $plant = Plant::factory()->create();
        $supplier = \App\Models\Supplier::factory()->create();
        $product = \App\Models\Product::factory()->create();
        $user = $this->plantInCharge($plant);

        $permAccess = \App\Models\Permission::firstOrCreate(['slug' => \App\Support\PermissionSlug::PLANT_ACCESS], ['name' => 'Plant Portal Access']);
        $permManage = \App\Models\Permission::firstOrCreate(['slug' => \App\Support\PermissionSlug::PLANT_MANAGE_PURCHASES], ['name' => 'Manage Plant Purchases']);
        $permView = \App\Models\Permission::firstOrCreate(['slug' => \App\Support\PermissionSlug::PLANT_VIEW_PURCHASES], ['name' => 'View Plant Purchases']);
        $user->role->permissions()->syncWithoutDetaching([$permAccess->id, $permManage->id, $permView->id]);
        $user->unsetRelation('role');

        $purchase = \App\Models\Purchase::create([
            'plant_id' => $plant->id,
            'supplier_id' => $supplier->id,
            'reference_no' => 'Q-TEST-01',
            'document_type' => \App\Enums\PurchaseDocumentType::Quotation,
            'ordered_at' => now(),
            'subtotal' => 500,
            'total_cgst' => 45,
            'total_sgst' => 45,
            'total_igst' => 0,
            'total_gst' => 90,
            'grand_total' => 590,
            'status' => \App\Enums\PurchaseStatus::Ordered,
            'created_by' => $user->id,
        ]);

        $item = \App\Models\PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 100,
            'gst_rate' => 18,
            'discount_percent' => 0,
            'taxable_amount' => 500,
            'cgst_amount' => 45,
            'sgst_amount' => 45,
            'igst_amount' => 0,
            'line_total' => 590,
        ]);

        $this->actingAs($user)
            ->get(route('plant.purchases.edit', $purchase))
            ->assertOk()
            ->assertSee('name="items[0][quantity]"', false);

        $this->actingAs($user)
            ->put(route('plant.purchases.update', $purchase), [
                'supplier_id' => $supplier->id,
                'is_interstate' => 0,
                'items' => [
                    [
                        'id' => $item->id,
                        'quantity' => 10,
                        'unit_price' => 100,
                        'discount_percent' => 0,
                    ],
                ],
            ])
            ->assertRedirect(route('plant.purchases.index'));

        $item->refresh();
        $purchase->refresh();

        $this->assertEquals(10, (float) $item->quantity);
        $this->assertEquals(1000, (float) $item->taxable_amount);
        $this->assertEquals(1180, (float) $item->line_total);
        $this->assertEquals(1000, (float) $purchase->subtotal);
        $this->assertEquals(1180, (float) $purchase->grand_total);
    }
}
