<?php

namespace Tests\Feature\Plant;

use App\Enums\RequestStatus;
use App\Models\Product;
use App\Models\Role;
use App\Models\Plant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlantPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function plantInCharge(Plant $plant): User
    {
        return User::factory()->create([
            'plant_id' => $plant->id,
            'role_id' => Role::where('slug', Role::SLUG_PLANT_IN_CHARGE)->value('id'),
        ]);
    }

    protected function purchaser(Plant $plant): User
    {
        return User::factory()->create([
            'plant_id' => $plant->id,
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
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 2],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('product_requests', [
            'plant_id' => $plant->id,
            'requested_by' => $inCharge->id,
            'status' => RequestStatus::Submitted->value,
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

    public function test_plant_in_charge_cannot_access_purchase_requests(): void
    {
        $plant = Plant::factory()->create();
        $inCharge = $this->plantInCharge($plant);

        $this->actingAs($inCharge)->get(route('plant.purchase-requests.index'))
            ->assertStatus(403);
    }

    public function test_plant_in_charge_can_access_purchases(): void
    {
        $plant = Plant::factory()->create();
        $inCharge = $this->plantInCharge($plant);

        $this->actingAs($inCharge)->get(route('plant.purchases.index'))
            ->assertStatus(200)
            ->assertSee('Receive Stock', false)
            ->assertDontSee('Print', false);
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
            ->assertSee('PO-PRODUCT-VIEW', false);

        $this->actingAs($inCharge)
            ->get(route('plant.purchases.index', ['search' => 'Hydraulic']))
            ->assertStatus(200)
            ->assertSee('Hydraulic Oil 68', false);
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
            'supplier_invoice_date' => '2026-06-18',
            'subtotal' => 120,
            'total_igst' => 21.6,
            'total_cgst' => 0,
            'total_sgst' => 0,
            'total_gst' => 21.6,
            'grand_total' => 141.6,
            'notes' => 'Some update notes',
        ]);
 
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
            ->assertSee('Create Purchase Order')
            ->assertSee($product->name);
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
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 10],
                ],
            ])
            ->assertRedirect();

        $this->assertEquals($initialCount + 1, $purchaser->fresh()->notifications()->count());
        $notification = $purchaser->fresh()->notifications()->first();
        $this->assertEquals('product_request_new', $notification->data['type']);
    }
}
