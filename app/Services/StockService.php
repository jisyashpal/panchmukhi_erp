<?php

namespace App\Services;

use App\Enums\RequestStatus;
use App\Enums\StockMovementType;
use App\Enums\StockReferenceType;
use App\Models\Product;
use App\Models\ProductUseEntry;
use App\Models\Purchase;
use App\Models\Site;
use App\Models\StockBalance;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockService
{
    public function getBalance(int $siteId, int $productId): float
    {
        return (float) StockBalance::query()
            ->where('site_id', $siteId)
            ->where('product_id', $productId)
            ->value('quantity') ?? 0;
    }

    public function assertAvailable(int $siteId, int $productId, float $quantity): void
    {
        if ($this->getBalance($siteId, $productId) < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => 'Insufficient stock for this product at the selected site.',
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
                    $purchase->site_id,
                    (int) $line['product_id'],
                    StockMovementType::In,
                    $qty,
                    StockReferenceType::Purchase,
                    $purchase->id,
                    $performedBy,
                    $meta['notes'] ?? 'Stock in from purchase '.$purchase->reference_no,
                    $meta
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
        int $siteId,
        int $productId,
        float $quantity,
        int $performedBy,
        StockReferenceType $referenceType = StockReferenceType::StockIn,
        ?int $referenceId = null,
        array $meta = []
    ): void {
        DB::transaction(function () use ($siteId, $productId, $quantity, $referenceType, $referenceId, $performedBy, $meta) {
            $this->applyMovement(
                $siteId,
                $productId,
                StockMovementType::In,
                $quantity,
                $referenceType,
                $referenceId,
                $performedBy,
                $meta['notes'] ?? 'Manual stock in',
                $meta
            );
        });
    }

    public function recordOut(
        int $siteId,
        int $productId,
        float $quantity,
        StockReferenceType $referenceType,
        ?int $referenceId,
        int $performedBy,
        ?string $notes = null,
        array $meta = []
    ): void {
        DB::transaction(function () use ($siteId, $productId, $quantity, $referenceType, $referenceId, $performedBy, $notes, $meta) {
            $this->assertAvailable($siteId, $productId, $quantity);

            $this->applyMovement(
                $siteId,
                $productId,
                StockMovementType::Out,
                $quantity,
                $referenceType,
                $referenceId,
                $performedBy,
                $notes,
                $meta
            );
        });
    }

    public function recordProductUse(ProductUseEntry $entry): void
    {
        $this->recordOut(
            $entry->site_id,
            $entry->product_id,
            (float) $entry->quantity,
            StockReferenceType::ProductUse,
            $entry->id,
            $entry->recorded_by,
            $entry->notes,
            ['machine_id' => $entry->machine_id]
        );
    }

    protected function applyMovement(
        int $siteId,
        int $productId,
        StockMovementType $type,
        float $quantity,
        StockReferenceType $referenceType,
        ?int $referenceId,
        int $performedBy,
        ?string $notes,
        array $meta = []
    ): void {
        StockMovement::create([
            'site_id' => $siteId,
            'product_id' => $productId,
            'machine_id' => $meta['machine_id'] ?? null,
            'type' => $type,
            'quantity' => $quantity,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'performed_by' => $performedBy,
            'photo_path' => $meta['photo_path'] ?? null,
            'photo_confirmed' => (bool) ($meta['photo_confirmed'] ?? false),
            'reason' => $meta['reason'] ?? null,
            'notes' => $notes,
            'moved_at' => now(),
        ]);

        $balance = StockBalance::firstOrCreate(
            ['site_id' => $siteId, 'product_id' => $productId],
            ['quantity' => 0]
        );

        $delta = $type === StockMovementType::In ? $quantity : -$quantity;
        $balance->update(['quantity' => (float) $balance->quantity + $delta]);
    }
}
