<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\SubCategory;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class ProductCatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->units() as $unit) {
            Unit::firstOrCreate(
                ['abbreviation' => $unit['abbreviation']],
                ['name' => $unit['name']]
            );
        }

        $bag = Unit::where('abbreviation', 'bag')->firstOrFail();
        $kg = Unit::where('abbreviation', 'kg')->firstOrFail();
        $cum = Unit::where('abbreviation', 'cum')->firstOrFail();
        $nos = Unit::where('abbreviation', 'nos')->firstOrFail();
        $L = Unit::where('abbreviation', 'L')->firstOrFail();

        $brands = collect([
            'Generic',
            'Ultratech',
            'Tata Steel',
            'Asian Paints',
        ])->mapWithKeys(fn (string $name) => [$name => Brand::firstOrCreate(['name' => $name])]);

        $rawMaterials = Category::firstOrCreate(
            ['slug' => 'raw-materials'],
            ['name' => 'Raw Materials', 'is_active' => true]
        );

        $consumables = Category::firstOrCreate(
            ['slug' => 'consumables'],
            ['name' => 'Consumables', 'is_active' => true]
        );

        $cement = SubCategory::firstOrCreate(
            ['category_id' => $rawMaterials->id, 'slug' => 'cement'],
            ['name' => 'Cement', 'is_active' => true]
        );

        $steel = SubCategory::firstOrCreate(
            ['category_id' => $rawMaterials->id, 'slug' => 'steel'],
            ['name' => 'Steel', 'is_active' => true]
        );

        $aggregates = SubCategory::firstOrCreate(
            ['category_id' => $rawMaterials->id, 'slug' => 'aggregates'],
            ['name' => 'Aggregates', 'is_active' => true]
        );

        $paint = SubCategory::firstOrCreate(
            ['category_id' => $consumables->id, 'slug' => 'paint'],
            ['name' => 'Paint', 'is_active' => true]
        );

        $products = [
            [
                'sku' => 'CEM-OPC-50',
                'category_id' => $rawMaterials->id,
                'sub_category_id' => $cement->id,
                'brand_id' => $brands['Ultratech']->id,
                'unit_id' => $bag->id,
                'name' => 'OPC Cement 50kg',
                'reorder_level' => 100,
            ],
            [
                'sku' => 'STL-12MM',
                'category_id' => $rawMaterials->id,
                'sub_category_id' => $steel->id,
                'brand_id' => $brands['Tata Steel']->id,
                'unit_id' => $kg->id,
                'name' => 'Steel Rod 12mm',
                'reorder_level' => 500,
            ],
            [
                'sku' => 'STL-16MM',
                'category_id' => $rawMaterials->id,
                'sub_category_id' => $steel->id,
                'brand_id' => $brands['Tata Steel']->id,
                'unit_id' => $kg->id,
                'name' => 'Steel Rod 16mm',
                'reorder_level' => 300,
            ],
            [
                'sku' => 'SND-RIVER',
                'category_id' => $rawMaterials->id,
                'sub_category_id' => $aggregates->id,
                'brand_id' => $brands['Generic']->id,
                'unit_id' => $cum->id,
                'name' => 'River Sand',
                'reorder_level' => 20,
            ],
            [
                'sku' => 'AGG-20MM',
                'category_id' => $rawMaterials->id,
                'sub_category_id' => $aggregates->id,
                'brand_id' => $brands['Generic']->id,
                'unit_id' => $cum->id,
                'name' => 'Coarse Aggregate 20mm',
                'reorder_level' => 15,
            ],
            [
                'sku' => 'PNT-EMUL-20',
                'category_id' => $consumables->id,
                'sub_category_id' => $paint->id,
                'brand_id' => $brands['Asian Paints']->id,
                'unit_id' => $L->id,
                'name' => 'Exterior Emulsion 20L',
                'reorder_level' => 10,
            ],
            [
                'sku' => 'MESH-6MM',
                'category_id' => $rawMaterials->id,
                'sub_category_id' => $steel->id,
                'brand_id' => $brands['Generic']->id,
                'unit_id' => $nos->id,
                'name' => 'Wire Mesh Sheet 6mm',
                'reorder_level' => 25,
            ],
        ];

        foreach ($products as $product) {
            Product::firstOrCreate(
                ['sku' => $product['sku']],
                array_merge($product, ['is_active' => true])
            );
        }
    }

    /**
     * @return list<array{name: string, abbreviation: string}>
     */
    private function units(): array
    {
        return [
            ['name' => 'Bag', 'abbreviation' => 'bag'],
            ['name' => 'Kilogram', 'abbreviation' => 'kg'],
            ['name' => 'Gram', 'abbreviation' => 'g'],
            ['name' => 'Tonne', 'abbreviation' => 't'],
            ['name' => 'Piece', 'abbreviation' => 'pc'],
            ['name' => 'Number', 'abbreviation' => 'nos'],
            ['name' => 'Meter', 'abbreviation' => 'm'],
            ['name' => 'Square Meter', 'abbreviation' => 'sqm'],
            ['name' => 'Cubic Meter', 'abbreviation' => 'cum'],
            ['name' => 'Liter', 'abbreviation' => 'L'],
            ['name' => 'Milliliter', 'abbreviation' => 'ml'],
            ['name' => 'Box', 'abbreviation' => 'box'],
            ['name' => 'Carton', 'abbreviation' => 'ctn'],
            ['name' => 'Roll', 'abbreviation' => 'roll'],
            ['name' => 'Bundle', 'abbreviation' => 'bdl'],
            ['name' => 'Pair', 'abbreviation' => 'pr'],
            ['name' => 'Set', 'abbreviation' => 'set'],
            ['name' => 'Dozen', 'abbreviation' => 'dz'],
            ['name' => 'Foot', 'abbreviation' => 'ft'],
            ['name' => 'Inch', 'abbreviation' => 'in'],
        ];
    }
}
