<?php

namespace App\Http\Controllers\Ho\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Site;
use App\Models\StockBalance;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockController extends Controller
{
    public function index(Request $request): View
    {
        $balances = StockBalance::with(['site', 'product.unit'])
            ->when($request->filled('site_id'), fn ($q) => $q->where('site_id', $request->integer('site_id')))
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->integer('product_id')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->string('search');
                $q->whereHas('product', function ($pq) use ($search) {
                    $pq->where('name', 'like', '%'.$search.'%')
                       ->orWhere('sku', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('site_id')
            ->paginate(30)
            ->withQueryString();

        return view('ho.inventory.stocks.index', [
            'balances' => $balances,
            'sites' => Site::orderBy('name')->get(),
            'products' => Product::orderBy('name')->get(),
        ]);
    }
}
