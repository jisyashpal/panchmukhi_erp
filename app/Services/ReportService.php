<?php

namespace App\Services;

use App\Models\ProductUseEntry;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Support\ReportFilters;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function productLifeUsage(?ReportFilters $filters = null, bool $forExport = false): \Illuminate\Support\Collection
    {
        $filters ??= new ReportFilters;

        return ProductUseEntry::query()
            ->with(['site', 'product.category', 'machine', 'recorder'])
            ->when($filters->siteId, fn ($q) => $q->where('site_id', $filters->siteId))
            ->when($filters->productId, fn ($q) => $q->where('product_id', $filters->productId))
            ->when($filters->categoryId, fn ($q) => $q->whereHas(
                'product',
                fn ($pq) => $pq->where('category_id', $filters->categoryId)
            ))
            ->when($filters->dateFrom, fn ($q) => $q->whereDate('used_at', '>=', $filters->dateFrom))
            ->when($filters->dateTo, fn ($q) => $q->whereDate('used_at', '<=', $filters->dateTo))
            ->when($filters->search, function ($q) use ($filters) {
                $term = '%'.$filters->search.'%';
                $q->where(function ($q) use ($term) {
                    $q->whereHas('product', fn ($pq) => $pq->where('name', 'like', $term))
                        ->orWhereHas('machine', fn ($mq) => $mq->where('name', 'like', $term))
                        ->orWhereHas('site', fn ($sq) => $sq->where('name', 'like', $term));
                });
            })
            ->latest('used_at')
            ->when(! $forExport, fn ($q) => $q->limit(500))
            ->get();
    }

    public function categoryWisePurchaseAmount(?ReportFilters $filters = null): \Illuminate\Support\Collection
    {
        $filters ??= new ReportFilters;

        return PurchaseItem::query()
            ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->join('products', 'products.id', '=', 'purchase_items.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->when($filters->siteId, fn ($q) => $q->where('purchases.site_id', $filters->siteId))
            ->when($filters->categoryId, fn ($q) => $q->where('categories.id', $filters->categoryId))
            ->when($filters->dateFrom, fn ($q) => $q->whereDate('purchases.ordered_at', '>=', $filters->dateFrom))
            ->when($filters->dateTo, fn ($q) => $q->whereDate('purchases.ordered_at', '<=', $filters->dateTo))
            ->when($filters->search, fn ($q) => $q->where('categories.name', 'like', '%'.$filters->search.'%'))
            ->groupBy('categories.id', 'categories.name')
            ->selectRaw('categories.name as category_name, SUM(purchase_items.line_total) as total_amount, SUM(purchase_items.quantity) as total_qty')
            ->orderByDesc('total_amount')
            ->get();
    }

    public function multipleUsageHighlights(?ReportFilters $filters = null, bool $forExport = false): \Illuminate\Support\Collection
    {
        $filters ??= new ReportFilters;

        return ProductUseEntry::query()
            ->select('product_id', DB::raw('COUNT(*) as use_count'), DB::raw('SUM(quantity) as total_qty'))
            ->when($filters->siteId, fn ($q) => $q->where('site_id', $filters->siteId))
            ->when($filters->productId, fn ($q) => $q->where('product_id', $filters->productId))
            ->when($filters->categoryId, function ($q) use ($filters) {
                $q->whereIn('product_id', function ($sub) use ($filters) {
                    $sub->select('id')
                        ->from('products')
                        ->where('category_id', $filters->categoryId);
                });
            })
            ->when($filters->dateFrom, fn ($q) => $q->whereDate('used_at', '>=', $filters->dateFrom))
            ->when($filters->dateTo, fn ($q) => $q->whereDate('used_at', '<=', $filters->dateTo))
            ->when($filters->search, function ($q) use ($filters) {
                $q->whereIn('product_id', function ($sub) use ($filters) {
                    $sub->select('id')
                        ->from('products')
                        ->where('name', 'like', '%'.$filters->search.'%');
                });
            })
            ->groupBy('product_id')
            ->having('use_count', '>', 1)
            ->with('product')
            ->orderByDesc('use_count')
            ->when(! $forExport, fn ($q) => $q->limit(100))
            ->get();
    }

    public function productUsageByCategory(?ReportFilters $filters = null): \Illuminate\Support\Collection
    {
        $filters ??= new ReportFilters;

        return ProductUseEntry::query()
            ->join('products', 'products.id', '=', 'product_use_entries.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->when($filters->siteId, fn ($q) => $q->where('product_use_entries.site_id', $filters->siteId))
            ->when($filters->categoryId, fn ($q) => $q->where('categories.id', $filters->categoryId))
            ->when($filters->dateFrom, fn ($q) => $q->whereDate('product_use_entries.used_at', '>=', $filters->dateFrom))
            ->when($filters->dateTo, fn ($q) => $q->whereDate('product_use_entries.used_at', '<=', $filters->dateTo))
            ->when($filters->search, fn ($q) => $q->where('categories.name', 'like', '%'.$filters->search.'%'))
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
