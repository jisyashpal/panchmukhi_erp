<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Site;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockCheckController extends Controller
{
    public function __invoke(Request $request, Site $site, Product $product, StockService $stockService): JsonResponse
    {
        $user = $request->user();
        if ($user && ! $user->isSuperAdmin() && ! $user->isHoDataOperator() && (int) $user->site_id !== $site->id) {
            abort(403);
        }

        return response()->json([
            'site_id' => $site->id,
            'product_id' => $product->id,
            'quantity' => $stockService->getBalance($site->id, $product->id),
        ]);
    }
}
