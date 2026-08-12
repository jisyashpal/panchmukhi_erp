<?php

namespace App\Http\Controllers\Plant\Operation;

use App\Enums\PurchaseStatus;
use App\Enums\RequestStatus;
use App\Http\Controllers\Concerns\BelongsToUserPlant;
use App\Http\Controllers\Controller;
use App\Models\ProductRequest;
use App\Models\Purchase;
use App\Models\StockBalance;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use BelongsToUserPlant;

    public function __invoke(): View
    {
        $user = auth()->user();
        $user->loadMissing('plant');
        $isPurchaser = $this->purchaserSeesAllPlants();
        $plantId = $isPurchaser ? null : $this->plantId();

        $lowStockQuery = StockBalance::query()
            ->with(['product', 'plant'])
            ->when($plantId, fn ($q) => $q->where('plant_id', $plantId))
            ->join('products', 'products.id', '=', 'stock_balances.product_id')
            ->whereColumn('stock_balances.quantity', '<=', 'products.reorder_level')
            ->select('stock_balances.*')
            ->limit(10);

        $stockLinesQuery = StockBalance::with(['product.unit', 'plant'])
            ->when($plantId, fn ($q) => $q->where('plant_id', $plantId))
            ->orderByDesc('quantity')
            ->limit(8);

        $data = [
            'plant' => $isPurchaser ? null : $user->plant,
            'isPurchaser' => $isPurchaser,
            'stockLines' => $stockLinesQuery->get(),
            'lowStock' => $lowStockQuery->get(),
            'submittedProductRequests' => ProductRequest::query()
                ->when($plantId, fn ($q) => $q->where('plant_id', $plantId))
                ->where('status', RequestStatus::Submitted)
                ->count(),
            'totalPurchases' => Purchase::query()
                ->when($plantId, fn ($q) => $q->where('plant_id', $plantId))
                ->count(),
            'totalStockLines' => StockBalance::query()
                ->when($plantId, fn ($q) => $q->where('plant_id', $plantId))
                ->where('quantity', '>', 0)
                ->count(),
            'totalStockQty' => (float) StockBalance::query()
                ->when($plantId, fn ($q) => $q->where('plant_id', $plantId))
                ->sum('quantity'),
            'awaitingDispatch' => Purchase::query()
                ->when($plantId, fn ($q) => $q->where('plant_id', $plantId))
                ->where('status', PurchaseStatus::Ordered)
                ->whereNull('dispatched_at')
                ->count(),
            'awaitingDelivery' => Purchase::query()
                ->when($plantId, fn ($q) => $q->where('plant_id', $plantId))
                ->where('status', PurchaseStatus::Ordered)
                ->whereNotNull('dispatched_at')
                ->count(),
        ];

        return view('plant.operation.dashboard', $data);
    }
}
