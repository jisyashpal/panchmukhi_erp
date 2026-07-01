<?php

namespace App\Http\Controllers\Admin\Operation;

use App\Enums\PurchaseStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Plant;
use App\Models\StockBalance;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $plantId = $request->integer('plant_id') ?: null;

        $plantsQuery = Plant::query()->where('is_active', true);
        $activePlants = (clone $plantsQuery)->count();
        $usersCount = User::where('is_active', true)->count();
        $productsCount = Product::where('is_active', true)->count();

        $purchasesQuery = Purchase::query()->with(['plant', 'supplier']);
        $stockQuery = StockBalance::query();

        if ($plantId) {
            $purchasesQuery->where('plant_id', $plantId);
            $stockQuery->where('plant_id', $plantId);
        }

        $lowStock = StockBalance::query()
            ->with(['plant', 'product'])
            ->when($plantId, fn ($q) => $q->where('stock_balances.plant_id', $plantId))
            ->join('products', 'products.id', '=', 'stock_balances.product_id')
            ->whereColumn('stock_balances.quantity', '<=', 'products.reorder_level')
            ->select('stock_balances.*')
            ->limit(10)
            ->get();

        return view('admin.operation.dashboard', [
            'plants' => Plant::orderBy('name')->get(),
            'plantId' => $plantId,
            'activePlants' => $activePlants,
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
