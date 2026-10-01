<?php

namespace Tests\Feature\Admin;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\SubCategory;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogDuplicateEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_catalog_entries_are_rejected(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $category = Category::factory()->create(['name' => 'Hydraulics', 'slug' => 'hydraulics']);
        $otherCategory = Category::factory()->create(['name' => 'Crusher', 'slug' => 'crusher']);
        SubCategory::create([
            'category_id' => $category->id,
            'name' => 'Bolt',
            'slug' => 'bolt',
            'is_active' => true,
        ]);
        Brand::factory()->create(['name' => 'Generic']);
        Unit::factory()->create(['name' => 'Number', 'abbreviation' => 'nos']);
        $unit = Unit::factory()->create(['name' => 'Kilogram', 'abbreviation' => 'kg']);
        Product::factory()->create([
            'category_id' => $category->id,
            'sub_category_id' => null,
            'brand_id' => null,
            'unit_id' => $unit->id,
            'name' => 'Seal Kit',
            'size' => '20mm',
            'sku' => 'HYD_SEA_20MM',
        ]);
        Vehicle::create([
            'category_id' => $category->id,
            'name' => 'Dumper',
            'registration_no' => 'RJ14AB1234',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.categories.create'))
            ->post(route('admin.categories.store'), ['name' => 'hydraulics', 'is_active' => '1'])
            ->assertRedirect(route('admin.categories.create'))
            ->assertSessionHasErrors(['name' => 'This category already exists.']);

        $this->actingAs($admin)
            ->from(route('admin.sub-categories.create'))
            ->post(route('admin.sub-categories.store'), [
                'category_id' => $category->id,
                'name' => 'BOLT',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.sub-categories.create'))
            ->assertSessionHasErrors(['name' => 'This item type already exists in the selected category.']);

        $this->actingAs($admin)
            ->post(route('admin.sub-categories.store'), [
                'category_id' => $otherCategory->id,
                'name' => 'Bolt',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('sub_categories', [
            'category_id' => $otherCategory->id,
            'name' => 'Bolt',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.brands.create'))
            ->post(route('admin.brands.store'), ['name' => 'generic'])
            ->assertRedirect(route('admin.brands.create'))
            ->assertSessionHasErrors(['name' => 'This brand already exists.']);

        $this->actingAs($admin)
            ->from(route('admin.units.create'))
            ->post(route('admin.units.store'), ['name' => 'Pieces', 'abbreviation' => 'NOS'])
            ->assertRedirect(route('admin.units.create'))
            ->assertSessionHasErrors(['abbreviation' => 'This abbreviation already exists.']);

        $this->actingAs($admin)
            ->from(route('admin.vehicles.create'))
            ->post(route('admin.vehicles.store'), [
                'category_id' => $otherCategory->id,
                'name' => 'Tipper',
                'registration_no' => 'rj14ab1234',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.vehicles.create'))
            ->assertSessionHasErrors(['registration_no' => 'This registration number already exists.']);

        $this->actingAs($admin)
            ->from(route('admin.products.create'))
            ->post(route('admin.products.store'), [
                'products' => [[
                    'name' => 'seal kit',
                    'size' => '20MM',
                    'category_id' => $category->id,
                    'unit_id' => $unit->id,
                    'reorder_level' => 5,
                    'is_active' => '1',
                ]],
            ])
            ->assertRedirect(route('admin.products.create'))
            ->assertSessionHasErrors(['products.0.name' => 'This product already exists.']);

        $this->assertSame(1, Category::whereRaw('LOWER(name) = ?', ['hydraulics'])->count());
        $this->assertSame(1, Brand::whereRaw('LOWER(name) = ?', ['generic'])->count());
        $this->assertSame(1, Product::whereRaw('LOWER(name) = ?', ['seal kit'])->count());
    }

    public function test_updating_a_catalog_record_keeps_its_own_name(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $category = Category::factory()->create(['name' => 'Hydraulics', 'slug' => 'hydraulics']);

        $this->actingAs($admin)
            ->put(route('admin.categories.update', $category), [
                'name' => 'Hydraulics',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHas('success');
    }
}
