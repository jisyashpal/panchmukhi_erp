<?php

namespace App\Http\Controllers\Admin\Operation;

use App\Enums\PurchaseStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Site;
use App\Models\StockBalance;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $siteId = $request->integer('site_id') ?: null;

        $sitesQuery = Site::query()->where('is_active', true);
        $activeSites = (clone $sitesQuery)->count();
        $usersCount = User::where('is_active', true)->count();
        $productsCount = Product::where('is_active', true)->count();

        $purchasesQuery = Purchase::query()->with(['site', 'supplier']);
        $stockQuery = StockBalance::query();

        if ($siteId) {
            $purchasesQuery->where('site_id', $siteId);
            $stockQuery->where('site_id', $siteId);
        }

        $lowStock = StockBalance::query()
            ->with(['site', 'product'])
            ->when($siteId, fn ($q) => $q->where('stock_balances.site_id', $siteId))
            ->join('products', 'products.id', '=', 'stock_balances.product_id')
            ->whereColumn('stock_balances.quantity', '<=', 'products.reorder_level')
            ->select('stock_balances.*')
            ->limit(10)
            ->get();

        return view('admin.operation.dashboard', [
            'sites' => Site::orderBy('name')->get(),
            'siteId' => $siteId,
            'activeSites' => $activeSites,
            'usersCount' => $usersCount,
            'productsCount' => $productsCount,
            'totalStockLines' => (clone $stockQuery)->count(),
            'lowStock' => $lowStock,
            'recentPurchases' => $purchasesQuery->latest()->limit(5)->get(),
            'deliveredPurchases' => (clone $purchasesQuery)->where('status', PurchaseStatus::Delivered)->count(),
            'pendingOrders' => (clone $purchasesQuery)->where('status', PurchaseStatus::Ordered)->count(),
        ]);
    }
}
