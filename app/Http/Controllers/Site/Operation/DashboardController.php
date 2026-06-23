<?php

namespace App\Http\Controllers\Site\Operation;

use App\Enums\PurchaseStatus;
use App\Enums\RequestStatus;
use App\Http\Controllers\Concerns\BelongsToUserSite;
use App\Http\Controllers\Controller;
use App\Models\ProductRequest;
use App\Models\Purchase;
use App\Models\PurchaseRequest;
use App\Models\StockBalance;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use BelongsToUserSite;

    public function __invoke(): View
    {
        $siteId = $this->siteId();
        $user = auth()->user();
        $user->loadMissing('site');

        $lowStock = StockBalance::query()
            ->with(['product'])
            ->where('site_id', $siteId)
            ->join('products', 'products.id', '=', 'stock_balances.product_id')
            ->whereColumn('stock_balances.quantity', '<=', 'products.reorder_level')
            ->select('stock_balances.*')
            ->limit(10)
            ->get();

        $data = [
            'site' => $user->site,
            'stockLines' => StockBalance::with(['product.unit'])
                ->where('site_id', $siteId)
                ->orderByDesc('quantity')
                ->limit(8)
                ->get(),
            'lowStock' => $lowStock,
            'submittedProductRequests' => ProductRequest::where('site_id', $siteId)
                ->where('status', RequestStatus::Submitted)->count(),
            'submittedPurchaseRequests' => PurchaseRequest::where('site_id', $siteId)
                ->where('status', RequestStatus::Submitted)->count(),
            'awaitingDispatch' => Purchase::where('site_id', $siteId)
                ->where('status', PurchaseStatus::Ordered)
                ->whereNull('dispatched_at')
                ->count(),
            'awaitingDelivery' => Purchase::where('site_id', $siteId)
                ->where('status', PurchaseStatus::Ordered)
                ->whereNotNull('dispatched_at')
                ->count(),
        ];

        return view('site.operation.dashboard', $data);
    }
}
