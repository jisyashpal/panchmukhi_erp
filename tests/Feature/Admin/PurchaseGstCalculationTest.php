<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Role;
use App\Models\Plant;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchasePricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseGstCalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_intrastate_gst_splits_cgst_sgst(): void
    {
        $service = new PurchasePricingService;
        $line = $service->calculateLine(10, 100, 18, false);

        $this->assertEquals(1000, $line['taxable_amount']);
        $this->assertEquals(90, $line['cgst_amount']);
        $this->assertEquals(90, $line['sgst_amount']);
        $this->assertEquals(0, $line['igst_amount']);
        $this->assertEquals(1180, $line['line_total']);
    }

    public function test_interstate_gst_uses_igst_only(): void
    {
        $service = new PurchasePricingService;
        $line = $service->calculateLine(10, 100, 18, true);

        $this->assertEquals(180, $line['igst_amount']);
        $this->assertEquals(0, $line['cgst_amount']);
        $this->assertEquals(1180, $line['line_total']);
    }

    public function test_purchaser_can_create_purchase_with_gst(): void
    {
        $plant = Plant::factory()->create();
        $purchaser = User::factory()->create([
            'plant_id' => null,
            'role_id' => Role::where('slug', Role::SLUG_PURCHASER)->value('id'),
        ]);
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($purchaser)->post(route('plant.purchases.store'), [
            'plant_id' => $plant->id,
            'is_interstate' => 0,
            'items' => [
                [
                    'product_id' => $product->id,
                    'supplier_id' => $supplier->id,
                    'quantity' => 5,
                    'unit_price' => 100,
                    'gst_rate' => 18,
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('purchases', [
            'plant_id' => $plant->id,
            'grand_total' => 590,
            'total_cgst' => 45,
            'total_sgst' => 45,
            'total_igst' => 0,
        ]);
        $this->assertDatabaseHas('purchase_items', [
            'taxable_amount' => 500,
            'cgst_amount' => 45,
            'sgst_amount' => 45,
            'igst_amount' => 0,
            'line_total' => 590,
        ]);
    }

    public function test_create_purchase_splits_items_by_supplier_into_separate_pos(): void
    {
        $plant = Plant::factory()->create();
        $purchaser = User::factory()->create([
            'plant_id' => null,
            'role_id' => Role::where('slug', Role::SLUG_PURCHASER)->value('id'),
        ]);
        $supplierA = Supplier::factory()->create(['name' => 'Supplier A']);
        $supplierB = Supplier::factory()->create(['name' => 'Supplier B']);
        $productA = Product::factory()->create(['name' => 'Product A']);
        $productB = Product::factory()->create(['name' => 'Product B']);

        $response = $this->actingAs($purchaser)->post(route('plant.purchases.store'), [
            'plant_id' => $plant->id,
            'is_interstate' => 0,
            'items' => [
                [
                    'product_id' => $productA->id,
                    'supplier_id' => $supplierA->id,
                    'quantity' => 2,
                    'unit_price' => 100,
                    'gst_rate' => 18,
                ],
                [
                    'product_id' => $productB->id,
                    'supplier_id' => $supplierB->id,
                    'quantity' => 3,
                    'unit_price' => 50,
                    'gst_rate' => 18,
                ],
            ],
        ]);

        $response->assertRedirect(route('plant.purchases.index', ['purchase_request_id' => null]));
        $this->assertEquals(2, Purchase::count());
        $this->assertDatabaseHas('purchases', [
            'plant_id' => $plant->id,
            'supplier_id' => $supplierA->id,
            'grand_total' => 236,
        ]);
        $this->assertDatabaseHas('purchases', [
            'plant_id' => $plant->id,
            'supplier_id' => $supplierB->id,
            'grand_total' => 177,
        ]);
    }
}
