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

        $cleanCategory = preg_replace('/[^A-Za-z0-9]/', '', $categoryName);
        $cleanSubCategory = preg_replace('/[^A-Za-z0-9]/', '', $subCategoryName);
        $cleanSize = preg_replace('/[^A-Za-z0-9]/', '', $request->size ?? '');
        $cleanName = preg_replace('/[^A-Za-z0-9]/', '', $request->name ?? '');

        $categoryPart = strtoupper(substr($cleanCategory, 0, 3));
        $subCategoryPart = $cleanSubCategory !== '' ? strtoupper(substr($cleanSubCategory, 0, 3)) : '';
        $sizePart = $cleanSize !== '' ? strtoupper(substr($cleanSize, 0, 3)) : '';
        $namePart = $cleanName !== '' ? (strlen($cleanName) >= 3 ? strtoupper(substr($cleanName, -3)) : strtoupper($cleanName)) : '';

        $sku = $categoryPart;
        if ($subCategoryPart !== '') {
            $sku .= '_' . $subCategoryPart;
        }
        if ($sizePart !== '') {
            $sku .= '_' . $sizePart;
        }
        if ($namePart !== '') {
            $sku .= '_' . $namePart;
        }

        $request->merge(['sku' => $sku]);

        return $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'sub_category_id' => ['nullable', 'exists:sub_categories,id'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'unit_id' => ['required', 'exists:units,id'],
            'name' => ['required', 'string', 'max:255'],
            'size' => ['nullable', 'string', 'max:50'],
            'sku' => ['required', 'string', 'max:100', 'unique:products,sku,'.($product?->id ?? 'NULL')],
            'reorder_level' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ], [], [
            'name' => 'item name',
            'size' => 'size/part',
        ]);
    }
}
