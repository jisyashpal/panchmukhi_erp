<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

trait ManagesBrands
{
    use HasCatalogPortal;
    use RedirectsToProductCreate;

    public function index(): View
    {
        $brands = Brand::withCount('products')->orderBy('name')->paginate(20);

        return view($this->catalogView('brands.index'), compact('brands'));
    }

    public function create(): View
    {
        return view($this->catalogView('brands.create'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255', 'unique:brands,name']]);
        $brand = Brand::create($data);

        return $this->redirectAfterCatalogStore(
            $request,
            'Brand created.',
            $this->catalogPortal().'.brands.index',
            ['brand_id' => $brand->id],
        );
    }

    public function edit(Brand $brand): View
    {
        return view($this->catalogView('brands.edit'), compact('brand'));
    }

    public function update(Request $request, Brand $brand): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255', 'unique:brands,name,'.$brand->id]]);
        $brand->update($data);

        return redirect()->route($this->catalogPortal().'.brands.index')->with('success', 'Brand updated.');
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        if ($brand->products()->exists()) {
            return redirect()->route($this->catalogPortal().'.brands.index')
                ->with('error', 'Cannot delete a brand that has products. Remove or reassign products first.');
        }

        $brand->delete();

        return redirect()->route($this->catalogPortal().'.brands.index')->with('success', 'Brand deleted.');
    }
}
