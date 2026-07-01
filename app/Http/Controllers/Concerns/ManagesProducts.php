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
        Product::create($this->validatedProduct($request));

        return redirect()->route($this->catalogPortal().'.products.index')->with('success', 'Product created.');
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

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return redirect()->route($this->catalogPortal().'.products.index')->with('success', 'Product deleted.');
    }
}
