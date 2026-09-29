<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Enums\StockReferenceType;
use App\Models\ProductUseEntry;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Models\PurchaseItem;
use App\Models\SupplierPayment;
use App\Support\ReportFilters;
use Illuminate\Support\Facades\DB;

class ReportService
{
    private function categoryTopItemsAggregate(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => 'REPLACE(GROUP_CONCAT(DISTINCT products.name), ",", ", ")',
            'pgsql' => 'STRING_AGG(DISTINCT products.name, ", " ORDER BY products.name)',
            default => 'GROUP_CONCAT(DISTINCT products.name ORDER BY products.name SEPARATOR ", ")',
        };
    }

    public function productLifeUsage(?ReportFilters $filters = null, bool $forExport = false): \Illuminate\Support\Collection
    {
        $filters ??= new ReportFilters;

        $movements = StockMovement::query()
            ->with(['plant', 'vehicle', 'product.category', 'subCategory', 'performer'])
            ->where('type', StockMovementType::Out)
            ->when($filters->plantId, fn ($q) => $q->where('plant_id', $filters->plantId))
            ->when($filters->productId, fn ($q) => $q->where('product_id', $filters->productId))
            ->when($filters->vehicleId, fn ($q) => $q->where('vehicle_id', $filters->vehicleId))
            ->when($filters->categoryId, fn ($q) => $q->whereHas(
                'product',
                fn ($pq) => $pq->where('category_id', $filters->categoryId)
            ))
            ->when($filters->dateFrom, fn ($q) => $q->whereDate('moved_at', '>=', $filters->dateFrom))
            ->when($filters->dateTo, fn ($q) => $q->whereDate('moved_at', '<=', $filters->dateTo))
            ->when($filters->search, function ($q) use ($filters) {
                $term = '%'.$filters->search.'%';
                $q->where(function ($q) use ($term) {
                    $q->whereHas('product', fn ($pq) => $pq->where('name', 'like', $term)->orWhere('sku', 'like', $term))
                        ->orWhereHas('subCategory', fn ($sq) => $sq->where('name', 'like', $term))
                        ->orWhereHas('plant', fn ($sq) => $sq->where('name', 'like', $term))
                        ->orWhereHas('vehicle', function ($vq) use ($term) {
                            $vq->where('name', 'like', $term)
                                ->orWhere('registration_no', 'like', $term);
                        });
                });
            })
            ->latest('moved_at')
            ->latest('id')
            ->when(! $forExport, fn ($q) => $q->limit(500))
            ->get();

        $useIds = $movements
            ->filter(fn (StockMovement $movement) => $movement->reference_type === StockReferenceType::ProductUse && $movement->reference_id)
            ->pluck('reference_id')
            ->unique()
            ->values();

        $uses = $useIds->isEmpty()
            ? collect()
            : ProductUseEntry::with(['replacedProduct', 'subCategory'])->whereIn('id', $useIds)->get()->keyBy('id');

        return $movements->each(function (StockMovement $movement) use ($uses) {
            $use = $movement->reference_type === StockReferenceType::ProductUse
                ? $uses->get($movement->reference_id)
                : null;

            $movement->setRelation('productUse', $use);
        });
    }

    public function categoryWisePurchaseAmount(?ReportFilters $filters = null): \Illuminate\Support\Collection
    {
        $filters ??= new ReportFilters;

        $topItemsAggregate = $this->categoryTopItemsAggregate();

        return PurchaseItem::query()
            ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->join('products', 'products.id', '=', 'purchase_items.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->when($filters->plantId, fn ($q) => $q->where('purchases.plant_id', $filters->plantId))
            ->when($filters->categoryId, fn ($q) => $q->where('categories.id', $filters->categoryId))
            ->when($filters->dateFrom, fn ($q) => $q->whereDate('purchases.ordered_at', '>=', $filters->dateFrom))
            ->when($filters->dateTo, fn ($q) => $q->whereDate('purchases.ordered_at', '<=', $filters->dateTo))
            ->when($filters->search, fn ($q) => $q->where('categories.name', 'like', '%'.$filters->search.'%'))
            ->groupBy('categories.id', 'categories.name')
            ->selectRaw(
                'categories.id as category_id,
                 categories.name as category_name,
                 SUM(purchase_items.line_total) as total_amount,
                 SUM(purchase_items.quantity) as total_qty,
                 COUNT(DISTINCT purchases.id) as order_count,
                 COUNT(DISTINCT purchase_items.product_id) as product_count,
                  '.$topItemsAggregate.' as top_items'
            )
            ->orderByDesc('total_amount')
            ->get();
    }

    public function productWisePurchaseAmount(?ReportFilters $filters = null): \Illuminate\Support\Collection
    {
        $filters ??= new ReportFilters;

        return PurchaseItem::query()
            ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->join('products', 'products.id', '=', 'purchase_items.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('units', 'units.id', '=', 'products.unit_id')
            ->when($filters->plantId, fn ($q) => $q->where('purchases.plant_id', $filters->plantId))
            ->when($filters->categoryId, fn ($q) => $q->where('categories.id', $filters->categoryId))
            ->when($filters->productId, fn ($q) => $q->where('products.id', $filters->productId))
            ->when($filters->dateFrom, fn ($q) => $q->whereDate('purchases.ordered_at', '>=', $filters->dateFrom))
            ->when($filters->dateTo, fn ($q) => $q->whereDate('purchases.ordered_at', '<=', $filters->dateTo))
            ->when($filters->search, function ($q) use ($filters) {
                $term = '%'.$filters->search.'%';
                $q->where(function ($q) use ($term) {
                    $q->where('products.name', 'like', $term)
                        ->orWhere('products.sku', 'like', $term)
                        ->orWhere('categories.name', 'like', $term);
                });
            })
            ->groupBy(
                'products.id',
                'products.name',
                'products.sku',
                'categories.id',
                'categories.name',
                'units.abbreviation',
                'units.name'
            )
            ->selectRaw(
                'products.id as product_id,
                 products.name as product_name,
                 products.sku as sku,
                 categories.id as category_id,
                 categories.name as category_name,
                 COALESCE(units.abbreviation, units.name) as unit,
                 SUM(purchase_items.line_total) as total_amount,
                 SUM(purchase_items.quantity) as total_qty,
                 COUNT(DISTINCT purchases.id) as order_count,
                 COUNT(DISTINCT purchases.supplier_id) as supplier_count,
                 CASE WHEN SUM(purchase_items.quantity) > 0
                      THEN ROUND(SUM(purchase_items.line_total) / SUM(purchase_items.quantity), 2)
                      ELSE 0 END as avg_unit_price,
                 MAX(purchases.ordered_at) as last_purchased_at'
            )
            ->orderByDesc('total_amount')
            ->get();
    }

    /**
     * Date-wise purchase register: every PO line item as its own row.
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection
     */
    public function purchaseItemRegister(?ReportFilters $filters = null, bool $forExport = false, bool $oldestFirst = false)
    {
        $query = $this->purchaseItemRegisterQuery($filters)
            ->select(
                'purchase_items.id',
                'purchases.reference_no',
                'purchases.ordered_at',
                'purchases.id as purchase_id',
                'purchases.status',
                'plants.name as plant_name',
                'suppliers.name as supplier_name',
                'products.id as product_id',
                'products.name as product_name',
                'products.sku as sku',
                'categories.name as category_name',
                'purchase_items.quantity',
                'purchase_items.unit_price',
                'purchase_items.line_total'
            )
            ->selectRaw('COALESCE(units.abbreviation, units.name) as unit');

        if ($oldestFirst) {
            $query->orderBy('purchases.ordered_at')
                ->orderBy('purchases.id')
                ->orderBy('purchase_items.id');
        } else {
            $query->orderByDesc('purchases.ordered_at')
                ->orderByDesc('purchases.id')
                ->orderBy('purchase_items.id');
        }

        if ($forExport) {
            return $query->get();
        }

        return $query->paginate(50)->withQueryString();
    }

    public function purchaseItemRegisterTotals(?ReportFilters $filters = null): object
    {
        $row = $this->purchaseItemRegisterQuery($filters)
            ->selectRaw(
                'COUNT(purchase_items.id) as line_count,
                 SUM(purchase_items.quantity) as total_qty,
                 SUM(purchase_items.line_total) as total_amount,
                 COUNT(DISTINCT purchases.id) as order_count'
            )
            ->first();

        return (object) [
            'line_count' => (int) ($row->line_count ?? 0),
            'total_qty' => (float) ($row->total_qty ?? 0),
            'total_amount' => (float) ($row->total_amount ?? 0),
            'order_count' => (int) ($row->order_count ?? 0),
        ];
    }

    private function purchaseItemRegisterQuery(?ReportFilters $filters)
    {
        $filters ??= new ReportFilters;

        return PurchaseItem::query()
            ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->join('products', 'products.id', '=', 'purchase_items.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->join('plants', 'plants.id', '=', 'purchases.plant_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'purchases.supplier_id')
            ->leftJoin('units', 'units.id', '=', 'products.unit_id')
            ->when($filters->plantId, fn ($q) => $q->where('purchases.plant_id', $filters->plantId))
            ->when($filters->categoryId, fn ($q) => $q->where('categories.id', $filters->categoryId))
            ->when($filters->productId, fn ($q) => $q->where('products.id', $filters->productId))
            ->when($filters->dateFrom, fn ($q) => $q->whereDate('purchases.ordered_at', '>=', $filters->dateFrom))
            ->when($filters->dateTo, fn ($q) => $q->whereDate('purchases.ordered_at', '<=', $filters->dateTo))
            ->when($filters->search, function ($q) use ($filters) {
                $term = '%'.$filters->search.'%';
                $q->where(function ($q) use ($term) {
                    $q->where('products.name', 'like', $term)
                        ->orWhere('products.sku', 'like', $term)
                        ->orWhere('categories.name', 'like', $term)
                        ->orWhere('purchases.reference_no', 'like', $term)
                        ->orWhere('suppliers.name', 'like', $term)
                        ->orWhere('plants.name', 'like', $term);
                });
            });
    }

    /**
     * All purchase line items for a given product (drill-down ledger).
     */
    public function productPurchaseLedger(int $productId, ?ReportFilters $filters = null): \Illuminate\Support\Collection
    {
        $filters ??= new ReportFilters;

        return PurchaseItem::query()
            ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->join('products', 'products.id', '=', 'purchase_items.product_id')
            ->join('plants', 'plants.id', '=', 'purchases.plant_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'purchases.supplier_id')
            ->where('products.id', $productId)
            ->when($filters->plantId, fn ($q) => $q->where('purchases.plant_id', $filters->plantId))
            ->when($filters->dateFrom, fn ($q) => $q->whereDate('purchases.ordered_at', '>=', $filters->dateFrom))
            ->when($filters->dateTo, fn ($q) => $q->whereDate('purchases.ordered_at', '<=', $filters->dateTo))
            ->when($filters->search, function ($q) use ($filters) {
                $term = '%'.$filters->search.'%';
                $q->where(function ($q) use ($term) {
                    $q->where('purchases.reference_no', 'like', $term)
                        ->orWhere('suppliers.name', 'like', $term)
                        ->orWhere('plants.name', 'like', $term);
                });
            })
            ->select(
                'purchase_items.id',
                'purchases.reference_no',
                'purchases.ordered_at',
                'purchases.id as purchase_id',
                'plants.name as plant_name',
                'suppliers.name as supplier_name',
                'products.name as product_name',
                'purchase_items.quantity',
                'purchase_items.unit_price',
                'purchase_items.line_total',
                'purchases.status'
            )
            ->orderByDesc('purchases.ordered_at')
            ->get();
    }

    /**
     * All purchase line items for a given category (drill-down ledger).
     */
    public function categoryPurchaseLedger(int $categoryId, ?ReportFilters $filters = null): \Illuminate\Support\Collection
    {
        $filters ??= new ReportFilters;

        return PurchaseItem::query()
            ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->join('products', 'products.id', '=', 'purchase_items.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->join('plants', 'plants.id', '=', 'purchases.plant_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'purchases.supplier_id')
            ->where('categories.id', $categoryId)
            ->when($filters->plantId, fn ($q) => $q->where('purchases.plant_id', $filters->plantId))
            ->when($filters->dateFrom, fn ($q) => $q->whereDate('purchases.ordered_at', '>=', $filters->dateFrom))
            ->when($filters->dateTo, fn ($q) => $q->whereDate('purchases.ordered_at', '<=', $filters->dateTo))
            ->when($filters->search, function ($q) use ($filters) {
                $term = '%'.$filters->search.'%';
                $q->where(function ($q) use ($term) {
                    $q->where('products.name', 'like', $term)
                      ->orWhere('purchases.reference_no', 'like', $term)
                      ->orWhere('suppliers.name', 'like', $term);
                });
            })
            ->select(
                'purchase_items.id',
                'purchases.reference_no',
                'purchases.ordered_at',
                'purchases.id as purchase_id',
                'plants.name as plant_name',
                'suppliers.name as supplier_name',
                'products.name as product_name',
                'purchase_items.quantity',
                'purchase_items.unit_price',
                'purchase_items.line_total',
                'purchases.status'
            )
            ->orderByDesc('purchases.ordered_at')
            ->get();
    }

    public function multipleUsageHighlights(?ReportFilters $filters = null, bool $forExport = false): \Illuminate\Support\Collection
    {
        $filters ??= new ReportFilters;

        return StockMovement::query()
            ->join('products', 'products.id', '=', 'stock_movements.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('product_use_entries', function ($join) {
                $join->on('product_use_entries.id', '=', 'stock_movements.reference_id')
                    ->where('stock_movements.reference_type', '=', StockReferenceType::ProductUse->value);
            })
            ->where('stock_movements.type', StockMovementType::Out)
            ->when($filters->plantId, fn ($q) => $q->where('stock_movements.plant_id', $filters->plantId))
            ->when($filters->productId, fn ($q) => $q->where('stock_movements.product_id', $filters->productId))
            ->when($filters->vehicleId, fn ($q) => $q->where('stock_movements.vehicle_id', $filters->vehicleId))
            ->when($filters->categoryId, fn ($q) => $q->where('categories.id', $filters->categoryId))
            ->when($filters->dateFrom, fn ($q) => $q->whereDate('stock_movements.moved_at', '>=', $filters->dateFrom))
            ->when($filters->dateTo, fn ($q) => $q->whereDate('stock_movements.moved_at', '<=', $filters->dateTo))
            ->when($filters->search, function ($q) use ($filters) {
                $term = '%'.$filters->search.'%';
                $q->where(function ($q) use ($term) {
                    $q->where('products.name', 'like', $term)
                        ->orWhere('products.sku', 'like', $term);
                });
            })
            ->groupBy(
                'stock_movements.product_id',
                'products.name',
                'categories.id',
                'categories.name'
            )
            ->selectRaw(
                'stock_movements.product_id,
                 products.name as product_name,
                 categories.id as category_id,
                 categories.name as category_name,
                 COUNT(stock_movements.id) as use_count,
                 SUM(stock_movements.quantity) as total_qty,
                 ROUND(AVG(stock_movements.quantity), 2) as avg_qty_per_use,
                 COALESCE(SUM(product_use_entries.life_hours), 0) as total_life_hours,
                 COALESCE(ROUND(AVG(product_use_entries.life_hours), 1), 0) as avg_life_hours,
                 MAX(stock_movements.moved_at) as last_used_at,
                 COUNT(DISTINCT stock_movements.plant_id) as plant_count,
                 COUNT(DISTINCT stock_movements.vehicle_id) as vehicle_count'
            )
            ->having('use_count', '>', 1)
            ->orderByDesc('use_count')
            ->when(! $forExport, fn ($q) => $q->limit(100))
            ->get();
    }

    public function productUsageByCategory(?ReportFilters $filters = null): \Illuminate\Support\Collection
    {
        $filters ??= new ReportFilters;

        return StockMovement::query()
            ->join('products', 'products.id', '=', 'stock_movements.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('product_use_entries', function ($join) {
                $join->on('product_use_entries.id', '=', 'stock_movements.reference_id')
                    ->where('stock_movements.reference_type', '=', StockReferenceType::ProductUse->value);
            })
            ->where('stock_movements.type', StockMovementType::Out)
            ->when($filters->plantId, fn ($q) => $q->where('stock_movements.plant_id', $filters->plantId))
            ->when($filters->vehicleId, fn ($q) => $q->where('stock_movements.vehicle_id', $filters->vehicleId))
            ->when($filters->categoryId, fn ($q) => $q->where('categories.id', $filters->categoryId))
            ->when($filters->dateFrom, fn ($q) => $q->whereDate('stock_movements.moved_at', '>=', $filters->dateFrom))
            ->when($filters->dateTo, fn ($q) => $q->whereDate('stock_movements.moved_at', '<=', $filters->dateTo))
            ->when($filters->search, fn ($q) => $q->where('categories.name', 'like', '%'.$filters->search.'%'))
            ->groupBy('categories.id', 'categories.name')
            ->selectRaw(
                'categories.id as category_id,
                 categories.name as category_name,
                 SUM(stock_movements.quantity) as total_qty,
                 COALESCE(SUM(product_use_entries.life_hours), 0) as total_life_hours,
                 COUNT(stock_movements.id) as usage_count,
                 COALESCE(ROUND(AVG(product_use_entries.life_hours), 1), 0) as avg_life_hours,
                 COUNT(DISTINCT stock_movements.product_id) as product_count,
                 COUNT(DISTINCT stock_movements.vehicle_id) as vehicle_count'
            )
            ->orderByDesc('total_qty')
            ->get();
    }

    public function supplierWisePurchaseTotals(?ReportFilters $filters = null): \Illuminate\Support\Collection
    {
        $filters ??= new ReportFilters;

        $rows = Purchase::query()
            ->purchaseOrders()
            ->with('supplier')
            ->when($filters->plantId, fn ($q) => $q->where('plant_id', $filters->plantId))
            ->when($filters->dateFrom, fn ($q) => $q->whereDate('ordered_at', '>=', $filters->dateFrom))
            ->when($filters->dateTo, fn ($q) => $q->whereDate('ordered_at', '<=', $filters->dateTo))
            ->when($filters->search, function ($q) use ($filters) {
                $term = '%'.$filters->search.'%';
                $q->whereHas('supplier', fn ($sq) => $sq->where('name', 'like', $term));
            })
            ->selectRaw('supplier_id, SUM(grand_total) as total_amount, COUNT(*) as order_count')
            ->groupBy('supplier_id')
            ->orderByDesc('total_amount')
            ->get();

        if ($rows->isEmpty()) {
            return $rows;
        }

        $paidBySupplier = SupplierPayment::query()
            ->whereIn('supplier_id', $rows->pluck('supplier_id'))
            ->when($filters->dateFrom, fn ($q) => $q->whereDate('payment_date', '>=', $filters->dateFrom))
            ->when($filters->dateTo, fn ($q) => $q->whereDate('payment_date', '<=', $filters->dateTo))
            ->selectRaw('supplier_id, SUM(amount) as paid_amount')
            ->groupBy('supplier_id')
            ->pluck('paid_amount', 'supplier_id');

        return $rows->map(function ($row) use ($paidBySupplier) {
            $paid = (float) ($paidBySupplier[$row->supplier_id] ?? 0);
            $total = (float) $row->total_amount;
            $row->paid_amount = $paid;
            $row->balance = $total - $paid;

            return $row;
        });
    }

    public function supplierLedger(int $supplierId, ?ReportFilters $filters = null): \Illuminate\Support\Collection
    {
        $filters ??= new ReportFilters;

        $purchases = Purchase::query()
            ->purchaseOrders()
            ->with(['plant', 'items.product'])
            ->where('supplier_id', $supplierId)
            ->when($filters->plantId, fn ($q) => $q->where('plant_id', $filters->plantId))
            ->when($filters->dateFrom, fn ($q) => $q->whereDate('ordered_at', '>=', $filters->dateFrom))
            ->when($filters->dateTo, fn ($q) => $q->whereDate('ordered_at', '<=', $filters->dateTo))
            ->when($filters->search, function ($q) use ($filters) {
                $term = '%'.$filters->search.'%';
                $q->where(function ($q) use ($term) {
                    $q->where('reference_no', 'like', $term)
                        ->orWhereHas('items.product', fn ($pq) => $pq->where('name', 'like', $term));
                });
            })
            ->get()
            ->map(fn (Purchase $p) => (object) [
                'type' => 'purchase',
                'date' => $p->ordered_at,
                'reference' => $p->reference_no,
                'plant' => $p->plant?->name,
                'particulars' => $p->items->map(fn ($item) => $item->product->name.' x '.$item->quantity)->implode(', '),
                'items' => $p->items,
                'debit' => (float) $p->grand_total,
                'credit' => 0.0,
                'subtotal' => (float) $p->subtotal,
                'total_gst' => (float) $p->total_gst,
                'status' => $p->status,
                'purchase' => $p,
                'payment' => null,
                'notes' => $p->notes,
            ]);

        $paymentsQuery = SupplierPayment::query()
            ->with('payer')
            ->where('supplier_id', $supplierId)
            ->when($filters->dateFrom, fn ($q) => $q->whereDate('payment_date', '>=', $filters->dateFrom))
            ->when($filters->dateTo, fn ($q) => $q->whereDate('payment_date', '<=', $filters->dateTo));

        if ($filters->search) {
            $term = '%'.$filters->search.'%';
            $paymentsQuery->where(function ($q) use ($term) {
                $q->where('notes', 'like', $term);
            });
        }

        // Plant filter applies to purchases only; payments are party-level.
        $payments = $paymentsQuery->get()->map(fn (SupplierPayment $payment) => (object) [
            'type' => 'payment',
            'date' => $payment->payment_date?->startOfDay(),
            'reference' => 'Payment',
            'plant' => null,
            'particulars' => $payment->notes ?: 'Payment to party',
            'items' => collect(),
            'debit' => 0.0,
            'credit' => (float) $payment->amount,
            'subtotal' => 0.0,
            'total_gst' => 0.0,
            'status' => null,
            'purchase' => null,
            'payment' => $payment,
            'notes' => $payment->notes,
        ]);

        $entries = $purchases->concat($payments)
            ->sortBy(fn ($row) => [$row->date?->timestamp ?? 0, $row->type === 'purchase' ? 0 : 1])
            ->values();

        $running = 0.0;

        return $entries->map(function ($row) use (&$running) {
            $running += $row->debit - $row->credit;
            $row->balance = $running;

            return $row;
        });
    }

    /**
     * Product-wise Stock In (qty + amount from PO) and Stock Out (qty) summary.
     */
    public function categoryStockInOut(?ReportFilters $filters = null): array
    {
        $filters ??= new ReportFilters;

        $applyCommon = function ($q) use ($filters) {
            return $q
                ->when($filters->plantId, fn ($q) => $q->where('sm.plant_id', $filters->plantId))
                ->when($filters->categoryId, fn ($q) => $q->where('c.id', $filters->categoryId))
                ->when($filters->productId, fn ($q) => $q->where('p.id', $filters->productId))
                ->when($filters->dateFrom, fn ($q) => $q->whereDate('sm.moved_at', '>=', $filters->dateFrom))
                ->when($filters->dateTo, fn ($q) => $q->whereDate('sm.moved_at', '<=', $filters->dateTo))
                ->when($filters->search, fn ($q) => $q->where(function ($q) use ($filters) {
                    $q->where('p.name', 'like', '%'.$filters->search.'%')
                        ->orWhere('p.sku', 'like', '%'.$filters->search.'%')
                        ->orWhere('c.name', 'like', '%'.$filters->search.'%');
                }));
        };

        $purchaseRates = DB::table('purchase_items')
            ->select('purchase_id', 'product_id', DB::raw('MAX(unit_price) as unit_price'))
            ->groupBy('purchase_id', 'product_id');

        // Stock In includes every inward movement. Purchase receipts still supply the amount.
        $inRows = $applyCommon(
            DB::table('stock_movements as sm')
                ->join('products as p', 'p.id', '=', 'sm.product_id')
                ->join('categories as c', 'c.id', '=', 'p.category_id')
                ->leftJoin('units as u', 'u.id', '=', 'p.unit_id')
                ->leftJoinSub($purchaseRates, 'pi', function ($join) {
                    $join->on('pi.purchase_id', '=', 'sm.reference_id')
                        ->on('pi.product_id', '=', 'sm.product_id')
                        ->where('sm.reference_type', '=', 'purchase');
                })
                ->where('sm.type', 'in')
        )
            ->select(
                'p.id as product_id',
                'p.name as product',
                'p.sku',
                'c.id as category_id',
                'c.name as category',
                DB::raw('COALESCE(u.abbreviation, u.name) as unit'),
                DB::raw('SUM(sm.quantity) as in_qty'),
                DB::raw('SUM(sm.quantity * pi.unit_price) as in_amount')
            )
            ->groupBy('p.id', 'p.name', 'p.sku', 'c.id', 'c.name', 'u.abbreviation', 'u.name')
            ->orderByDesc('in_amount')
            ->get()
            ->keyBy('product_id');

        // --- Stock Out ---
        $outRows = $applyCommon(
            DB::table('stock_movements as sm')
                ->join('products as p', 'p.id', '=', 'sm.product_id')
                ->join('categories as c', 'c.id', '=', 'p.category_id')
                ->leftJoin('units as u', 'u.id', '=', 'p.unit_id')
                ->where('sm.type', 'out')
        )
            ->select(
                'p.id as product_id',
                'p.name as product',
                'p.sku',
                'c.id as category_id',
                'c.name as category',
                DB::raw('COALESCE(u.abbreviation, u.name) as unit'),
                DB::raw('SUM(sm.quantity) as out_qty')
            )
            ->groupBy('p.id', 'p.name', 'p.sku', 'c.id', 'c.name', 'u.abbreviation', 'u.name')
            ->get()
            ->keyBy('product_id');

        // Merge
        $merged = $inRows->map(function ($row) use ($outRows) {
            $out = $outRows->get($row->product_id);
            $row->out_qty = $out?->out_qty ?? 0;

            return $row;
        });

        foreach ($outRows as $id => $out) {
            if (! $merged->has($id)) {
                $merged->put($id, (object) [
                    'product_id'  => $id,
                    'product'     => $out->product,
                    'sku'         => $out->sku,
                    'category_id' => $out->category_id,
                    'category'    => $out->category,
                    'unit'        => $out->unit,
                    'in_qty'      => 0,
                    'in_amount'   => 0,
                    'out_qty'     => $out->out_qty,
                ]);
            }
        }

        return [
            'rows' => $merged->values()->sortBy('product', SORT_NATURAL | SORT_FLAG_CASE)->values(),
            'in_qty' => $merged->sum('in_qty'),
            'in_amount' => $merged->sum('in_amount'),
            'out_qty' => $merged->sum('out_qty'),
        ];
    }
}

