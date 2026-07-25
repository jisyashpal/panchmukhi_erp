<?php

namespace App\Http\Controllers\Ho\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductUseEntry;
use App\Models\Plant;
use App\Models\SubCategory;
use App\Models\Vehicle;
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
        $query = ProductUseEntry::with(['plant', 'vehicle', 'product.category', 'replacedProduct', 'subCategory', 'recorder'])
            ->when($request->filled('plant_id'), fn ($q) => $q->where('plant_id', $request->integer('plant_id')))
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->integer('product_id')))
            ->when($request->filled('vehicle_id'), fn ($q) => $q->where('vehicle_id', $request->integer('vehicle_id')));

        app(FinancialYearResolver::class)->applyToQuery($query, 'used_at', $request);

        $entries = $query->latest('used_at')
            ->paginate(20)
            ->withQueryString();

        return view('ho.inventory.product-life.index', [
            'entries'  => $entries,
            'plants'   => Plant::orderBy('name')->get(),
            'products' => Product::with('category')->orderBy('name')->get(),
            'vehicles' => Vehicle::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $plantId = $request->integer('plant_id') ?: null;

        return view('ho.inventory.product-life.create', [
            'plants'        => Plant::orderBy('name')->get(),
            'categories'    => Category::where('is_active', true)->orderBy('name')->get(),
            'subCategories' => SubCategory::where('is_active', true)->orderBy('name')->get(),
            'products'      => Product::with('category')->where('is_active', true)->orderBy('name')->get(),
            'vehicles'      => Vehicle::where('is_active', true)->with('category')->orderBy('name')->get(),
            'plantId'       => $plantId,
        ]);
    }

    /** Last ProductUseEntry for a vehicle + item type slot (e.g. same vehicle tyre change). */
    public function lastEntry(Request $request): JsonResponse
    {
        $vehicleId = $request->integer('vehicle_id') ?: null;
        $subCategoryId = $request->integer('sub_category_id') ?: null;

        if (! $vehicleId || ! $subCategoryId) {
            return response()->json(null);
        }

        $entry = ProductUseEntry::query()
            ->where('vehicle_id', $vehicleId)
            ->where('sub_category_id', $subCategoryId)
            ->with(['product', 'subCategory', 'replacedProduct'])
            ->latest('used_at')
            ->first();

        if (! $entry) {
            return response()->json(null);
        }

        return response()->json([
            'used_at' => $entry->used_at->format('d M Y'),
            'quantity' => $entry->quantity,
            'meter_type' => $entry->meter_type ?? 'hours',
            'current_reading' => $entry->current_reading,
            'previous_reading' => $entry->previous_reading,
            'life_hours' => $entry->life_hours,
            'usage_count' => $entry->usage_count,
            'sub_category_id' => $entry->sub_category_id,
            'sub_category' => $entry->subCategory?->name,
            'product_id' => $entry->product_id,
            'product_name' => $entry->product?->name,
            'replaced_product_id' => $entry->replaced_product_id,
            'replaced_product_name' => $entry->replacedProduct?->name,
            'notes' => $entry->notes,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'plant_id' => ['required', 'exists:plants,id'],
            'vehicle_id' => ['required', 'exists:vehicles,id'],
            'product_id' => ['required', 'exists:products,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'sub_category_id' => ['required', 'exists:sub_categories,id'],
            'meter_type' => ['required', 'in:hours,km'],
            'current_reading' => ['required', 'numeric', 'min:0'],
            'previous_reading' => ['nullable', 'numeric', 'min:0'],
            'quantity' => ['nullable', 'numeric', 'min:0.01'],
            'usage_count' => ['nullable', 'integer', 'min:1'],
            'used_at' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['quantity'] = filled($data['quantity'] ?? null) ? (float) $data['quantity'] : 1.0;
        $data['usage_count'] = filled($data['usage_count'] ?? null) ? (int) $data['usage_count'] : 1;

        $previousReading = isset($data['previous_reading']) && $data['previous_reading'] !== ''
            ? (float) $data['previous_reading']
            : null;
        $currentReading = (float) $data['current_reading'];

        if ($previousReading !== null && $currentReading < $previousReading) {
            return back()->withInput()->withErrors([
                'current_reading' => 'Current reading must be greater than or equal to previous reading.',
            ]);
        }

        // Same vehicle + item type (e.g. tyre) → last fitted item becomes "replaced"
        $lastEntry = ProductUseEntry::query()
            ->where('vehicle_id', $data['vehicle_id'])
            ->where('sub_category_id', $data['sub_category_id'])
            ->latest('used_at')
            ->first();

        // How much the previous item was used since it was fitted
        $lifeHours = $previousReading !== null
            ? max(0, $currentReading - $previousReading)
            : 0;

        DB::transaction(function () use ($data, $previousReading, $currentReading, $lastEntry, $lifeHours) {
            $entry = ProductUseEntry::create([
                'plant_id' => $data['plant_id'],
                'vehicle_id' => $data['vehicle_id'],
                'product_id' => $data['product_id'],
                'replaced_product_id' => $lastEntry?->product_id,
                'sub_category_id' => $data['sub_category_id'],
                'meter_type' => $data['meter_type'],
                'current_reading' => $currentReading,
                'previous_reading' => $previousReading,
                'quantity' => $data['quantity'],
                'life_hours' => $lifeHours,
                'usage_count' => $data['usage_count'],
                'used_at' => $data['used_at'],
                'recorded_by' => auth()->id(),
                'notes' => $data['notes'] ?? null,
            ]);

            $this->stockService->recordProductUse($entry);
        });

        return redirect()->route('ho.product-life.index')->with('success', 'Product life entry recorded.');
    }
}