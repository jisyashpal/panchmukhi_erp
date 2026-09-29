<?php

namespace App\Http\Controllers\Plant\Inventory;

use App\Http\Controllers\Concerns\BelongsToUserPlant;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Plant;
use App\Models\Product;
use App\Models\StockBalance;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockController extends Controller
{
    use BelongsToUserPlant;

    public function index(Request $request): View
    {
        $isPurchaser = $this->purchaserSeesAllPlants();
        $plantId = $isPurchaser
            ? ($request->filled('plant_id') ? $request->integer('plant_id') : null)
            : $this->plantId();
        $selectedProductId = $request->filled('product_id') ? $request->integer('product_id') : null;
        $selectedCategoryId = $request->filled('category_id') ? $request->integer('category_id') : null;
        $search = $request->filled('search') ? trim($request->string('search')->toString()) : null;

        $balances = StockBalance::with(['product.unit', 'product.category', 'product.subCategory', 'plant'])
            ->when($plantId, fn ($q) => $q->where('plant_id', $plantId))
            ->when($selectedProductId, fn ($q) => $q->where('product_id', $selectedProductId))
            ->when($selectedCategoryId, function ($q) use ($selectedCategoryId) {
                $q->whereHas('product', fn ($pq) => $pq->where('category_id', $selectedCategoryId));
            })
            ->when($search && ! $selectedProductId, function ($q) use ($search) {
                $term = '%'.$search.'%';
                $q->whereHas('product', function ($pq) use ($term) {
                    $pq->where(function ($inner) use ($term) {
                        $inner->where('name', 'like', $term)
                            ->orWhere('sku', 'like', $term);
                    });
                });
            })
            ->orderBy('quantity', 'desc')
            ->paginate(30)
            ->withQueryString();

        $stockedProducts = Product::query()
            ->whereHas('stockBalances', function ($q) use ($plantId) {
                $q->when($plantId, fn ($q) => $q->where('plant_id', $plantId));
            });

        $selectedProduct = $selectedProductId
            ? Product::with(['unit', 'category', 'subCategory'])->find($selectedProductId)
            : null;

        $products = (clone $stockedProducts)
            ->with(['category:id,name', 'subCategory:id,name'])
            ->when($selectedCategoryId, fn ($q) => $q->where('category_id', $selectedCategoryId))
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'category_id', 'sub_category_id']);

        if ($selectedProduct && ! $products->contains('id', $selectedProduct->id)) {
            $products->prepend($selectedProduct);
        }

        return view('plant.inventory.stocks.index', [
            'balances' => $balances,
            'products' => $products,
            'categories' => Category::query()
                ->whereHas('products', function ($q) use ($plantId) {
                    $q->whereHas('stockBalances', function ($q) use ($plantId) {
                        $q->when($plantId, fn ($q) => $q->where('plant_id', $plantId));
                    });
                })
                ->orderBy('name')
                ->get(['id', 'name']),
            'selectedProduct' => $selectedProduct,
            'isPurchaser' => $isPurchaser,
            'plants' => $isPurchaser ? Plant::orderBy('name')->get() : collect(),
            'currentPlant' => $isPurchaser ? null : auth()->user()?->plant,
        ]);
    }
}
