<?php

namespace App\Http\Controllers\Ho\Operation;

use App\Enums\PurchaseStatus;
use App\Enums\RequestStatus;
use App\Enums\StockMovementType;
use App\Enums\StockReferenceType;
use App\Http\Controllers\Controller;
use App\Models\ProductRequest;
use App\Models\Purchase;
use App\Models\PurchaseRequest;
use App\Models\Plant;
use App\Models\StockBalance;
use App\Support\FinancialYearResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $plantId = $request->integer('plant_id') ?: null;
        $resolver = app(FinancialYearResolver::class);
        $range = $resolver->resolve($request);

        $stockQuery = StockBalance::with(['plant', 'product']);
        $purchaseQuery = Purchase::query();
        $productRequestQuery = ProductRequest::query();
        $purchaseRequestQuery = PurchaseRequest::query();

        if ($plantId) {
            $stockQuery->where('plant_id', $plantId);
            $purchaseQuery->where('plant_id', $plantId);
            $productRequestQuery->where('plant_id', $plantId);
            $purchaseRequestQuery->where('plant_id', $plantId);
        }

        $resolver->applyToQuery($purchaseQuery, 'ordered_at', $request);
        $resolver->applyToQuery($productRequestQuery, 'created_at', $request);
        $resolver->applyToQuery($purchaseRequestQuery, 'created_at', $request);

        $lowStock = (clone $stockQuery)
            ->join('products', 'products.id', '=', 'stock_balances.product_id')
            ->whereColumn('stock_balances.quantity', '<=', 'products.reorder_level')
            ->select('stock_balances.*')
            ->limit(8)
            ->get();

        $purchaseQtyQuery = Purchase::query()
            ->when($plantId, fn ($q) => $q->where('plant_id', $plantId))
            ->join('purchase_items', 'purchase_items.purchase_id', '=', 'purchases.id');
        $resolver->applyToQuery($purchaseQtyQuery, 'purchases.ordered_at', $request);

        // Category-wise Stock In qty + amount
        $categoryInQuery = DB::table('stock_movements as sm')
            ->join('products as p', 'p.id', '=', 'sm.product_id')
            ->join('categories as c', 'c.id', '=', 'p.category_id')
            ->join('purchase_items as pi', fn ($j) => $j
                ->on('pi.purchase_id', '=', 'sm.reference_id')
                ->on('pi.product_id', '=', 'sm.product_id')
            )
            ->where('sm.type', StockMovementType::In->value)
            ->where('sm.reference_type', StockReferenceType::Purchase->value)
            ->when($plantId, fn ($q) => $q->where('sm.plant_id', $plantId))
            ->select('c.name as category', DB::raw('SUM(sm.quantity) as in_qty'), DB::raw('SUM(pi.line_total) as in_amount'))
            ->groupBy('c.id', 'c.name')
            ->orderByDesc('in_amount')
            ->whereDate('sm.moved_at', '>=', $range->dateFrom)
            ->whereDate('sm.moved_at', '<=', $range->dateTo);
        $categoryStockIn = $categoryInQuery->get();

        // Category-wise Stock Out qty
        $categoryOutQuery = DB::table('stock_movements as sm')
            ->join('products as p', 'p.id', '=', 'sm.product_id')
            ->join('categories as c', 'c.id', '=', 'p.category_id')
            ->where('sm.type', StockMovementType::Out->value)
            ->when($plantId, fn ($q) => $q->where('sm.plant_id', $plantId))
            ->select('c.name as category', DB::raw('SUM(sm.quantity) as out_qty'))
            ->groupBy('c.id', 'c.name')
            ->orderByDesc('out_qty')
            ->whereDate('sm.moved_at', '>=', $range->dateFrom)
            ->whereDate('sm.moved_at', '<=', $range->dateTo);
        $categoryStockOut = $categoryOutQuery->get()->keyBy('category');

        // Merge category in+out
        $categoryBreakdown = $categoryStockIn->map(function ($row) use ($categoryStockOut) {
            $out = $categoryStockOut->get($row->category);
            $row->out_qty = $out?->out_qty ?? 0;
            return $row;
        });
        // Add categories that only have out movements
        foreach ($categoryStockOut as $cat => $out) {
            if (! $categoryBreakdown->contains('category', $cat)) {
                $categoryBreakdown->push((object) [
                    'category'   => $cat,
                    'in_qty'     => 0,
                    'in_amount'  => 0,
                    'out_qty'    => $out->out_qty,
                ]);
            }
        }

        return view('ho.operation.dashboard', [
            'plants'                    => Plant::orderBy('name')->get(),
            'plantId'                   => $plantId,
            'financialYearRange'        => $range,
            'totalStockValue'           => (clone $stockQuery)->sum('quantity'),
            'pendingOrders'             => (clone $purchaseQuery)->where('status', PurchaseStatus::Ordered)->count(),
            'confirmedOrders'           => (clone $purchaseQuery)->where('status', PurchaseStatus::Delivered)->count(),
            'totalPurchaseAmount'       => (clone $purchaseQuery)->sum('grand_total'),
            'totalPurchaseQty'          => $purchaseQtyQuery->sum('purchase_items.quantity'),
            'categoryBreakdown'         => $categoryBreakdown,
            'submittedProductRequests'  => (clone $productRequestQuery)
                ->where('status', RequestStatus::Submitted)->count(),
            'submittedPurchaseRequests' => (clone $purchaseRequestQuery)
                ->where('status', RequestStatus::Submitted)->count(),
            'lowStock'                  => $lowStock,
            'topStock'                  => (clone $stockQuery)->orderByDesc('quantity')->limit(8)->get(),
            'recentPurchases'           => (clone $purchaseQuery)->with(['plant', 'supplier'])->latest('ordered_at')->limit(5)->get(),
        ]);
    }
}
