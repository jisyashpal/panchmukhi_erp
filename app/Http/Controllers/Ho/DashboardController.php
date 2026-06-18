<?php

namespace App\Http\Controllers\Ho;

use App\Enums\PurchaseStatus;
use App\Enums\RequestStatus;
use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductRequest;
use App\Models\Purchase;
use App\Models\PurchaseRequest;
use App\Models\Site;
use App\Models\StockBalance;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $siteId = $request->integer('site_id') ?: null;

        $stockQuery = StockBalance::with(['site', 'product']);
        $purchaseQuery = Purchase::query();
        $movementIn = StockMovement::where('type', StockMovementType::In);
        $movementOut = StockMovement::where('type', StockMovementType::Out);

        if ($siteId) {
            $stockQuery->where('site_id', $siteId);
            $purchaseQuery->where('site_id', $siteId);
            $movementIn->where('site_id', $siteId);
            $movementOut->where('site_id', $siteId);
        }

        $lowStock = (clone $stockQuery)
            ->join('products', 'products.id', '=', 'stock_balances.product_id')
            ->whereColumn('stock_balances.quantity', '<=', 'products.reorder_level')
            ->select('stock_balances.*')
            ->limit(8)
            ->get();

        return view('ho.dashboard', [
            'sites' => Site::orderBy('name')->get(),
            'siteId' => $siteId,
            'totalStockValue' => (clone $stockQuery)->sum('quantity'),
            'pendingOrders' => (clone $purchaseQuery)->where('status', PurchaseStatus::Ordered)->count(),
            'confirmedOrders' => (clone $purchaseQuery)->where('status', PurchaseStatus::Delivered)->count(),
            'totalPurchaseAmount' => (clone $purchaseQuery)->sum('grand_total'),
            'totalPurchaseQty' => Purchase::query()
                ->when($siteId, fn ($q) => $q->where('site_id', $siteId))
                ->join('purchase_items', 'purchase_items.purchase_id', '=', 'purchases.id')
                ->sum('purchase_items.quantity'),
            'stockInCount' => (clone $movementIn)->count(),
            'stockOutCount' => (clone $movementOut)->count(),
            'submittedProductRequests' => ProductRequest::when($siteId, fn ($q) => $q->where('site_id', $siteId))
                ->where('status', RequestStatus::Submitted)->count(),
            'submittedPurchaseRequests' => PurchaseRequest::when($siteId, fn ($q) => $q->where('site_id', $siteId))
                ->where('status', RequestStatus::Submitted)->count(),
            'lowStock' => $lowStock,
            'topStock' => (clone $stockQuery)->orderByDesc('quantity')->limit(8)->get(),
            'recentPurchases' => (clone $purchaseQuery)->with(['site', 'supplier'])->latest()->limit(5)->get(),
        ]);
    }
}
