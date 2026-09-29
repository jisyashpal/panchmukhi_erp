<?php

namespace App\Services;

use App\Enums\RequestStatus;
use App\Enums\StockMovementType;
use App\Enums\StockReferenceType;
use App\Models\Product;
use App\Models\ProductUseEntry;
use App\Models\Purchase;
use App\Models\Plant;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Support\ReportFilters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockService
{
    public function __construct(
        protected NotificationService $notifications
    ) {}
    public function getBalance(int $plantId, int $productId): float
    {
        return (float) StockBalance::query()
            ->where('plant_id', $plantId)
            ->where('product_id', $productId)
            ->value('quantity') ?? 0;
    }

    public function assertAvailable(int $plantId, int $productId, float $quantity, string $errorKey = 'quantity'): void
    {
        if ($this->getBalance($plantId, $productId) < $quantity) {
            throw ValidationException::withMessages([
                $errorKey => 'Insufficient stock for this product at the selected plant.',
            ]);
        }
    }

    /**
     * @param  array<int, array{product_id: int, quantity: float}>  $lines
     */
    public function recordInFromPurchase(Purchase $purchase, array $lines, int $performedBy, array $meta = []): void
    {
        DB::transaction(function () use ($purchase, $lines, $performedBy, $meta) {
            foreach ($lines as $line) {
                $qty = (float) $line['quantity'];
                if ($qty <= 0) {
                    continue;
                }

                $this->applyMovement(
                    $purchase->plant_id,
                    (int) $line['product_id'],
                    StockMovementType::In,
                    $qty,
                    StockReferenceType::Purchase,
                    $purchase->id,
                    $performedBy,
                    $meta['notes'] ?? 'Stock in from purchase '.$purchase->reference_no,
                    array_merge($meta, ['supplier_id' => $purchase->supplier_id])
                );

                $item = $purchase->items()->where('product_id', $line['product_id'])->first();
                if ($item) {
                    $item->update([
                        'received_quantity' => (float) $item->received_quantity + $qty,
                    ]);
                }
            }
        });
    }

    public function reassignPurchaseLineProduct(Purchase $purchase, int $fromProductId, int $toProductId, float $quantity): void
    {
        if ($fromProductId === $toProductId || $quantity <= 0) {
            return;
        }

        DB::transaction(function () use ($purchase, $fromProductId, $toProductId, $quantity) {
            $movements = StockMovement::query()
                ->where('plant_id', $purchase->plant_id)
                ->where('reference_type', StockReferenceType::Purchase)
                ->where('reference_id', $purchase->id)
                ->where('product_id', $fromProductId)
                ->where('type', StockMovementType::In)
                ->orderByDesc('id')
                ->get();

            $movement = $movements->first(fn (StockMovement $row) => abs((float) $row->quantity - $quantity) < 0.001)
                ?? ($movements->count() === 1 ? $movements->first() : null);

            if (! $movement) {
                return;
            }

            $qty = (float) $movement->quantity;
            $movement->update(['product_id' => $toProductId]);

            $fromBalance = StockBalance::query()
                ->where('plant_id', $purchase->plant_id)
                ->where('product_id', $fromProductId)
                ->first();

            if ($fromBalance) {
                $fromBalance->update([
                    'quantity' => (float) $fromBalance->quantity - $qty,
                ]);
            }

            $toBalance = StockBalance::firstOrCreate(
                ['plant_id' => $purchase->plant_id, 'product_id' => $toProductId],
                ['quantity' => 0]
            );
            $toBalance->update([
                'quantity' => (float) $toBalance->quantity + $qty,
            ]);
        });
    }

    public function recordIn(
        int $plantId,
        int $productId,
        float $quantity,
        int $performedBy,
        StockReferenceType $referenceType = StockReferenceType::StockIn,
        ?int $referenceId = null,
        array $meta = [],
        $movedAt = null
    ): void {
        DB::transaction(function () use ($plantId, $productId, $quantity, $referenceType, $referenceId, $performedBy, $meta, $movedAt) {
            $this->applyMovement(
                $plantId,
                $productId,
                StockMovementType::In,
                $quantity,
                $referenceType,
                $referenceId,
                $performedBy,
                $meta['notes'] ?? 'Manual stock in',
                $meta,
                $movedAt
            );
        });
    }

    public function recordOut(
        int $plantId,
        int $productId,
        float $quantity,
        StockReferenceType $referenceType,
        ?int $referenceId,
        int $performedBy,
        ?string $notes = null,
        array $meta = [],
        $movedAt = null
    ): void {
        DB::transaction(function () use ($plantId, $productId, $quantity, $referenceType, $referenceId, $performedBy, $notes, $meta, $movedAt) {
            $this->assertAvailable($plantId, $productId, $quantity);

            $this->applyMovement(
                $plantId,
                $productId,
                StockMovementType::Out,
                $quantity,
                $referenceType,
                $referenceId,
                $performedBy,
                $notes,
                $meta,
                $movedAt
            );
        });
    }

    /**
     * @param  array<int, array{product_id: int, quantity: float, moved_at?: mixed, vehicle_id?: int|null, reason?: ?string, notes?: ?string}>  $lines
     */
    public function recordOutMany(
        int $plantId,
        array $lines,
        StockReferenceType $referenceType,
        ?int $referenceId,
        int $performedBy,
        ?string $notes = null,
        array $meta = [],
        $movedAt = null
    ): void {
        DB::transaction(function () use ($plantId, $lines, $referenceType, $referenceId, $performedBy, $notes, $meta, $movedAt) {
            $needed = [];
            foreach ($lines as $index => $line) {
                $productId = (int) $line['product_id'];
                $quantity = (float) $line['quantity'];
                if ($quantity <= 0) {
                    continue;
                }
                $needed[$productId] = ($needed[$productId] ?? 0) + $quantity;
                $this->assertAvailable(
                    $plantId,
                    $productId,
                    $needed[$productId],
                    "items.{$index}.quantity"
                );
            }

            foreach ($lines as $line) {
                $quantity = (float) $line['quantity'];
                if ($quantity <= 0) {
                    continue;
                }

                $lineMeta = $meta;
                if (array_key_exists('vehicle_id', $line)) {
                    $lineMeta['vehicle_id'] = $line['vehicle_id'];
                }
                if (array_key_exists('reason', $line) && filled($line['reason'])) {
                    $lineMeta['reason'] = $line['reason'];
                }
                if (array_key_exists('unit_price', $line) && $line['unit_price'] !== null && $line['unit_price'] !== '') {
                    $lineMeta['unit_price'] = (float) $line['unit_price'];
                }

                $this->applyMovement(
                    $plantId,
                    (int) $line['product_id'],
                    StockMovementType::Out,
                    $quantity,
                    $referenceType,
                    $referenceId,
                    $performedBy,
                    $line['notes'] ?? $notes,
                    $lineMeta,
                    $line['moved_at'] ?? $movedAt
                );
            }
        });
    }

    /**
     * Save stock-out history without checking or changing the current balance.
     *
     * @param  array<int, array{product_id: int, quantity: float, moved_at?: mixed, vehicle_id?: int|null, reason?: ?string, notes?: ?string}>  $lines
     */
    public function recordOutRegister(
        int $plantId,
        array $lines,
        StockReferenceType $referenceType,
        ?int $referenceId,
        int $performedBy,
        ?string $notes = null,
        array $meta = [],
        $movedAt = null
    ): void {
        DB::transaction(function () use ($plantId, $lines, $referenceType, $referenceId, $performedBy, $notes, $meta, $movedAt) {
            foreach ($lines as $line) {
                $quantity = (float) $line['quantity'];
                if ($quantity <= 0) {
                    continue;
                }

                $lineMeta = $meta;
                $lineMeta['skip_balance'] = true;
                $lineMeta['skip_notification'] = true;
                if (array_key_exists('vehicle_id', $line)) {
                    $lineMeta['vehicle_id'] = $line['vehicle_id'];
                }
                if (array_key_exists('reason', $line) && filled($line['reason'])) {
                    $lineMeta['reason'] = $line['reason'];
                }

                $this->applyMovement(
                    $plantId,
                    (int) $line['product_id'],
                    StockMovementType::Out,
                    $quantity,
                    $referenceType,
                    $referenceId,
                    $performedBy,
                    $line['notes'] ?? $notes,
                    $lineMeta,
                    $line['moved_at'] ?? $movedAt
                );
            }
        });
    }

    public function recordProductUse(ProductUseEntry $entry): void
    {
        $this->recordOut(
            $entry->plant_id,
            $entry->product_id,
            (float) $entry->quantity,
            StockReferenceType::ProductUse,
            $entry->id,
            $entry->recorded_by,
            $entry->notes,
            ['sub_category_id' => $entry->sub_category_id]
        );
    }

    protected function applyMovement(
        int $plantId,
        int $productId,
        StockMovementType $type,
        float $quantity,
        StockReferenceType $referenceType,
        ?int $referenceId,
        int $performedBy,
        ?string $notes,
        array $meta = [],
        $movedAt = null
    ): void {
        $movementDate = $movedAt ? \Illuminate\Support\Facades\Date::parse($movedAt) : now();

        StockMovement::create([
            'plant_id' => $plantId,
            'product_id' => $productId,
            'supplier_id' => $meta['supplier_id'] ?? null,
            'sub_category_id' => $meta['sub_category_id'] ?? null,
            'vehicle_id' => $meta['vehicle_id'] ?? null,
            'type' => $type,
            'quantity' => $quantity,
            'unit_price' => array_key_exists('unit_price', $meta) ? $meta['unit_price'] : null,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'performed_by' => $performedBy,
            'photo_path' => $meta['photo_path'] ?? null,
            'photo_confirmed' => (bool) ($meta['photo_confirmed'] ?? false),
            'reason' => $meta['reason'] ?? null,
            'notes' => $notes,
            'moved_at' => $movementDate,
        ]);

        if ($meta['skip_balance'] ?? false) {
            return;
        }

        $balance = StockBalance::firstOrCreate(
            ['plant_id' => $plantId, 'product_id' => $productId],
            ['quantity' => 0]
        );

        $delta = $type === StockMovementType::In ? $quantity : -$quantity;
        $newBalance = (float) $balance->quantity + $delta;
        $balance->update(['quantity' => $newBalance]);

        if (! ($meta['skip_notification'] ?? false)) {
            $this->notifications->notifyPlantStockUpdated(
                $plantId,
                $productId,
                $type,
                $quantity,
                $newBalance,
                $performedBy,
                $notes
            );
        }
    }

    /**
     * @return LengthAwarePaginator|Collection<int, StockMovement>
     */
    public function listMovements(StockMovementType $type, ?ReportFilters $filters = null, bool $forExport = false): LengthAwarePaginator|Collection
    {
        $filters ??= new ReportFilters;

        $query = StockMovement::query()
            ->with(['plant', 'product.unit', 'product.category', 'supplier', 'vehicle', 'performer'])
            ->where('type', $type)
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
                        ->orWhereHas('plant', fn ($pq) => $pq->where('name', 'like', $term))
                        ->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'like', $term))
                        ->orWhereHas('vehicle', function ($vq) use ($term) {
                            $vq->where('name', 'like', $term)
                                ->orWhere('registration_no', 'like', $term);
                        })
                        ->orWhere('reason', 'like', $term)
                        ->orWhere('notes', 'like', $term);
                });
            })
            ->latest('moved_at')
            ->latest('id');

        return $forExport
            ? $query->get()
            : $query->paginate(30)->withQueryString();
    }

    /**
     * @param  LengthAwarePaginator|Collection<int, StockMovement>|iterable<int, StockMovement>  $movements
     * @return Collection<int, string>
     */
    public function purchaseReferencesFor(iterable $movements): Collection
    {
        $items = collect(
            $movements instanceof LengthAwarePaginator ? $movements->items() : $movements
        );

        $ids = $items
            ->filter(fn ($m) => $m->reference_type === StockReferenceType::Purchase && $m->reference_id)
            ->pluck('reference_id')
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return Purchase::whereIn('id', $ids)->pluck('reference_no', 'id');
    }
}
