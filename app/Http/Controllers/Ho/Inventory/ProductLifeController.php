<?php

namespace App\Http\Controllers\Ho\Inventory;

use App\Enums\StockMovementType;
use App\Enums\StockReferenceType;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductUseEntry;
use App\Models\Plant;
use App\Models\StockMovement;
use App\Models\Vehicle;
use App\Support\FinancialYearResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProductLifeController extends Controller
{

    public function index(Request $request): View
    {
        $query = ProductUseEntry::with(['plant', 'vehicle', 'product.category', 'replacedProduct', 'subCategory', 'recorder'])
            ->when($request->filled('plant_id'), fn ($q) => $q->where('plant_id', $request->integer('plant_id')))
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->integer('product_id')))
            ->when($request->filled('vehicle_id'), fn ($q) => $q->where('vehicle_id', $request->integer('vehicle_id')));

        app(FinancialYearResolver::class)->applyToQuery($query, 'used_at', $request);

        $totals = (clone $query)->setEagerLoads([])->selectRaw(
            'COALESCE(SUM(life_hours), 0) as total_life,
             COALESCE(SUM(quantity), 0) as total_qty,
             COALESCE(SUM(usage_count), 0) as usage_count'
        )->first();

        $entries = $query->latest('used_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('ho.inventory.product-life.index', [
            'entries'  => $entries,
            'totals'   => $totals,
            'plants'   => Plant::orderBy('name')->get(),
            'products' => Product::with('category')->orderBy('name')->get(),
            'vehicles' => Vehicle::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        $stockOuts = $this->availableStockOuts();

        return view('ho.inventory.product-life.create', [
            'stockOuts' => $stockOuts,
            'plants' => $stockOuts->pluck('plant')->filter()->unique('id')->sortBy('name')->values(),
            'vehicles' => $stockOuts->pluck('vehicle')->filter()->unique('id')->sortBy('name')->values(),
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
            ->latest('id')
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
            'stock_movement_id' => ['required', 'exists:stock_movements,id'],
            'meter_type' => ['required', 'in:hours,km'],
            'current_reading' => ['required', 'numeric', 'min:0'],
            'previous_reading' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $previousReading = isset($data['previous_reading']) && $data['previous_reading'] !== ''
            ? (float) $data['previous_reading']
            : null;
        $currentReading = (float) $data['current_reading'];

        if ($previousReading !== null && $currentReading < $previousReading) {
            return back()->withInput()->withErrors([
                'current_reading' => 'Current reading must be greater than or equal to previous reading.',
            ]);
        }

        try {
            DB::transaction(function () use ($data, $previousReading, $currentReading) {
                $movement = StockMovement::query()
                    ->with('product')
                    ->whereKey($data['stock_movement_id'])
                    ->lockForUpdate()
                    ->first();

                if (! $movement || $movement->type !== StockMovementType::Out || ! $movement->vehicle_id) {
                    throw ValidationException::withMessages([
                        'stock_movement_id' => 'Select a vehicle stock out that has already been recorded.',
                    ]);
                }

                if ($movement->reference_type === StockReferenceType::ProductUse && $movement->reference_id) {
                    throw ValidationException::withMessages([
                        'stock_movement_id' => 'Product life is already recorded for this stock out.',
                    ]);
                }

                $subCategoryId = $movement->sub_category_id ?: $movement->product?->sub_category_id;
                $usageCount = max(1, (int) round((float) $movement->quantity));

                $lastEntry = $subCategoryId
                    ? ProductUseEntry::query()
                        ->where('vehicle_id', $movement->vehicle_id)
                        ->where('sub_category_id', $subCategoryId)
                        ->latest('used_at')
                        ->latest('id')
                        ->first()
                    : null;

                $lifeHours = $previousReading !== null
                    ? max(0, $currentReading - $previousReading)
                    : 0;

                $entry = ProductUseEntry::create([
                    'plant_id' => $movement->plant_id,
                    'vehicle_id' => $movement->vehicle_id,
                    'product_id' => $movement->product_id,
                    'replaced_product_id' => $lastEntry?->product_id,
                    'sub_category_id' => $subCategoryId,
                    'meter_type' => $data['meter_type'],
                    'current_reading' => $currentReading,
                    'previous_reading' => $previousReading,
                    'quantity' => $movement->quantity,
                    'life_hours' => $lifeHours,
                    'usage_count' => $usageCount,
                    'used_at' => $movement->moved_at,
                    'recorded_by' => auth()->id(),
                    'notes' => $data['notes'] ?? null,
                ]);

                $movement->update([
                    'reference_type' => StockReferenceType::ProductUse,
                    'reference_id' => $entry->id,
                    'sub_category_id' => $movement->sub_category_id ?: $subCategoryId,
                ]);
            });
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()->route('ho.product-life.index')->with('success', 'Product life recorded against the selected stock out.');
    }

    private function availableStockOuts()
    {
        return StockMovement::query()
            ->with([
                'plant:id,name',
                'vehicle:id,name,registration_no',
                'product:id,name,sku,category_id,sub_category_id',
                'product.category:id,name',
                'product.subCategory:id,name,category_id',
                'subCategory:id,name',
            ])
            ->where('type', StockMovementType::Out)
            ->whereNotNull('vehicle_id')
            ->where(function ($query) {
                $query->where('reference_type', '!=', StockReferenceType::ProductUse->value)
                    ->orWhereNull('reference_id');
            })
            ->latest('moved_at')
            ->latest('id')
            ->get();
    }
}