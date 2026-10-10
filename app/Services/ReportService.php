<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Enums\StockReferenceType;
use App\Models\ProductUseEntry;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Support\ReportFilters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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

    /**
     * @return LengthAwarePaginator<int, StockMovement>|Collection<int, StockMovement>
     */
    public function productLifeUsage(?ReportFilters $filters = null, bool $forExport = false): LengthAwarePaginator|Collection
    {
        $filters ??= new ReportFilters;

        $query = $this->productLifeUsageQuery($filters)
            ->with(['plant', 'vehicle', 'product.category', 'subCategory', 'performer'])
            ->latest('moved_at')
            ->latest('id');

        $movements = $forExport
            ? $query->get()
            : $query->paginate(30)->withQueryString();

        return $this->attachProductUses($movements);
    }

    public function productLifeUsageSummary(?ReportFilters $filters = null): object
    {
        $filters ??= new ReportFilters;

        $row = $this->productLifeUsageQuery($filters)
            ->leftJoin('product_use_entries', function ($join) {
                $join->on('product_use_entries.id', '=', 'stock_movements.reference_id')
                    ->where('stock_movements.reference_type', '=', StockReferenceType::ProductUse->value);
            })
            ->selectRaw(
                'COUNT(stock_movements.id) as entry_count,
                 COALESCE(SUM(stock_movements.quantity), 0) as total_qty,
                 COALESCE(SUM(COALESCE(product_use_entries.life_hours, 0)), 0) as total_life,
                 COUNT(DISTINCT stock_movements.vehicle_id) as vehicle_count'
            )
            ->first();

        $count = (int) ($row->entry_count ?? 0);
        $totalLife = (float) ($row->total_life ?? 0);

        return (object) [
            'entry_count' => $count,
            'total_qty' => (float) ($row->total_qty ?? 0),
            'total_life' => $totalLife,
            'vehicle_count' => (int) ($row->vehicle_count ?? 0),
            'avg_life' => $count > 0 ? $totalLife / $count : 0,
        ];
    }

    private function productLifeUsageQuery(ReportFilters $filters)
    {
        return StockMovement::query()
            ->where('stock_movements.type', StockMovementType::Out)
            ->when($filters->plantId, fn ($q) => $q->where('stock_movements.plant_id', $filters->plantId))
            ->when($filters->productId, fn ($q) => $q->where('stock_movements.product_id', $filters->productId))
            ->when($filters->vehicleId, fn ($q) => $q->where('stock_movements.vehicle_id', $filters->vehicleId))
            ->when($filters->categoryId, fn ($q) => $q->whereHas(
                'product',
                fn ($pq) => $pq->where('category_id', $filters->categoryId)
            ))
            ->when($filters->dateFrom, fn ($q) => $q->whereDate('stock_movements.moved_at', '>=', $filters->dateFrom))
            ->when($filters->dateTo, fn ($q) => $q->whereDate('stock_movements.moved_at', '<=', $filters->dateTo))
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
            });
    }

    /**
     * @param  LengthAwarePaginator<int, StockMovement>|Collection<int, StockMovement>  $movements
     * @return LengthAwarePaginator<int, StockMovement>|Collection<int, StockMovement>
     */
    private function attachProductUses(LengthAwarePaginator|Collection $movements): LengthAwarePaginator|Collection
    {
        $items = $movements instanceof LengthAwarePaginator
            ? $movements->getCollection()
            : $movements;

        $useIds = $items
            ->filter(fn (StockMovement $movement) => $movement->reference_type === StockReferenceType::ProductUse && $movement->reference_id)
            ->pluck('reference_id')
            ->unique()
            ->values();

        $uses = $useIds->isEmpty()
            ? collect()
            : ProductUseEntry::with(['replacedProduct', 'subCategory'])->whereIn('id', $useIds)->get()->keyBy('id');

        $items->each(function (StockMovement $movement) use ($uses) {
            $use = $movement->reference_type === StockReferenceType::ProductUse
                ? $uses->get($movement->reference_id)
                : null;

            $movement->setRelation('productUse', $use);
        });

        return $movements;
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

        return $this->stockOutUsageQuery($filters)
            ->groupBy('categories.id', 'categories.name')
            ->selectRaw(
                'categories.id as category_id,
                 categories.name as category_name,
                 SUM(stock_movements.quantity) as total_qty,
                 '.$this->stockOutAmountExpression().' as total_amount,
                 COALESCE(SUM(product_use_entries.life_hours), 0) as total_life_hours,
                 COUNT(stock_movements.id) as usage_count,
                 COALESCE(ROUND(AVG(product_use_entries.life_hours), 1), 0) as avg_life_hours,
                 COUNT(DISTINCT stock_movements.product_id) as product_count,
                 COUNT(DISTINCT stock_movements.vehicle_id) as vehicle_count'
            )
            ->orderByDesc('total_amount')
            ->get();
    }

    public function vehicleStockOutExpense(?ReportFilters $filters = null): \Illuminate\Support\Collection
    {
        $filters ??= new ReportFilters;

        return $this->stockOutUsageQuery($filters)
            ->leftJoin('vehicles', 'vehicles.id', '=', 'stock_movements.vehicle_id')
            ->groupBy('stock_movements.vehicle_id', 'vehicles.name', 'vehicles.registration_no')
            ->selectRaw(
                'stock_movements.vehicle_id as vehicle_id,
                 vehicles.name as vehicle_name,
                 vehicles.registration_no as registration_no,
                 SUM(stock_movements.quantity) as total_qty,
                 '.$this->stockOutAmountExpression().' as total_amount,
                 COUNT(stock_movements.id) as usage_count,
                 COUNT(DISTINCT stock_movements.product_id) as product_count,
                 '.$this->categoryNamesAggregate().' as category_names'
            )
            ->orderByDesc('total_amount')
            ->get();
    }

    public function vehicleStockOutProducts(?ReportFilters $filters = null, bool $unassignedOnly = false): \Illuminate\Support\Collection
    {
        $filters ??= new ReportFilters;

        return $this->stockOutUsageQuery($filters)
            ->when($unassignedOnly, fn ($q) => $q->whereNull('stock_movements.vehicle_id'))
            ->leftJoin('vehicles', 'vehicles.id', '=', 'stock_movements.vehicle_id')
            ->groupBy(
                'stock_movements.vehicle_id',
                'vehicles.name',
                'vehicles.registration_no',
                'stock_movements.product_id',
                'products.name',
                'categories.id',
                'categories.name'
            )
            ->selectRaw(
                'stock_movements.vehicle_id as vehicle_id,
                 vehicles.name as vehicle_name,
                 vehicles.registration_no as registration_no,
                 stock_movements.product_id as product_id,
                 products.name as product_name,
                 categories.name as category_name,
                 SUM(stock_movements.quantity) as total_qty,
                 '.$this->stockOutAmountExpression().' as total_amount,
                 COUNT(stock_movements.id) as usage_count'
            )
            ->orderByDesc('total_amount')
            ->get();
    }

    private function stockOutUsageQuery(ReportFilters $filters)
    {
        return StockMovement::query()
            ->join('products', 'products.id', '=', 'stock_movements.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('product_use_entries', function ($join) {
                $join->on('product_use_entries.id', '=', 'stock_movements.reference_id')
                    ->where('stock_movements.reference_type', '=', StockReferenceType::ProductUse->value);
            })
            ->leftJoinSub($this->latestPurchaseRates(), 'rates', 'rates.product_id', '=', 'stock_movements.product_id')
            ->where('stock_movements.type', StockMovementType::Out)
            ->when($filters->plantId, fn ($q) => $q->where('stock_movements.plant_id', $filters->plantId))
            ->when($filters->vehicleId, fn ($q) => $q->where('stock_movements.vehicle_id', $filters->vehicleId))
            ->when($filters->categoryId, fn ($q) => $q->where('categories.id', $filters->categoryId))
            ->when($filters->dateFrom, fn ($q) => $q->whereDate('stock_movements.moved_at', '>=', $filters->dateFrom))
            ->when($filters->dateTo, fn ($q) => $q->whereDate('stock_movements.moved_at', '<=', $filters->dateTo))
            ->when($filters->search, fn ($q) => $q->where('categories.name', 'like', '%'.$filters->search.'%'));
    }

    private function latestPurchaseRates()
    {
        $latestIds = DB::table('purchase_items')
            ->selectRaw('MAX(id) as id')
            ->groupBy('product_id');

        return DB::table('purchase_items as pi')
            ->joinSub($latestIds, 'latest_pi', 'latest_pi.id', '=', 'pi.id')
            ->select('pi.product_id', 'pi.unit_price');
    }

    private function stockOutAmountExpression(): string
    {
        return 'SUM(stock_movements.quantity * COALESCE(stock_movements.unit_price, rates.unit_price, 0))';
    }

    private function categoryNamesAggregate(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => 'REPLACE(GROUP_CONCAT(DISTINCT categories.name), ",", ", ")',
            'pgsql' => 'STRING_AGG(DISTINCT categories.name, \', \' ORDER BY categories.name)',
            default => 'GROUP_CONCAT(DISTINCT categories.name ORDER BY categories.name SEPARATOR ", ")',
        };
    }

    public function supplierWisePurchaseTotals(?ReportFilters $filters = null): \Illuminate\Support\Collection
    {
        $filters ??= new ReportFilters;

        $purchaseBase = Purchase::query()
            ->purchaseOrders()
            ->when($filters->plantId, fn ($q) => $q->where('plant_id', $filters->plantId))
            ->when($filters->search, function ($q) use ($filters) {
                $term = '%'.$filters->search.'%';
                $q->whereHas('supplier', fn ($sq) => $sq->where('name', 'like', $term));
            });

        $periodPurchases = (clone $purchaseBase)
            ->when($filters->dateFrom, fn ($q) => $q->whereDate('ordered_at', '>=', $filters->dateFrom))
            ->when($filters->dateTo, fn ($q) => $q->whereDate('ordered_at', '<=', $filters->dateTo))
            ->selectRaw('supplier_id, SUM(grand_total) as total_amount, COUNT(*) as order_count')
            ->groupBy('supplier_id')
            ->get()
            ->keyBy(fn ($row) => (int) $row->supplier_id);

        $priorPurchases = collect();
        if ($filters->dateFrom) {
            $priorPurchases = (clone $purchaseBase)
                ->whereDate('ordered_at', '<', $filters->dateFrom)
                ->selectRaw('supplier_id, SUM(grand_total) as total_amount')
                ->groupBy('supplier_id')
                ->pluck('total_amount', 'supplier_id')
                ->mapWithKeys(fn ($amount, $id) => [(int) $id => (float) $amount]);
        }

        $paymentBase = SupplierPayment::query()
            ->when($filters->search, function ($q) use ($filters) {
                $term = '%'.$filters->search.'%';
                $q->whereHas('supplier', fn ($sq) => $sq->where('name', 'like', $term));
            });

        $periodPayments = (clone $paymentBase)
            ->when($filters->dateFrom, fn ($q) => $q->whereDate('payment_date', '>=', $filters->dateFrom))
            ->when($filters->dateTo, fn ($q) => $q->whereDate('payment_date', '<=', $filters->dateTo))
            ->selectRaw('supplier_id, SUM(amount) as paid_amount')
            ->groupBy('supplier_id')
            ->pluck('paid_amount', 'supplier_id')
            ->mapWithKeys(fn ($amount, $id) => [(int) $id => (float) $amount]);

        $priorPayments = collect();
        if ($filters->dateFrom) {
            $priorPayments = (clone $paymentBase)
                ->whereDate('payment_date', '<', $filters->dateFrom)
                ->selectRaw('supplier_id, SUM(amount) as paid_amount')
                ->groupBy('supplier_id')
                ->pluck('paid_amount', 'supplier_id')
                ->mapWithKeys(fn ($amount, $id) => [(int) $id => (float) $amount]);
        }

        $openingSuppliers = Supplier::query()
            ->whereNotNull('opening_balance_date')
            ->where('opening_balance', '>', 0)
            ->when($filters->dateTo, fn ($q) => $q->whereDate('opening_balance_date', '<=', $filters->dateTo))
            ->when($filters->search, fn ($q) => $q->where('name', 'like', '%'.$filters->search.'%'))
            ->get()
            ->keyBy('id');

        $ids = $periodPurchases->keys()->merge($priorPurchases->keys());

        // Opening and payments are party-level. A plant filter limits the list to
        // parties that have purchases at that plant, matching the previous report.
        if (! $filters->plantId) {
            $ids = $ids
                ->merge($periodPayments->keys())
                ->merge($priorPayments->keys())
                ->merge($openingSuppliers->keys());
        }

        $ids = $ids->map(fn ($id) => (int) $id)->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $suppliers = Supplier::query()->whereIn('id', $ids)->get()->keyBy('id');

        return $ids->map(function (int $id) use ($filters, $suppliers, $periodPurchases, $priorPurchases, $periodPayments, $priorPayments) {
            $supplier = $suppliers->get($id);
            $openingBf = 0.0;
            $openingInPeriod = 0.0;

            if ($supplier?->hasOpeningBalance()) {
                $openingDate = $supplier->opening_balance_date->toDateString();
                $onOrBeforeEnd = ! $filters->dateTo || $openingDate <= $filters->dateTo;

                if ($onOrBeforeEnd && $filters->dateFrom && $openingDate < $filters->dateFrom) {
                    $openingBf = $supplier->signedOpeningBalance();
                } elseif ($onOrBeforeEnd && (! $filters->dateFrom || $openingDate >= $filters->dateFrom)) {
                    $openingInPeriod = $supplier->signedOpeningBalance();
                }
            }

            $broughtForward = round($openingBf + (float) ($priorPurchases[$id] ?? 0) - (float) ($priorPayments[$id] ?? 0), 2);
            $total = round((float) ($periodPurchases[$id]->total_amount ?? 0), 2);
            $paid = round((float) ($periodPayments[$id] ?? 0), 2);

            return (object) [
                'supplier_id' => $id,
                'supplier' => $supplier,
                'order_count' => (int) ($periodPurchases[$id]->order_count ?? 0),
                'total_amount' => $total,
                'paid_amount' => $paid,
                'brought_forward' => $broughtForward,
                'balance' => round($broughtForward + $total + $openingInPeriod - $paid, 2),
            ];
        })->sortByDesc('balance')->values();
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

        $supplier = Supplier::query()->find($supplierId);
        $entries = $purchases->concat($payments);

        if ($supplier && $this->openingFallsInPeriod($supplier, $filters)) {
            $entries->push($this->openingLedgerRow($supplier));
        }

        $entries = $entries
            ->sortBy(fn ($row) => [
                $row->date?->timestamp ?? 0,
                match ($row->type) {
                    'opening' => 0,
                    'purchase' => 1,
                    default => 2,
                },
            ])
            ->values();

        if ($supplier && $filters->dateFrom && ! $filters->search) {
            $broughtForward = $this->supplierBroughtForward($supplier, $filters);
            $priorOpening = $supplier->hasOpeningBalance()
                && $supplier->opening_balance_date->toDateString() < $filters->dateFrom;

            if (abs($broughtForward) >= 0.01 || $priorOpening) {
                $entries->prepend($this->broughtForwardLedgerRow($filters->dateFrom, $broughtForward));
            }
        }

        $running = 0.0;

        return $entries->map(function ($row) use (&$running) {
            $running = round($running + $row->debit - $row->credit, 2);
            $row->balance = $running;

            return $row;
        });
    }

    private function supplierBroughtForward(Supplier $supplier, ReportFilters $filters): float
    {
        $broughtForward = 0.0;

        if ($supplier->hasOpeningBalance() && $supplier->opening_balance_date->toDateString() < $filters->dateFrom) {
            $broughtForward += $supplier->signedOpeningBalance();
        }

        $priorPurchases = Purchase::query()
            ->purchaseOrders()
            ->where('supplier_id', $supplier->id)
            ->whereDate('ordered_at', '<', $filters->dateFrom)
            ->when($filters->plantId, fn ($q) => $q->where('plant_id', $filters->plantId))
            ->sum('grand_total');

        $priorPayments = SupplierPayment::query()
            ->where('supplier_id', $supplier->id)
            ->whereDate('payment_date', '<', $filters->dateFrom)
            ->sum('amount');

        return round($broughtForward + (float) $priorPurchases - (float) $priorPayments, 2);
    }

    private function openingFallsInPeriod(Supplier $supplier, ReportFilters $filters): bool
    {
        if (! $supplier->hasOpeningBalance()) {
            return false;
        }

        $openingDate = $supplier->opening_balance_date->toDateString();

        if ($filters->dateFrom && $openingDate < $filters->dateFrom) {
            return false;
        }

        if ($filters->dateTo && $openingDate > $filters->dateTo) {
            return false;
        }

        if ($filters->search) {
            $term = mb_strtolower($filters->search);

            return str_contains('opening balance', $term);
        }

        return true;
    }

    private function openingLedgerRow(Supplier $supplier): object
    {
        $signed = $supplier->signedOpeningBalance();

        return (object) [
            'type' => 'opening',
            'date' => $supplier->opening_balance_date->copy()->startOfDay(),
            'reference' => 'Opening',
            'plant' => null,
            'particulars' => 'Opening balance',
            'items' => collect(),
            'debit' => $signed > 0 ? $signed : 0.0,
            'credit' => $signed < 0 ? abs($signed) : 0.0,
            'subtotal' => 0.0,
            'total_gst' => 0.0,
            'status' => null,
            'purchase' => null,
            'payment' => null,
            'notes' => null,
        ];
    }

    private function broughtForwardLedgerRow(string $dateFrom, float $broughtForward): object
    {
        return (object) [
            'type' => 'balance_bf',
            'date' => Carbon::parse($dateFrom)->startOfDay(),
            'reference' => 'B/F',
            'plant' => null,
            'particulars' => 'Balance B/F',
            'items' => collect(),
            'debit' => $broughtForward > 0 ? $broughtForward : 0.0,
            'credit' => $broughtForward < 0 ? abs($broughtForward) : 0.0,
            'subtotal' => 0.0,
            'total_gst' => 0.0,
            'status' => null,
            'purchase' => null,
            'payment' => null,
            'notes' => null,
        ];
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

