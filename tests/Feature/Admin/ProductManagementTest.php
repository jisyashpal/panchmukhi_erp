<?php

namespace Tests\Feature\Admin;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Plant;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\SubCategory;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_products_index_shows_current_stock_and_delete(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $plant = Plant::factory()->create();
        $product = Product::factory()->create(['name' => 'Hydraulic Pump']);

        StockBalance::create([
            'plant_id' => $plant->id,
            'product_id' => $product->id,
            'quantity' => 12.5,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.products.index'))
            ->assertOk()
            ->assertSee('Hydraulic Pump')
            ->assertSee('Current Stock')
            ->assertSee('12.50')
            ->assertSee('aria-label="Delete"', false);
    }

    public function test_admin_can_delete_unused_product(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $product = Product::factory()->create();

        $this->actingAs($admin)
            ->delete(route('admin.products.destroy', $product))
            ->assertRedirect(route('admin.products.index'))
            ->assertSessionHas('success', 'Product deleted.');

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_admin_cannot_delete_product_used_in_stock(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $plant = Plant::factory()->create();
        $product = Product::factory()->create();

        StockBalance::create([
            'plant_id' => $plant->id,
            'product_id' => $product->id,
            'quantity' => 4,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.products.destroy', $product))
            ->assertRedirect(route('admin.products.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_updating_a_product_rebuilds_sku_the_same_way_as_create(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $category = Category::factory()->create(['name' => 'Hydraulic']);
        $subCategory = SubCategory::create([
            'category_id' => $category->id,
            'name' => 'Pump',
            'slug' => 'pump',
            'is_active' => true,
        ]);
        $brand = Brand::factory()->create(['name' => 'Bosch']);
        $unit = Unit::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'sub_category_id' => $subCategory->id,
            'brand_id' => $brand->id,
            'unit_id' => $unit->id,
            'name' => 'Hydraulic Pump',
            'size' => '12mm',
            'sku' => 'OLD-SKU',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertSee('SKU Code')
            ->assertSee('name="sku_code"', false);

        $this->actingAs($admin)
            ->put(route('admin.products.update', $product), [
                'category_id' => $category->id,
                'sub_category_id' => $subCategory->id,
                'brand_id' => $brand->id,
                'unit_id' => $unit->id,
                'sku_code' => 'hp-01',
                'name' => 'Hydraulic Valve',
                'size' => '20mm',
                'reorder_level' => 8,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.products.index'))
            ->assertSessionHas('success');

        $product->refresh();

        $this->assertSame('HYD_PUM_BOS_HP01_HYD_20MM', $product->sku);
        $this->assertSame('Hydraulic Valve', $product->name);
        $this->assertSame('20mm', $product->size);
    }
}
