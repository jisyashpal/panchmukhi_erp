<?php

namespace App\Services;

use App\Models\ProductUseEntry;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function productLifeUsage(?int $siteId = null): \Illuminate\Support\Collection
    {
        return ProductUseEntry::query()
            ->with(['site', 'product.category', 'machine', 'recorder'])
            ->when($siteId, fn ($q) => $q->where('site_id', $siteId))
            ->latest('used_at')
            ->limit(100)
            ->get();
    }

    public function categoryWisePurchaseAmount(?int $siteId = null): \Illuminate\Support\Collection
    {
        return PurchaseItem::query()
            ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->join('products', 'products.id', '=', 'purchase_items.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->when($siteId, fn ($q) => $q->where('purchases.site_id', $siteId))
            ->groupBy('categories.id', 'categories.name')
            ->selectRaw('categories.name as category_name, SUM(purchase_items.line_total) as total_amount, SUM(purchase_items.quantity) as total_qty')
            ->orderByDesc('total_amount')
            ->get();
    }

    public function multipleUsageHighlights(?int $siteId = null): \Illuminate\Support\Collection
    {
        return ProductUseEntry::query()
            ->select('product_id', DB::raw('COUNT(*) as use_count'), DB::raw('SUM(quantity) as total_qty'))
            ->when($siteId, fn ($q) => $q->where('site_id', $siteId))
            ->groupBy('product_id')
            ->having('use_count', '>', 1)
            ->with('product')
            ->orderByDesc('use_count')
            ->limit(20)
            ->get();
    }

    public function productUsageByCategory(?int $siteId = null): \Illuminate\Support\Collection
    {
        return ProductUseEntry::query()
            ->join('products', 'products.id', '=', 'product_use_entries.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->when($siteId, fn ($q) => $q->where('product_use_entries.site_id', $siteId))
            ->groupBy('categories.id', 'categories.name')
            ->selectRaw('categories.name as category_name, SUM(product_use_entries.quantity) as total_qty, SUM(product_use_entries.life_hours) as total_life_hours')
            ->orderByDesc('total_qty')
            ->get();
    }

    public function supplierWisePurchaseTotals(?int $siteId = null): \Illuminate\Support\Collection
    {
        return Purchase::query()
            ->with('supplier')
            ->when($siteId, fn ($q) => $q->where('site_id', $siteId))
            ->selectRaw('supplier_id, SUM(grand_total) as total_amount, COUNT(*) as order_count')
            ->groupBy('supplier_id')
            ->orderByDesc('total_amount')
            ->get();
    }
}
