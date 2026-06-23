<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

trait ManagesCategories
{
    use HasCatalogPortal;
    use RedirectsToProductCreate;

    public function index(): View
    {
        $categories = Category::withCount(['subCategories', 'products'])->orderBy('name')->paginate(20);

        return view($this->catalogView('categories.index'), compact('categories'));
    }

    public function create(): View
    {
        return view($this->catalogView('categories.create'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);
        $data['slug'] = Str::slug($data['name']);

        $category = Category::create($data);

        return $this->redirectAfterCatalogStore(
            $request,
            'Category created.',
            $this->catalogPortal().'.categories.index',
            ['category_id' => $category->id],
        );
    }

    public function edit(Category $category): View
    {
        return view($this->catalogView('categories.edit'), compact('category'));
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);
        $data['slug'] = Str::slug($data['name']);

        $category->update($data);

        return redirect()->route($this->catalogPortal().'.categories.index')->with('success', 'Category updated.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->products()->exists()) {
            return redirect()->route($this->catalogPortal().'.categories.index')
                ->with('error', 'Cannot delete a category that has products. Remove or reassign products first.');
        }

        $category->delete();

        return redirect()->route($this->catalogPortal().'.categories.index')->with('success', 'Category deleted.');
    }
}
