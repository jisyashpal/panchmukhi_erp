<?php

namespace Tests\Feature\Admin;

use App\Models\Brand;
use App\Models\Category;
use App\Models\SubCategory;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCreateCatalogReturnTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_category_from_product_create_returns_with_form_data(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.categories.store'), [
                'name' => 'Returned Category',
                'is_active' => '1',
                'redirect' => 'products.create',
                'pc_name' => 'Hydraulic Pump',
                'pc_size' => '2 inch',
                'pc_sku_code' => 'HP01',
                'pc_reorder_level' => '25',
            ]);

        $category = Category::where('name', 'Returned Category')->first();
        $this->assertNotNull($category);

        $response->assertRedirect(route('admin.products.create', [
            'name' => 'Hydraulic Pump',
            'size' => '2 inch',
            'sku_code' => 'HP01',
            'reorder_level' => '25',
            'category_id' => $category->id,
        ]));

        $this->actingAs($admin)
            ->get($response->headers->get('Location'))
            ->assertOk()
            ->assertSee('Hydraulic Pump')
            ->assertSee('2 inch')
            ->assertSee('HP01')
            ->assertSee('js-create-catalog', false);
    }

    public function test_creating_item_type_from_product_create_returns_with_form_data(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $category = Category::factory()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.sub-categories.store'), [
                'category_id' => $category->id,
                'name' => 'Returned Item Type',
                'is_active' => '1',
                'redirect' => 'products.create',
                'pc_name' => 'Hydraulic Pump',
                'pc_size' => '2 inch',
                'pc_category_id' => $category->id,
            ]);

        $subCategory = SubCategory::where('name', 'Returned Item Type')->first();
        $this->assertNotNull($subCategory);

        $response->assertRedirect(route('admin.products.create', [
            'name' => 'Hydraulic Pump',
            'size' => '2 inch',
            'category_id' => $category->id,
            'sub_category_id' => $subCategory->id,
        ]));
    }

    public function test_creating_brand_from_product_create_returns_with_form_data(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $category = Category::factory()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.brands.store'), [
                'name' => 'Returned Brand',
                'redirect' => 'products.create',
                'pc_name' => 'Hydraulic Pump',
                'pc_category_id' => $category->id,
            ]);

        $brand = Brand::where('name', 'Returned Brand')->first();
        $this->assertNotNull($brand);

        $response->assertRedirect(route('admin.products.create', [
            'name' => 'Hydraulic Pump',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
        ]));
    }

    public function test_creating_unit_from_product_create_returns_with_form_data(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.units.store'), [
                'name' => 'Kilogram',
                'abbreviation' => 'kg',
                'redirect' => 'products.create',
                'pc_name' => 'Hydraulic Pump',
                'pc_reorder_level' => '12',
            ]);

        $unit = Unit::where('abbreviation', 'kg')->first();
        $this->assertNotNull($unit);

        $response->assertRedirect(route('admin.products.create', [
            'name' => 'Hydraulic Pump',
            'reorder_level' => '12',
            'unit_id' => $unit->id,
        ]));
    }
}
