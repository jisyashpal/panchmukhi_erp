<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\SubCategory;
use App\Models\Unit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductCatalogSeeder extends Seeder
{
    public function run(): void
    {
        // Seed default units
        foreach ($this->units() as $unit) {
            Unit::firstOrCreate(
                ['abbreviation' => $unit['abbreviation']],
                ['name' => $unit['name']]
            );
        }

        $brandGeneric = Brand::firstOrCreate(['name' => 'Generic']);

        $csvPath = __DIR__ . '/products_import_template.csv';
        if (($handle = fopen($csvPath, 'r')) === false) {
            throw new \Exception("Cannot open CSV file: " . $csvPath);
        }

        // Read headers
        $headers = fgetcsv($handle);
        $headers = array_map(fn($h) => strtolower(trim($h)), $headers);

        $headerMap = [
            'category' => array_search('category', $headers),
            'subcategory' => array_search('subcategory', $headers),
            'productname' => array_search('product name', $headers) !== false ? array_search('product name', $headers) : array_search('productname', $headers),
            'size' => array_search('size/part', $headers) !== false ? array_search('size/part', $headers) : array_search('size', $headers),
            'unit' => array_search('unit', $headers),
            'brand' => array_search('brand', $headers),
            'reorderlevel' => array_search('reorder level', $headers) !== false ? array_search('reorder level', $headers) : array_search('reorderlevel', $headers),
        ];

        while (($row = fgetcsv($handle)) !== false) {
            if (empty(array_filter($row))) {
                continue;
            }

            $catName = trim($row[$headerMap['category']] ?? '');
            $subName = $headerMap['subcategory'] !== false ? trim($row[$headerMap['subcategory']] ?? '') : '';
            $prodName = trim($row[$headerMap['productname']] ?? '');
            $size = $headerMap['size'] !== false ? trim($row[$headerMap['size']] ?? '') : '';
            $unitAbbr = trim($row[$headerMap['unit']] ?? 'nos');
            $brandName = $headerMap['brand'] !== false ? trim($row[$headerMap['brand']] ?? '') : '';
            $reorderLevel = $headerMap['reorderlevel'] !== false ? trim($row[$headerMap['reorderlevel']] ?? '10') : '10';

            if (empty($catName) || empty($prodName)) {
                continue;
            }

            // Look up or create Category
            $category = Category::firstOrCreate(
                ['slug' => Str::slug($catName)],
                ['name' => $catName, 'is_active' => true]
            );

            // Look up or create SubCategory
            $subCategory = null;
            if (!empty($subName)) {
                $subCategory = SubCategory::firstOrCreate(
                    [
                        'category_id' => $category->id,
                        'slug' => Str::slug($subName)
                    ],
                    ['name' => $subName, 'is_active' => true]
                );
            }

            // Look up or create Unit
            $unit = Unit::where('abbreviation', strtolower($unitAbbr))
                ->orWhere('name', 'like', $unitAbbr)
                ->first();
            if (!$unit) {
                $unit = Unit::create([
                    'name' => ucfirst($unitAbbr),
                    'abbreviation' => strtolower($unitAbbr)
                ]);
            }

            // Look up or create Brand
            $brand = null;
            if (!empty($brandName)) {
                $brand = Brand::firstOrCreate(['name' => $brandName]);
            }

            // Generate clean SKU format: Category_SubCategory_Size_Brand_NamePart
            $cleanCategory = preg_replace('/[^A-Za-z0-9]/', '', $category->name);
            $cleanSubCategory = $subCategory ? preg_replace('/[^A-Za-z0-9]/', '', $subCategory->name) : '';
            $cleanSize = preg_replace('/[^A-Za-z0-9]/', '', $size);
            $cleanBrand = $brand ? preg_replace('/[^A-Za-z0-9]/', '', $brand->name) : '';
            $cleanName = preg_replace('/[^A-Za-z0-9]/', '', $prodName);

            $categoryPart = strtoupper(substr($cleanCategory, 0, 3));
            $subCategoryPart = $cleanSubCategory !== '' ? strtoupper(substr($cleanSubCategory, 0, 3)) : '';
            $sizePart = $cleanSize !== '' ? strtoupper($cleanSize) : '';
            $brandPart = $cleanBrand !== '' ? strtoupper(substr($cleanBrand, 0, 3)) : '';
            $namePart = $cleanName !== '' ? (strlen($cleanName) >= 3 ? strtoupper(substr($cleanName, -3)) : strtoupper($cleanName)) : '';

            $sku = $categoryPart;
            if ($subCategoryPart !== '') {
                $sku .= '_' . $subCategoryPart;
            }
            if ($sizePart !== '') {
                $sku .= '_' . $sizePart;
            }
            if ($brandPart !== '') {
                $sku .= '_' . $brandPart;
            }
            if ($namePart !== '') {
                $sku .= '_' . $namePart;
            }

            // Ensure SKU is unique (handle duplicates)
            $baseSku = $sku;
            $nextNum = 1;
            while (Product::where('sku', $sku)->exists()) {
                // Check if exact same product already exists to avoid duplicate seed
                $existing = Product::where('sku', $sku)->first();
                if ($existing->name === $prodName && $existing->category_id === $category->id && $existing->sub_category_id === ($subCategory ? $subCategory->id : null)) {
                    break;
                }
                $sku = $baseSku . '_' . str_pad($nextNum, 2, '0', STR_PAD_LEFT);
                $nextNum++;
            }

            Product::updateOrCreate(
                [
                    'name' => $prodName,
                    'category_id' => $category->id,
                    'sub_category_id' => $subCategory ? $subCategory->id : null
                ],
                [
                    'sku' => $sku,
                    'brand_id' => $brand ? $brand->id : null,
                    'unit_id' => $unit->id,
                    'size' => $size ?: null,
                    'reorder_level' => is_numeric($reorderLevel) ? (int)$reorderLevel : 10,
                    'is_active' => true,
                ]
            );
        }

        fclose($handle);
    }

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