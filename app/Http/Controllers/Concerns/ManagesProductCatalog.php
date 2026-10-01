<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\SubCategory;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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

        $skuCode = $this->normalizeSkuCode($request->input('sku_code'));
        $sku = $this->buildProductSku(
            $categoryName,
            $subCategoryName,
            $brandName,
            $request->name ?? '',
            $request->size ?? '',
            $skuCode ?? ''
        );

        $request->merge(['sku' => $sku]);

        $data = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'sub_category_id' => ['nullable', 'exists:sub_categories,id'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'unit_id' => ['required', 'exists:units,id'],
            'sku_code' => ['nullable', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'size' => ['nullable', 'string', 'max:50'],
            'sku' => ['required', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($product?->id)],
            'reorder_level' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ], [], [
            'name' => 'item name',
            'size' => 'size/part',
        ]);

        $data['sku'] = $sku;
        unset($data['sku_code']);

        if ($this->productAlreadyExists($data, $product?->id)) {
            throw ValidationException::withMessages([
                'name' => 'This product already exists.',
            ]);
        }

        return $data;
    }

    protected function productAlreadyExists(array $item, ?int $ignoreId = null): bool
    {
        $name = mb_strtolower(trim((string) ($item['name'] ?? '')));
        $size = mb_strtolower(trim((string) ($item['size'] ?? '')));

        return Product::query()
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where('category_id', $item['category_id'])
            ->whereRaw('LOWER(TRIM(name)) = ?', [$name])
            ->when(
                filled($item['sub_category_id'] ?? null),
                fn ($query) => $query->where('sub_category_id', $item['sub_category_id']),
                fn ($query) => $query->whereNull('sub_category_id'),
            )
            ->when(
                filled($item['brand_id'] ?? null),
                fn ($query) => $query->where('brand_id', $item['brand_id']),
                fn ($query) => $query->whereNull('brand_id'),
            )
            ->when(
                $size === '',
                fn ($query) => $query->where(fn ($query) => $query->whereNull('size')->orWhereRaw("TRIM(size) = ''")),
                fn ($query) => $query->whereRaw('LOWER(TRIM(size)) = ?', [$size]),
            )
            ->exists();
    }

    protected function productIdentityKey(array $item): string
    {
        return implode('|', [
            $item['category_id'] ?? '',
            $item['sub_category_id'] ?? '',
            $item['brand_id'] ?? '',
            mb_strtolower(trim((string) ($item['name'] ?? ''))),
            mb_strtolower(trim((string) ($item['size'] ?? ''))),
        ]);
    }

    protected function normalizeSkuCode(?string $skuCode): ?string
    {
        $clean = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $skuCode ?? ''));

        return $clean !== '' ? $clean : null;
    }

    protected function guessProductSkuCode(Product $product): ?string
    {
        $withoutCode = $this->buildProductSku(
            $product->category?->name ?? '',
            $product->subCategory?->name ?? '',
            $product->brand?->name ?? '',
            $product->name ?? '',
            $product->size ?? '',
            ''
        );

        $expected = $withoutCode === '' ? [] : explode('_', $withoutCode);
        $actual = filled($product->sku) ? explode('_', (string) $product->sku) : [];

        $prefix = 0;
        $limit = min(count($expected), count($actual));
        while ($prefix < $limit && $expected[$prefix] === $actual[$prefix]) {
            $prefix++;
        }

        $suffix = 0;
        while (
            $suffix < (count($expected) - $prefix)
            && $suffix < (count($actual) - $prefix)
            && $expected[count($expected) - 1 - $suffix] === $actual[count($actual) - 1 - $suffix]
        ) {
            $suffix++;
        }

        if ($prefix + $suffix !== count($expected)) {
            return null;
        }

        $middle = array_slice($actual, $prefix, count($actual) - $prefix - $suffix);

        return count($middle) === 1 ? $middle[0] : null;
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
