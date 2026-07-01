<?php

namespace App\Http\Controllers\Ho\Inventory;

use App\Http\Controllers\Controller;
use App\Models\SubCategory;
use App\Models\Product;
use App\Models\ProductUseEntry;
use App\Models\Plant;
use App\Services\StockService;
use App\Support\FinancialYearResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductLifeController extends Controller
{
    public function __construct(
        protected StockService $stockService
    ) {}

    public function index(Request $request): View
    {
        $query = ProductUseEntry::with(['plant', 'product', 'subCategory', 'recorder'])
            ->when($request->filled('plant_id'), fn ($q) => $q->where('plant_id', $request->integer('plant_id')))
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->integer('product_id')));

        app(FinancialYearResolver::class)->applyToQuery($query, 'used_at', $request);

        $entries = $query->latest('used_at')
            ->paginate(20)
            ->withQueryString();

        return view('ho.inventory.product-life.index', [
            'entries'  => $entries,
            'plants'   => Plant::orderBy('name')->get(),
            'products' => Product::with('category')->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $plantId = $request->integer('plant_id') ?: null;

        return view('ho.inventory.product-life.create', [
            'plants'        => Plant::orderBy('name')->get(),
            'products'      => Product::with('category')->where('is_active', true)->orderBy('name')->get(),
            'subCategories' => SubCategory::where('is_active', true)->orderBy('name')->get(),
            'plantId'       => $plantId,
        ]);
    }

    /** Return the last ProductUseEntry for a given product (used by JS on create form). */
    public function lastEntry(Request $request): JsonResponse
    {
        $productId = $request->integer('product_id');
        $entry = ProductUseEntry::where('product_id', $productId)
            ->with(['subCategory'])
            ->latest('used_at')
            ->first();

        if (! $entry) {
            return response()->json(null);
        }

        return response()->json([
            'used_at'         => $entry->used_at->format('d M Y'),
            'quantity'        => $entry->quantity,
            'life_hours'      => $entry->life_hours,
            'usage_count'     => $entry->usage_count,
            'sub_category_id' => $entry->sub_category_id,
            'sub_category'    => $entry->subCategory?->name,
            'notes'           => $entry->notes,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'plant_id' => ['required', 'exists:plants,id'],
            'product_id' => ['required', 'exists:products,id'],
            'sub_category_id' => ['nullable', 'exists:sub_categories,id'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'life_hours' => ['nullable', 'numeric', 'min:0'],
            'usage_count' => ['nullable', 'integer', 'min:1'],
            'used_at' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($data) {
            $entry = ProductUseEntry::create([
                'plant_id' => $data['plant_id'],
                'product_id' => $data['product_id'],
                'sub_category_id' => $data['sub_category_id'] ?? null,
                'quantity' => $data['quantity'],
                'life_hours' => $data['life_hours'] ?? 0,
                'usage_count' => $data['usage_count'] ?? 1,
                'used_at' => $data['used_at'],
                'recorded_by' => auth()->id(),
                'notes' => $data['notes'] ?? null,
            ]);

            $this->stockService->recordProductUse($entry);
        });

        return redirect()->route('ho.product-life.index')->with('success', 'Product life entry recorded.');
    }
}
