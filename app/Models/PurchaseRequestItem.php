<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequestItem extends Model
{
    protected $fillable = [
        'purchase_request_id',
        'product_request_item_id',
        'product_id',
        'item_name',
        'size',
        'sub_category_id',
        'supplier_id',
        'quantity',
        'unit_price',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
        ];
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function productRequestItem(): BelongsTo
    {
        return $this->belongsTo(ProductRequestItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function subCategory(): BelongsTo
    {
        return $this->belongsTo(SubCategory::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function requiresProductSelection(): bool
    {
        return $this->product_id === null;
    }

    public function displayName(): string
    {
        if ($this->product?->name) {
            return $this->product->name;
        }

        if ($this->item_name) {
            return $this->item_name;
        }

        return $this->productRequestItem?->displayName()
            ?? '—';
    }
}
