<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\RedirectsToProductCreate;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\SubCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SubCategoryController extends Controller
{
    use RedirectsToProductCreate;
    public function index(Request $request): View
    {
        $subCategories = SubCategory::with('category')
            ->withCount('products')
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.sub-categories.index', [
            'subCategories' => $subCategories,
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.sub-categories.create', [
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
            'admin.sub-categories.index',
            [
                'category_id' => $subCategory->category_id,
                'sub_category_id' => $subCategory->id,
            ],
        );
    }

    public function edit(SubCategory $subCategory): View
    {
        return view('admin.sub-categories.edit', [
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

        return redirect()->route('admin.sub-categories.index')->with('success', 'Sub category updated.');
    }

    public function destroy(SubCategory $subCategory): RedirectResponse
    {
        if ($subCategory->products()->exists()) {
            return redirect()->route('admin.sub-categories.index')
                ->with('error', 'Cannot delete a sub category that has products. Remove or reassign products first.');
        }

        $subCategory->delete();

        return redirect()->route('admin.sub-categories.index')->with('success', 'Sub category deleted.');
    }
}
