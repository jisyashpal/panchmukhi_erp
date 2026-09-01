<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\SubCategory;
use App\Models\Unit;
use Illuminate\Http\Request;

trait ManagesProductCatalog
{
    protected function productFormData(): array
    {
        return [
            'categories' => Category::orderBy('name')->get(),
            'subCategories' => SubCategory::orderBy('name')->get(),
            'brands' => Brand::orderBy('name')->get(),
            'units' => Unit::orderBy('name')->get(),
        ];
    }

    protected function validatedProduct(Request $request, ?Product $product = null): array
    {
        $category = Category::find($request->category_id);
        $categoryName = $category ? $category->name : '';

        $subCategoryName = '';
        if ($request->filled('sub_category_id')) {
            $subCategory = SubCategory::find($request->sub_category_id);
            $subCategoryName = $subCategory ? $subCategory->name : '';
        }

        $brandName = '';
        if ($request->filled('brand_id')) {
            $brand = Brand::find($request->brand_id);
            $brandName = $brand ? $brand->name : '';
        }

        $sku = $product
            ? $product->sku
            : $this->buildProductSku(
                $categoryName,
                $subCategoryName,
                $brandName,
                $request->name ?? '',
                $request->size ?? '',
                $request->sku_code ?? ''
            );

        $request->merge(['sku' => $sku]);

        return $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'sub_category_id' => ['nullable', 'exists:sub_categories,id'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'unit_id' => ['required', 'exists:units,id'],
            'name' => ['required', 'string', 'max:255'],
            'size' => ['nullable', 'string', 'max:50'],
            'sku' => ['required', 'string', 'max:100', 'unique:products,sku,' . ($product?->id ?? 'NULL')],
            'reorder_level' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ], [], [
            'name' => 'item name',
            'size' => 'size/part',
        ]);
    }

    protected function buildProductSku(
        string $categoryName,
        string $subCategoryName,
        string $brandName,
        string $itemName,
        ?string $size = '',
        ?string $skuCode = ''
    ): string {
        $cleanCategory = preg_replace('/[^A-Za-z0-9]/', '', $categoryName);
        $cleanSubCategory = preg_replace('/[^A-Za-z0-9]/', '', $subCategoryName);
        $cleanBrand = preg_replace('/[^A-Za-z0-9]/', '', $brandName);
        $cleanSkuCode = preg_replace('/[^A-Za-z0-9]/', '', $skuCode ?? '');
        $cleanName = preg_replace('/[^A-Za-z0-9]/', '', $itemName);
        $cleanSize = preg_replace('/[^A-Za-z0-9]/', '', $size ?? '');

        $parts = [
            strtoupper(substr((string) $cleanCategory, 0, 3)),
        ];

        if ($cleanSubCategory !== '') {
            $parts[] = strtoupper(substr((string) $cleanSubCategory, 0, 3));
        }
        if ($cleanBrand !== '') {
            $parts[] = strtoupper(substr((string) $cleanBrand, 0, 3));
        }
        if ($cleanSkuCode !== '') {
            $parts[] = strtoupper((string) $cleanSkuCode);
        }
        if ($cleanName !== '') {
            $parts[] = strtoupper(substr((string) $cleanName, 0, 3));
        }
        if ($cleanSize !== '') {
            $parts[] = strtoupper((string) $cleanSize);
        }

        return implode('_', array_filter($parts, fn ($part) => $part !== ''));
    }
}
