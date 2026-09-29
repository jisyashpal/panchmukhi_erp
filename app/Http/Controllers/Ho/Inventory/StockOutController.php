<?php

namespace App\Http\Controllers\Ho\Inventory;

use App\Enums\StockMovementType;
use App\Enums\StockReferenceType;
use App\Http\Controllers\Concerns\ListsStockMovements;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Plant;
use App\Models\PurchaseItem;
use App\Models\StockBalance;
use App\Models\SubCategory;
use App\Models\Vehicle;
use App\Services\StockService;
use App\Support\ReportFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockOutController extends Controller
{
    use ListsStockMovements;

    public function __construct(
        protected StockService $stockService
    ) {}

    public function create(Request $request): View|StreamedResponse
    {
        $filters = ReportFilters::fromRequest($request);

        if ($request->query('export') === 'excel') {
            return $this->exportStockMovements(StockMovementType::Out, $filters);
        }

        $plantId = $request->integer('plant_id') ?: Plant::query()->orderBy('name')->value('id');
        $movements = $this->stockService->listMovements(StockMovementType::Out, $filters);

        return view('ho.inventory.stocks.stock-out', [
            'plants' => Plant::orderBy('name')->get(),
            'categories' => Category::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'products' => Product::with('unit:id,name,abbreviation')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'sku', 'category_id', 'sub_category_id', 'unit_id']),
            'subCategories' => SubCategory::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'category_id']),
            'vehicles' => Vehicle::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'registration_no', 'category_id']),
            'plantId' => $plantId,
            'stockByPlant' => StockBalance::query()
                ->get(['plant_id', 'product_id', 'quantity'])
                ->groupBy('plant_id')
                ->map(fn ($rows) => $rows->mapWithKeys(fn ($row) => [
                    $row->product_id => (float) $row->quantity,
                ]))
                ->all(),
            'selectedProductId' => $request->integer('product_id') ?: null,
            'selectedCategoryId' => $request->integer('category_id') ?: null,
            'selectedSubCategoryId' => $request->integer('sub_category_id') ?: null,
            'filters' => $filters,
            'movements' => $movements,
            'rateByProduct' => PurchaseItem::query()
                ->select('product_id', 'unit_price')
                ->whereIn('id', PurchaseItem::query()
                    ->selectRaw('MAX(id)')
                    ->groupBy('product_id'))
                ->pluck('unit_price', 'product_id')
                ->map(fn ($price) => (float) $price)
                ->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'plant_id' => ['required', 'exists:plants,id'],
            'moved_at' => ['nullable', 'date'],
            'usage_type' => ['required', 'in:vehicle,other'],
            'vehicle_id' => ['nullable', 'exists:vehicles,id'],
            'photo_confirmed' => ['required', 'boolean'],
            'reason' => [
                Rule::requiredIf($request->input('usage_type') === 'other'),
                'nullable',
                'string',
                'max:1000',
            ],
            'notes' => ['nullable', 'string'],
            'photo' => ['nullable', 'image', 'max:5120'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($data['usage_type'] === 'other' && blank($data['reason'] ?? null)) {
            return back()->withInput()->withErrors([
                'reason' => 'Notes / reason is required for other or plant use.',
            ]);
        }

        if ($data['usage_type'] === 'vehicle' && blank($data['vehicle_id'] ?? null)) {
            return back()->withInput()->withErrors([
                'vehicle_id' => 'Please select a vehicle / machine.',
            ]);
        }

        $productIds = array_map('intval', array_column($data['items'], 'product_id'));
        if (count($productIds) !== count(array_unique($productIds))) {
            return back()->withInput()->withErrors([
                'items' => 'The same product cannot be issued twice in one stock out. Combine the quantities instead.',
            ]);
        }

        $vehicleId = $data['usage_type'] === 'vehicle' ? ($data['vehicle_id'] ?? null) : null;

        $meta = [
            'vehicle_id' => $vehicleId,
            'photo_confirmed' => $request->boolean('photo_confirmed'),
            'reason' => $data['reason'] ?? null,
        ];

        if ($request->hasFile('photo')) {
            $meta['photo_path'] = $request->file('photo')->store('stock-photos', 'public');
        }

        $lines = array_map(fn (array $item) => [
            'product_id' => (int) $item['product_id'],
            'quantity' => (float) $item['quantity'],
            'unit_price' => isset($item['unit_price']) && $item['unit_price'] !== ''
                ? (float) $item['unit_price']
                : null,
        ], $data['items']);

        try {
            $this->stockService->recordOutMany(
                (int) $data['plant_id'],
                $lines,
                StockReferenceType::StockOut,
                null,
                (int) auth()->id(),
                $data['notes'] ?? null,
                $meta,
                $data['moved_at'] ?? null
            );
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        $count = count($lines);
        $message = $count === 1
            ? 'Stock out recorded.'
            : "Stock out recorded for {$count} items.";

        return redirect()->route('ho.stocks.index', ['plant_id' => $data['plant_id']])
            ->with('success', $message);
    }
}
