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

    public function assertAvailable(int $plantId, int $productId, float $quantity): void
    {
        if ($this->getBalance($plantId, $productId) < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => 'Insufficient stock for this product at the selected plant.',
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
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'performed_by' => $performedBy,
            'photo_path' => $meta['photo_path'] ?? null,
            'photo_confirmed' => (bool) ($meta['photo_confirmed'] ?? false),
            'reason' => $meta['reason'] ?? null,
            'notes' => $notes,
            'moved_at' => $movementDate,
        ]);

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
}
