<?php

namespace Tests\Feature\Admin;

use App\Enums\PurchaseItemDispatchStatus;
use App\Enums\PurchaseStatus;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Plant;
use App\Models\StockBalance;
use App\Models\Supplier;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StockFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_delivery_increases_stock_balance(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $plant = Plant::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();

        $purchase = Purchase::create([
            'plant_id' => $plant->id,
            'supplier_id' => $supplier->id,
            'reference_no' => 'PO-TEST-001',
            'status' => PurchaseStatus::Ordered,
            'ordered_at' => now(),
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => 25,
            'unit_price' => 100,
        ]);

        $service = app(StockService::class);
        $service->recordInFromPurchase($purchase, [
            ['product_id' => $product->id, 'quantity' => 25],
        ], $admin->id);

        $this->assertDatabaseHas('stock_balances', [
            'plant_id' => $plant->id,
            'product_id' => $product->id,
            'quantity' => 25,
        ]);
    }

    public function test_purchase_edit_changes_amount_without_changing_received_stock(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $plant = Plant::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create(['name' => 'Jaw Plate']);

        $purchase = Purchase::create([
            'plant_id' => $plant->id,
            'supplier_id' => $supplier->id,
            'reference_no' => 'PO-STOCK-EDIT',
            'status' => PurchaseStatus::Delivered,
            'ordered_at' => now(),
            'subtotal' => 1000,
            'grand_total' => 1000,
        ]);

        $item = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 100,
            'gst_rate' => 0,
            'discount_percent' => 0,
            'taxable_amount' => 1000,
            'line_total' => 1000,
            'received_quantity' => 0,
        ]);

        $service = app(StockService::class);
        $service->recordInFromPurchase($purchase, [
            ['product_id' => $product->id, 'quantity' => 10],
        ], $admin->id);

        $this->actingAs($admin)
            ->put(route('ho.purchases.update', $purchase), [
                'supplier_id' => $supplier->id,
                'items' => [[
                    'id' => $item->id,
                    'quantity' => 14,
                    'unit_price' => 120,
                    'discount_percent' => 0,
                    'gst_rate' => 0,
                ]],
            ])
            ->assertRedirect(route('ho.purchases.index'));

        $item->refresh();
        $this->assertEquals(14, (float) $item->quantity);
        $this->assertEquals(10, (float) $item->received_quantity);
        $this->assertEquals(1680, (float) $purchase->fresh()->grand_total);
        $this->assertEquals(10, $service->getBalance($plant->id, $product->id));
        $this->assertDatabaseHas('stock_movements', [
            'reference_id' => $purchase->id,
            'product_id' => $product->id,
            'type' => 'in',
            'quantity' => 10,
        ]);

        $this->actingAs($admin)
            ->from(route('ho.purchases.edit', $purchase))
            ->put(route('ho.purchases.update', $purchase), [
                'supplier_id' => $supplier->id,
                'items' => [[
                    'id' => $item->id,
                    'quantity' => 4,
                    'unit_price' => 120,
                    'discount_percent' => 0,
                    'gst_rate' => 0,
                ]],
            ])
            ->assertRedirect(route('ho.purchases.edit', $purchase))
            ->assertSessionHas('error');

        $this->assertEquals(14, (float) $item->fresh()->quantity);
        $this->assertEquals(10, $service->getBalance($plant->id, $product->id));
    }

    public function test_new_item_on_existing_po_keeps_original_dispatch_and_stock(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $plant = Plant::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create(['name' => 'Jaw Plate']);
        $added = Product::factory()->create(['name' => 'Bolt']);

        $purchase = Purchase::create([
            'plant_id' => $plant->id,
            'supplier_id' => $supplier->id,
            'reference_no' => 'PO-SAME-DISPATCH',
            'status' => PurchaseStatus::Delivered,
            'ordered_at' => '2026-04-02 10:00:00',
            'dispatched_at' => '2026-04-05 12:00:00',
            'dispatched_by' => $admin->id,
            'subtotal' => 1000,
            'grand_total' => 1000,
        ]);

        $item = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 100,
            'gst_rate' => 0,
            'discount_percent' => 0,
            'taxable_amount' => 1000,
            'line_total' => 1000,
            'received_quantity' => 0,
            'dispatch_status' => PurchaseItemDispatchStatus::Dispatched,
            'dispatched_at' => '2026-04-05',
            'dispatched_quantity' => 10,
        ]);

        $service = app(StockService::class);
        $service->recordInFromPurchase($purchase, [
            ['product_id' => $product->id, 'quantity' => 10],
        ], $admin->id);

        $this->actingAs($admin)
            ->put(route('ho.purchases.update', $purchase), [
                'supplier_id' => $supplier->id,
                'items' => [
                    [
                        'id' => $item->id,
                        'quantity' => 10,
                        'unit_price' => 100,
                        'discount_percent' => 0,
                        'gst_rate' => 0,
                    ],
                    [
                        'product_id' => $added->id,
                        'quantity' => 3,
                        'unit_price' => 50,
                        'discount_percent' => 0,
                        'gst_rate' => 0,
                    ],
                ],
            ])
            ->assertRedirect(route('ho.purchases.index'));

        $this->assertSame(1, Purchase::count());
        $purchase->refresh();
        $this->assertSame('2026-04-05 12:00:00', $purchase->dispatched_at?->format('Y-m-d H:i:s'));
        $this->assertSame($admin->id, $purchase->dispatched_by);

        $newItem = $purchase->items()->where('product_id', $added->id)->first();
        $this->assertNotNull($newItem);
        $this->assertSame(PurchaseItemDispatchStatus::Dispatched, $newItem->dispatch_status);
        $this->assertSame('2026-04-05', $newItem->dispatched_at?->format('Y-m-d'));
        $this->assertEquals(3, (float) $newItem->dispatched_quantity);
        $this->assertEquals(0, (float) $newItem->received_quantity);
        $this->assertEquals(10, (float) $item->fresh()->received_quantity);
        $this->assertEquals(10, $service->getBalance($plant->id, $product->id));
        $this->assertEquals(0, $service->getBalance($plant->id, $added->id));
        $this->assertSame(1, \App\Models\StockMovement::where('reference_id', $purchase->id)->count());

        $this->actingAs($admin)
            ->put(route('ho.stocks.stock-in.update-purchase', $purchase), [
                'supplier_id' => $supplier->id,
                'items' => [
                    [
                        'id' => $item->id,
                        'quantity' => 12,
                        'unit_price' => 100,
                        'gst_rate' => 0,
                    ],
                    [
                        'id' => $newItem->id,
                        'quantity' => 3,
                        'unit_price' => 50,
                        'gst_rate' => 0,
                    ],
                ],
            ])
            ->assertRedirect(route('ho.stocks.stock-in', ['purchase_id' => $purchase->id]));

        $item->refresh();
        $this->assertEquals(12, (float) $item->quantity);
        $this->assertEquals(12, (float) $item->dispatched_quantity);
        $this->assertSame('2026-04-05', $item->dispatched_at?->format('Y-m-d'));
        $this->assertEquals(10, (float) $item->received_quantity);
        $this->assertEquals(10, $service->getBalance($plant->id, $product->id));
        $this->assertSame(1, Purchase::count());
    }

    public function test_stock_out_cannot_exceed_balance(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $plant = Plant::factory()->create();
        $product = Product::factory()->create();

        StockBalance::create([
            'plant_id' => $plant->id,
            'product_id' => $product->id,
            'quantity' => 5,
        ]);

        $service = app(StockService::class);

        $this->expectException(ValidationException::class);
        $service->recordOut(
            $plant->id,
            $product->id,
            10,
            \App\Enums\StockReferenceType::StockOut,
            null,
            $admin->id
        );
    }
}
