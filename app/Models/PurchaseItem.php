<?php

namespace App\Models;

use App\Enums\PurchaseItemDispatchStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{
    protected $fillable = [
        'purchase_id',
        'product_id',
        'quantity',
        'unit_price',
        'gst_rate',
        'taxable_amount',
        'cgst_amount',
        'sgst_amount',
        'igst_amount',
        'line_total',
        'received_quantity',
        'dispatch_status',
        'dispatched_at',
        'dispatched_quantity',
        'dispatch_remark',
        'alternative_product_id',
        'original_product_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'gst_rate' => 'decimal:2',
            'taxable_amount' => 'decimal:2',
            'cgst_amount' => 'decimal:2',
            'sgst_amount' => 'decimal:2',
            'igst_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'received_quantity' => 'decimal:2',
            'dispatched_quantity' => 'decimal:2',
            'dispatched_at' => 'date',
            'dispatch_status' => PurchaseItemDispatchStatus::class,
        ];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function alternativeProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'alternative_product_id');
    }

    public function originalProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'original_product_id');
    }

    public function isDispatchResolved(): bool
    {
        return $this->dispatch_status !== null;
    }

    public function isReceivableForStockIn(): bool
    {
        return $this->dispatch_status?->isReceivable() ?? false;
    }

    public function receivableQuantity(): float
    {
        if (! $this->isReceivableForStockIn()) {
            return 0.0;
        }

        $orderedOrDispatched = (float) ($this->dispatched_quantity ?? $this->quantity);

        return max(0, $orderedOrDispatched - (float) $this->received_quantity);
    }
}
