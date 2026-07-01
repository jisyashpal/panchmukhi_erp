<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Plant;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockCheckController extends Controller
{
    public function __invoke(Request $request, Plant $plant, Product $product, StockService $stockService): JsonResponse
    {
        $user = $request->user();
        if ($user && ! $user->isSuperAdmin() && ! $user->isHoDataOperator() && (int) $user->plant_id !== $plant->id) {
            abort(403);
        }

        return response()->json([
            'plant_id' => $plant->id,
            'product_id' => $product->id,
            'quantity' => $stockService->getBalance($plant->id, $product->id),
        ]);
    }
}
