<?php

namespace App\Http\Controllers\Ho\Inventory;

use App\Enums\StockReferenceType;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\SubCategory;
use App\Models\Product;
use App\Models\Plant;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StockOutController extends Controller
{
    public function __construct(
        protected StockService $stockService
    ) {}

    public function create(Request $request): View
    {
        $plantId = $request->integer('plant_id') ?: Plant::query()->orderBy('name')->value('id');

        return view('ho.inventory.stocks.stock-out', [
            'plants' => Plant::orderBy('name')->get(),
            'categories' => Category::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'products' => Product::with('unit:id,name,abbreviation')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'sku', 'category_id', 'unit_id']),
            'subCategories' => SubCategory::where('is_active', true)
                ->orderBy('name')
                ->get(),
            'plantId' => $plantId,
            'selectedProductId' => $request->integer('product_id') ?: old('product_id'),
            'selectedCategoryId' => $request->integer('category_id') ?: old('category_id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'plant_id' => ['required', 'exists:plants,id'],
            'product_id' => ['required', 'exists:products,id'],
            'usage_type' => ['required', 'in:subcategory,other'],
            'sub_category_id' => [
                'nullable',
                'exists:sub_categories,id',
            ],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'photo_confirmed' => ['required', 'boolean'],
            'reason' => [
                Rule::requiredIf($request->input('usage_type') === 'other'),
                'nullable',
                'string',
                'max:1000',
            ],
            'notes' => ['nullable', 'string'],
            'photo' => ['nullable', 'image', 'max:5120'],
        ]);

        if ($data['usage_type'] === 'other' && blank($data['reason'] ?? null)) {
            return back()->withInput()->withErrors([
                'reason' => 'Notes / reason is required for other or plant use.',
            ]);
        }

        if ($data['usage_type'] === 'subcategory' && blank($data['sub_category_id'] ?? null)) {
            return back()->withInput()->withErrors([
                'sub_category_id' => 'Please select a subcategory.',
            ]);
        }

        $subCategoryId = $data['usage_type'] === 'subcategory' ? ($data['sub_category_id'] ?? null) : null;

        $meta = [
            'sub_category_id' => $subCategoryId,
            'photo_confirmed' => $request->boolean('photo_confirmed'),
            'reason' => $data['reason'] ?? null,
        ];

        if ($request->hasFile('photo')) {
            $meta['photo_path'] = $request->file('photo')->store('stock-photos', 'public');
        }

        $this->stockService->recordOut(
            (int) $data['plant_id'],
            (int) $data['product_id'],
            (float) $data['quantity'],
            StockReferenceType::StockOut,
            null,
            (int) auth()->id(),
            $data['notes'] ?? null,
            $meta
        );

        return redirect()->route('ho.stocks.index', ['plant_id' => $data['plant_id']])
            ->with('success', 'Stock out recorded.');
    }
}
