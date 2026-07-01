<?php

namespace App\Http\Controllers\Plant\Inventory;

use App\Http\Controllers\Concerns\BelongsToUserPlant;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockBalance;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockController extends Controller
{
    use BelongsToUserPlant;

    public function index(Request $request): View
    {
        $plantId = $this->plantId();
        $selectedProductId = $request->filled('product_id') ? $request->integer('product_id') : null;

        $balances = StockBalance::with(['product.unit'])
            ->where('plant_id', $plantId)
            ->when($selectedProductId, fn ($q) => $q->where('product_id', $selectedProductId))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->string('search');
                $q->whereHas('product', function ($pq) use ($search) {
                    $pq->where('name', 'like', '%'.$search.'%')
                       ->orWhere('sku', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('quantity', 'desc')
            ->paginate(30)
            ->withQueryString();

        $selectedProduct = $selectedProductId
            ? Product::with('unit')->find($selectedProductId)
            : null;

        return view('plant.inventory.stocks.index', [
            'balances' => $balances,
            'products' => Product::orderBy('name')->get(),
            'selectedProduct' => $selectedProduct,
        ]);
    }
}
