<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\Site;
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

    public function test_super_admin_can_create_purchase_with_gst(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $site = Site::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($admin)->post(route('ho.purchases.store'), [
            'site_id' => $site->id,
            'supplier_id' => $supplier->id,
            'is_interstate' => 0,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 5,
                    'unit_price' => 100,
                    'gst_rate' => 18,
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('purchases', [
            'site_id' => $site->id,
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
}
