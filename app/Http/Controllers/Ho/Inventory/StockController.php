<?php

namespace App\Http\Controllers\Ho\Inventory;

use App\Enums\StockMovementType;
use App\Enums\StockReferenceType;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Plant;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Support\FinancialYear;
use App\Support\FinancialYearRange;
use App\Support\FinancialYearResolver;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockController extends Controller
{
    public function index(Request $request): View
    {
        $range = $this->fyRangeFromSession($request);

        $movementSub = StockMovement::query()
            ->select('plant_id', 'product_id')
            ->selectRaw(
                'SUM(CASE WHEN type = ? THEN quantity ELSE 0 END) as fy_stock_in',
                [StockMovementType::In->value]
            )
            ->selectRaw(
                'SUM(CASE WHEN type = ? THEN quantity ELSE 0 END) as fy_stock_out',
                [StockMovementType::Out->value]
            )
            ->whereDate('moved_at', '>=', $range->dateFrom)
            ->whereDate('moved_at', '<=', $range->dateTo)
            ->when($request->filled('plant_id'), fn ($q) => $q->where('plant_id', $request->integer('plant_id')))
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->integer('product_id')))
            ->groupBy('plant_id', 'product_id');

        $balances = StockBalance::with(['plant', 'product.unit'])
            ->joinSub($movementSub, 'fy_movements', function ($join) {
                $join->on('stock_balances.plant_id', '=', 'fy_movements.plant_id')
                    ->on('stock_balances.product_id', '=', 'fy_movements.product_id');
            })
            ->select('stock_balances.*', 'fy_movements.fy_stock_in', 'fy_movements.fy_stock_out')
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->string('search');
                $q->whereHas('product', function ($pq) use ($search) {
                    $pq->where('name', 'like', '%'.$search.'%')
                        ->orWhere('sku', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('stock_balances.plant_id')
            ->paginate(30)
            ->withQueryString();

        return view('ho.inventory.stocks.index', [
            'balances' => $balances,
            'plants' => Plant::orderBy('name')->get(),
            'products' => Product::orderBy('name')->get(),
            'financialYearRange' => $range,
        ]);
    }

    public function show(Request $request): View
    {
        $data = $request->validate([
            'plant_id' => ['required', 'exists:plants,id'],
            'product_id' => ['required', 'exists:products,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $range = $this->fyRangeFromSession($request);
        $dates = $this->resolveDateFilter($request, $range);
        $plant = Plant::findOrFail($data['plant_id']);
        $product = Product::with('unit')->findOrFail($data['product_id']);

        $balance = StockBalance::where('plant_id', $plant->id)
            ->where('product_id', $product->id)
            ->first();

        $currentQty = $balance?->quantity ?? 0;

        // Calculate opening balance at the start of dates['from']
        $futureMovements = StockMovement::where('plant_id', $plant->id)
            ->where('product_id', $product->id)
            ->whereDate('moved_at', '>=', $dates['from'])
            ->selectRaw('SUM(CASE WHEN type = ? THEN quantity ELSE 0 END) as stock_in', [StockMovementType::In->value])
            ->selectRaw('SUM(CASE WHEN type = ? THEN quantity ELSE 0 END) as stock_out', [StockMovementType::Out->value])
            ->first();

        $openingBalance = $currentQty - (float) ($futureMovements->stock_in ?? 0) + (float) ($futureMovements->stock_out ?? 0);

        // Fetch movements in date range chronologically
        $movements = StockMovement::with(['subCategory', 'vehicle', 'performer', 'supplier'])
            ->where('plant_id', $plant->id)
            ->where('product_id', $product->id)
            ->whereDate('moved_at', '>=', $dates['from'])
            ->whereDate('moved_at', '<=', $dates['to'])
            ->orderBy('moved_at', 'asc')
            ->get();

        // Retrieve purchase items history for this product to map rates
        $latestRate = (float) (PurchaseItem::where('product_id', $product->id)
            ->latest('id')
            ->value('unit_price') ?? 0);

        $purchaseRates = PurchaseItem::query()
            ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->where('purchase_items.product_id', $product->id)
            ->select('purchases.ordered_at', 'purchase_items.unit_price', 'purchase_items.purchase_id')
            ->orderBy('purchases.ordered_at', 'desc')
            ->get();

        $getRateAtTime = function ($movedAt, $referenceId = null) use ($purchaseRates, $latestRate) {
            if ($referenceId && $match = $purchaseRates->firstWhere('purchase_id', $referenceId)) {
                return (float) $match->unit_price;
            }
            
            // Find the most recent purchase rate before or equal to movedAt
            $match = $purchaseRates->first(fn ($item) => $item->ordered_at <= $movedAt);
            if ($match) {
                return (float) $match->unit_price;
            }
            
            // Fallback to the oldest available rate or overall latest rate
            if ($purchaseRates->isNotEmpty()) {
                return (float) $purchaseRates->last()->unit_price;
            }
            
            return $latestRate;
        };

        // Determine opening rate and value
        $openingRate = $getRateAtTime($dates['from'] . ' 00:00:00');
        $openingValue = $openingBalance * $openingRate;

        // Calculate running balance and movement transaction values
        $runningBalance = $openingBalance;
        $totalValueIn = 0.0;
        $totalValueOut = 0.0;

        $movements->map(function ($m) use (&$runningBalance, &$totalValueIn, &$totalValueOut, $getRateAtTime) {
            $m->rate = $getRateAtTime($m->moved_at, $m->reference_id);
            $m->value = $m->quantity * $m->rate;

            if ($m->type === StockMovementType::In) {
                $runningBalance += $m->quantity;
                $totalValueIn += $m->value;
            } else {
                $runningBalance -= $m->quantity;
                $totalValueOut += $m->value;
            }
            $m->running_balance = $runningBalance;
            return $m;
        });

        $purchaseIds = $movements
            ->filter(fn ($m) => $m->reference_type === StockReferenceType::Purchase && $m->reference_id)
            ->pluck('reference_id')
            ->unique()
            ->values();

        $purchaseReferences = $purchaseIds->isNotEmpty()
            ? Purchase::whereIn('id', $purchaseIds)->pluck('reference_no', 'id')
            : collect();

        $periodStockIn = $movements->where('type', StockMovementType::In)->sum('quantity');
        $periodStockOut = $movements->where('type', StockMovementType::Out)->sum('quantity');

        // Grouped usage/consumption summary with quantity and value
        $usageSummary = $movements->where('type', StockMovementType::Out)
            ->groupBy(function ($m) {
                if ($m->vehicle) {
                    return 'Vehicle: '.$m->vehicle->displayLabel();
                }
                if ($m->subCategory) {
                    return 'Sub-Category: '.$m->subCategory->name;
                }
                if ($m->reason) {
                    return 'Reason: '.$m->reason;
                }

                return 'Other / Unspecified';
            })
            ->map(fn ($group) => [
                'quantity' => $group->sum('quantity'),
                'value' => $group->sum(fn ($m) => $m->quantity * $m->rate),
            ]);

        return view('ho.inventory.stocks.show', [
            'plant' => $plant,
            'product' => $product,
            'balance' => $balance,
            'movements' => $movements,
            'purchaseReferences' => $purchaseReferences,
            'financialYearRange' => $range,
            'dateFrom' => $dates['from'],
            'dateTo' => $dates['to'],
            'periodStockIn' => $periodStockIn,
            'periodStockOut' => $periodStockOut,
            'openingBalance' => $openingBalance,
            'openingRate' => $openingRate,
            'openingValue' => $openingValue,
            'totalValueIn' => $totalValueIn,
            'totalValueOut' => $totalValueOut,
            'latestRate' => $latestRate,
            'usageSummary' => $usageSummary,
        ]);
    }

    private function fyRangeFromSession(Request $request): FinancialYearRange
    {
        $label = app(FinancialYearResolver::class)->ensureSessionDefault($request);
        $fyDates = FinancialYear::range($label);

        return new FinancialYearRange(
            label: $label,
            dateFrom: $fyDates['from']->toDateString(),
            dateTo: $fyDates['to']->toDateString(),
            isManual: false,
        );
    }

    /**
     * @return array{from: string, to: string}
     */
    private function resolveDateFilter(Request $request, FinancialYearRange $fyRange): array
    {
        $from = $request->filled('date_from')
            ? $request->string('date_from')->toString()
            : $fyRange->dateFrom;
        $to = $request->filled('date_to')
            ? $request->string('date_to')->toString()
            : $fyRange->dateTo;

        if ($from < $fyRange->dateFrom) {
            $from = $fyRange->dateFrom;
        }
        if ($to > $fyRange->dateTo) {
            $to = $fyRange->dateTo;
        }
        if ($from > $to) {
            $from = $fyRange->dateFrom;
            $to = $fyRange->dateTo;
        }

        return ['from' => $from, 'to' => $to];
    }
}
