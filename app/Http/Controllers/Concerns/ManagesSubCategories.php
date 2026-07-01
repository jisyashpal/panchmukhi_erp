<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Category;
use App\Models\SubCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

trait ManagesSubCategories
{
    use HasCatalogPortal;
    use RedirectsToProductCreate;

    public function index(Request $request): View
    {
        $subCategories = SubCategory::with('category')
            ->withCount('products')
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view($this->catalogView('sub-categories.index'), [
            'subCategories' => $subCategories,
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view($this->catalogView('sub-categories.create'), [
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);
        $data['slug'] = Str::slug($data['name']);

        $subCategory = SubCategory::create($data);

        return $this->redirectAfterCatalogStore(
            $request,
            'Sub category created.',
            $this->catalogPortal().'.sub-categories.index',
            [
                'category_id' => $subCategory->category_id,
                'sub_category_id' => $subCategory->id,
            ],
        );
    }

    public function edit(SubCategory $subCategory): View
    {
        return view($this->catalogView('sub-categories.edit'), [
            'subCategory' => $subCategory,
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, SubCategory $subCategory): RedirectResponse
    {
        $data = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);
        $data['slug'] = Str::slug($data['name']);

        $subCategory->update($data);

        return redirect()->route($this->catalogPortal().'.sub-categories.index')->with('success', 'Sub category updated.');
    }

    public function destroy(SubCategory $subCategory): RedirectResponse
    {
        if ($subCategory->products()->exists()) {
            return redirect()->route($this->catalogPortal().'.sub-categories.index')
                ->with('error', 'Cannot delete a sub category that has products. Remove or reassign products first.');
        }

        $subCategory->delete();

        return redirect()->route($this->catalogPortal().'.sub-categories.index')->with('success', 'Sub category deleted.');
    }
}
