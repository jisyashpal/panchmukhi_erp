<?php

namespace App\Http\Controllers\Plant\Operation;

use App\Enums\PurchaseStatus;
use App\Enums\RequestStatus;
use App\Http\Controllers\Concerns\BelongsToUserPlant;
use App\Http\Controllers\Controller;
use App\Models\ProductRequest;
use App\Models\Purchase;
use App\Models\PurchaseRequest;
use App\Models\StockBalance;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use BelongsToUserPlant;

    public function __invoke(): View
    {
        $plantId = $this->plantId();
        $user = auth()->user();
        $user->loadMissing('plant');

        $lowStock = StockBalance::query()
            ->with(['product'])
            ->where('plant_id', $plantId)
            ->join('products', 'products.id', '=', 'stock_balances.product_id')
            ->whereColumn('stock_balances.quantity', '<=', 'products.reorder_level')
            ->select('stock_balances.*')
            ->limit(10)
            ->get();

        $data = [
            'plant' => $user->plant,
            'stockLines' => StockBalance::with(['product.unit'])
                ->where('plant_id', $plantId)
                ->orderByDesc('quantity')
                ->limit(8)
                ->get(),
            'lowStock' => $lowStock,
            'submittedProductRequests' => ProductRequest::where('plant_id', $plantId)
                ->where('status', RequestStatus::Submitted)->count(),
            'submittedPurchaseRequests' => PurchaseRequest::where('plant_id', $plantId)
                ->where('status', RequestStatus::Submitted)->count(),
            'awaitingDispatch' => Purchase::where('plant_id', $plantId)
                ->where('status', PurchaseStatus::Ordered)
                ->whereNull('dispatched_at')
                ->count(),
            'awaitingDelivery' => Purchase::where('plant_id', $plantId)
                ->where('status', PurchaseStatus::Ordered)
                ->whereNotNull('dispatched_at')
                ->count(),
        ];

        return view('plant.operation.dashboard', $data);
    }
}
