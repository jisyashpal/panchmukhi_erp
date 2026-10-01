<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Category;
use App\Models\SubCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

trait ManagesSubCategories
{
    use HasCatalogPortal;
    use RedirectsToProductCreate;

    public function index(Request $request): View
    {
        $subCategories = SubCategory::with('category')
            ->withCount('products')
            ->when($request->filled('category_id'), fn($q) => $q->where('category_id', $request->integer('category_id')))
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
        $this->assertUniqueSubCategory((int) $data['category_id'], $data['name'], $data['slug']);

        $subCategory = SubCategory::create($data);

        return $this->redirectAfterCatalogStore(
            $request,
            'Item Type created.',
            $this->catalogPortal() . '.sub-categories.index',
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
        $this->assertUniqueSubCategory((int) $data['category_id'], $data['name'], $data['slug'], $subCategory->id);

        $subCategory->update($data);

        return redirect()->route($this->catalogPortal() . '.sub-categories.index')->with('success', 'Item Type updated.');
    }

    public function destroy(SubCategory $subCategory): RedirectResponse
    {
        if ($subCategory->products()->exists()) {
            return redirect()->route($this->catalogPortal() . '.sub-categories.index')
                ->with('error', 'Cannot delete a Item Type that has products. Remove or reassign products first.');
        }

        $subCategory->delete();

        return redirect()->route($this->catalogPortal() . '.sub-categories.index')->with('success', 'Item Type deleted.');
    }

    private function assertUniqueSubCategory(int $categoryId, string $name, string $slug, ?int $ignoreId = null): void
    {
        if ($slug === '') {
            throw ValidationException::withMessages([
                'name' => 'Enter an item type name that contains letters or numbers.',
            ]);
        }

        $exists = SubCategory::query()
            ->where('category_id', $categoryId)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where(function ($query) use ($name, $slug) {
                $query->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($name))])
                    ->orWhere('slug', $slug);
            })
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => 'This item type already exists in the selected category.',
            ]);
        }
    }
}
