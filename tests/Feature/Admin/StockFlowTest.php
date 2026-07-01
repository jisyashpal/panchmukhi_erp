<?php

namespace Tests\Feature\Admin;

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
