<?php

namespace App\Models;

use App\Enums\PurchaseStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Purchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'plant_id',
        'supplier_id',
        'purchase_request_id',
        'reference_no',
        'status',
        'is_interstate',
        'supplier_invoice_no',
        'supplier_invoice_date',
        'subtotal',
        'total_cgst',
        'total_sgst',
        'total_igst',
        'total_gst',
        'grand_total',
        'ordered_at',
        'delivered_at',
        'received_by',
        'received_at',
        'dispatched_by',
        'dispatched_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => PurchaseStatus::class,
            'is_interstate' => 'boolean',
            'supplier_invoice_date' => 'date',
            'subtotal' => 'decimal:2',
            'total_cgst' => 'decimal:2',
            'total_sgst' => 'decimal:2',
            'total_igst' => 'decimal:2',
            'total_gst' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'ordered_at' => 'datetime',
            'delivered_at' => 'datetime',
            'received_at' => 'datetime',
            'dispatched_at' => 'datetime',
        ];
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function dispatcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function hoComments(): HasMany
    {
        return $this->hasMany(PurchaseComment::class)->latest();
    }

    public function latestHoComment(): HasOne
    {
        return $this->hasOne(PurchaseComment::class)->latestOfMany();
    }

    public function isDispatched(): bool
    {
        return $this->dispatched_at !== null;
    }

    public function allItemsDispatchResolved(): bool
    {
        if ($this->items->isEmpty()) {
            return false;
        }

        return $this->items->every(fn (PurchaseItem $item) => $item->isDispatchResolved());
    }

    public function receivableItems()
    {
        return $this->items->filter(fn (PurchaseItem $item) => $item->isReceivableForStockIn());
    }
}
