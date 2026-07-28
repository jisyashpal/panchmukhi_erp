<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

trait ManagesProducts
{
    use HasCatalogPortal;
    use ManagesProductCatalog;

    public function index(Request $request): View
    {
        $products = Product::with(['category', 'subCategory', 'brand', 'unit'])
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->filled('brand_id'), fn ($q) => $q->where('brand_id', $request->integer('brand_id')))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->filled('search'), fn ($q) => $q->where(function ($q) use ($request) {
                $search = $request->string('search');
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('sku', 'like', '%'.$search.'%')
                    ->orWhere('size', 'like', '%'.$search.'%')
                    ->orWhereHas('category', fn ($q) => $q->where('name', 'like', '%'.$search.'%'))
                    ->orWhereHas('subCategory', fn ($q) => $q->where('name', 'like', '%'.$search.'%'))
                    ->orWhereHas('brand', fn ($q) => $q->where('name', 'like', '%'.$search.'%'));
            }))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view($this->catalogView('products.index'), [
            'products' => $products,
            'allProducts' => Product::orderBy('name')->get(),
            'categories' => $this->productFormData()['categories'],
            'brands' => $this->productFormData()['brands'],
        ]);
    }

    public function create(): View
    {
        return view($this->catalogView('products.create'), $this->productFormData());
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'products' => ['required', 'array', 'min:1'],
            'products.*.category_id' => ['required', 'exists:categories,id'],
            'products.*.sub_category_id' => ['nullable', 'exists:sub_categories,id'],
            'products.*.brand_id' => ['nullable', 'exists:brands,id'],
            'products.*.unit_id' => ['required', 'exists:units,id'],
            'products.*.name' => ['required', 'string', 'max:255'],
            'products.*.size' => ['nullable', 'string', 'max:50'],
            'products.*.reorder_level' => ['required', 'integer', 'min:0'],
            'products.*.is_active' => ['boolean'],
        ]);

        $items = $request->input('products');
        $skus = [];

        // Pre-fetch categories, subcategories and brands to avoid N+1 queries during SKU generation
        $categoryIds = collect($items)->pluck('category_id')->filter()->unique()->all();
        $subCategoryIds = collect($items)->pluck('sub_category_id')->filter()->unique()->all();
        $brandIds = collect($items)->pluck('brand_id')->filter()->unique()->all();

        $categories = \App\Models\Category::whereIn('id', $categoryIds)->pluck('name', 'id');
        $subCategories = \App\Models\SubCategory::whereIn('id', $subCategoryIds)->pluck('name', 'id');
        $brands = \App\Models\Brand::whereIn('id', $brandIds)->pluck('name', 'id');

        $validatedData = [];

        foreach ($items as $index => $item) {
            $categoryName = $categories[$item['category_id']] ?? '';
            $subCategoryName = $subCategories[$item['sub_category_id'] ?? ''] ?? '';
            $brandName = $brands[$item['brand_id'] ?? ''] ?? '';

            $cleanCategory = preg_replace('/[^A-Za-z0-9]/', '', $categoryName);
            $cleanSubCategory = preg_replace('/[^A-Za-z0-9]/', '', $subCategoryName);
            $cleanSize = preg_replace('/[^A-Za-z0-9]/', '', $item['size'] ?? '');
            $cleanBrand = preg_replace('/[^A-Za-z0-9]/', '', $brandName);
            $cleanName = preg_replace('/[^A-Za-z0-9]/', '', $item['name'] ?? '');

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

            // Check SKU uniqueness in DB
            $existsInDb = Product::where('sku', $sku)->exists();
            if ($existsInDb || in_array($sku, $skus)) {
                return back()->withErrors([
                    "products.{$index}.name" => "The product SKU ({$sku}) has already been taken (or is duplicated in this request)."
                ])->withInput();
            }

            $skus[] = $sku;

            $validatedData[] = [
                'category_id' => $item['category_id'],
                'sub_category_id' => $item['sub_category_id'] ?? null,
                'brand_id' => $item['brand_id'] ?? null,
                'unit_id' => $item['unit_id'],
                'name' => $item['name'],
                'size' => $item['size'] ?? null,
                'sku' => $sku,
                'reorder_level' => $item['reorder_level'],
                'is_active' => isset($item['is_active']) ? (bool)$item['is_active'] : true,
            ];
        }

        $createdProducts = [];
        foreach ($validatedData as $data) {
            $createdProducts[] = Product::create($data);
        }

        if (
            $this->catalogPortal() === 'plant'
            && $request->filled('product_request_item_id')
            && ! empty($createdProducts)
        ) {
            $item = \App\Models\ProductRequestItem::with('productRequest')->find($request->integer('product_request_item_id'));
            $product = $createdProducts[0];

            if ($item && ! $item->product_id) {
                $item->update([
                    'product_id' => $product->id,
                    'item_name' => null,
                    'size' => $item->size ?: $product->size,
                    'sub_category_id' => $item->sub_category_id ?: $product->sub_category_id,
                ]);

                $requestId = $request->integer('product_request_id') ?: $item->product_request_id;

                return redirect()
                    ->route('plant.product-requests.show', $requestId)
                    ->with('success', 'Product created and linked to the request item.');
            }
        }

        return redirect()->route($this->catalogPortal().'.products.index')->with('success', 'Products created successfully.');
    }

    public function edit(Product $product): View
    {
        return view($this->catalogView('products.edit'), array_merge(['product' => $product], $this->productFormData()));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $product->update($this->validatedProduct($request, $product));

        return redirect()->route($this->catalogPortal().'.products.index')->with('success', 'Product updated.');
    }

    public function importForm(): View
    {
        return view($this->catalogView('products.import'));
    }

    public function downloadTemplate(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="products_import_template.csv"',
        ];

        return response()->stream(function () {
            $handle = fopen('php://output', 'w');
            
            // Header row matching standard columns
            fputcsv($handle, [
                'Category',
                'SubCategory',
                'Product Name',
                'Size/Part',
                'Unit',
                'Brand',
                'Reorder Level'
            ]);

            // Sample rows matching standard data
            fputcsv($handle, [
                'HYVA',
                'BOLT',
                'BOLT NUT 12X40',
                '12X40',
                'nos',
                'Generic',
                '10'
            ]);
            fputcsv($handle, [
                'CRUSHER',
                'ROLLER',
                'IMPACT ROLLER 200MM',
                '200MM',
                'nos',
                'Generic',
                '10'
            ]);

            fclose($handle);
        }, 200, $headers);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:4096'],
        ]);

        $file = $request->file('csv_file');
        $filePath = $file->getRealPath();

        if (($handle = fopen($filePath, 'r')) === false) {
            return back()->withErrors(['csv_file' => 'Failed to open the uploaded file.']);
        }

        // Parse headers
        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return back()->withErrors(['csv_file' => 'The uploaded file is empty.']);
        }

        // Normalize header spaces and capitalization
        $headers = array_map(fn($h) => strtolower(trim($h)), $headers);

        // Required headers mapping
        $headerMap = [
            'category' => array_search('category', $headers),
            'subcategory' => array_search('subcategory', $headers),
            'productname' => array_search('product name', $headers) !== false ? array_search('product name', $headers) : array_search('productname', $headers),
            'size' => array_search('size/part', $headers) !== false ? array_search('size/part', $headers) : array_search('size', $headers),
            'unit' => array_search('unit', $headers),
            'brand' => array_search('brand', $headers),
            'reorderlevel' => array_search('reorder level', $headers) !== false ? array_search('reorder level', $headers) : array_search('reorderlevel', $headers),
        ];

        // Ensure minimum required columns exist
        if ($headerMap['category'] === false || $headerMap['productname'] === false || $headerMap['unit'] === false) {
            fclose($handle);
            return back()->withErrors(['csv_file' => 'The CSV must contain Category, Product Name, and Unit columns.']);
        }

        $importedCount = 0;
        $rowCount = 0;
        $errors = [];

        \Illuminate\Support\Facades\DB::beginTransaction();

        try {
            while (($row = fgetcsv($handle)) !== false) {
                $rowCount++;
                
                // Skip completely empty rows
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

                if (empty($catName) || empty($prodName) || empty($unitAbbr)) {
                    $errors[] = "Row {$rowCount}: Category, Product Name, and Unit are required fields.";
                    continue;
                }

                // Look up or create Category
                $category = \App\Models\Category::firstOrCreate(
                    ['slug' => \Illuminate\Support\Str::slug($catName)],
                    ['name' => $catName, 'is_active' => true]
                );

                // Look up or create SubCategory if present
                $subCategory = null;
                if (!empty($subName)) {
                    $subCategory = \App\Models\SubCategory::firstOrCreate(
                        [
                            'category_id' => $category->id,
                            'slug' => \Illuminate\Support\Str::slug($subName)
                        ],
                        ['name' => $subName, 'is_active' => true]
                    );
                }

                // Look up or create Unit
                $unit = \App\Models\Unit::where('abbreviation', strtolower($unitAbbr))
                    ->orWhere('name', 'like', $unitAbbr)
                    ->first();
                if (!$unit) {
                    $unit = \App\Models\Unit::create([
                        'name' => ucfirst($unitAbbr),
                        'abbreviation' => strtolower($unitAbbr)
                    ]);
                }

                // Look up or create Brand
                $brand = null;
                if (!empty($brandName)) {
                    $brand = \App\Models\Brand::firstOrCreate(['name' => $brandName]);
                }

                // Normalize names
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

                // Generate SKU
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
                while (\App\Models\Product::where('sku', $sku)->exists()) {
                    // Check if exact same product already exists to avoid redundant creation
                    $existing = \App\Models\Product::where('sku', $sku)->first();
                    if ($existing->name === $prodName && $existing->category_id === $category->id && $existing->sub_category_id === ($subCategory ? $subCategory->id : null)) {
                        // Product already exists with same name and category, update it or skip
                        break;
                    }
                    $sku = $baseSku . '_' . str_pad($nextNum, 2, '0', STR_PAD_LEFT);
                    $nextNum++;
                }

                \App\Models\Product::updateOrCreate(
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

                $importedCount++;
            }

            fclose($handle);

            if (!empty($errors)) {
                \Illuminate\Support\Facades\DB::rollBack();
                return back()->withErrors($errors)->withInput();
            }

            \Illuminate\Support\Facades\DB::commit();

        } catch (\Exception $e) {
            fclose($handle);
            \Illuminate\Support\Facades\DB::rollBack();
            return back()->withErrors(['csv_file' => 'Error during import: ' . $e->getMessage()]);
        }

        return redirect()
            ->route($this->catalogPortal() . '.products.index')
            ->with('success', "Successfully imported {$importedCount} products.");
    }
}
