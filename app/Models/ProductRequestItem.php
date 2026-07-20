<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductRequestItem extends Model
{
    protected $fillable = [
        'product_request_id',
        'product_id',
        'item_name',
        'size',
        'sub_category_id',
        'supplier_id',
        'quantity',
        'unit_price',
        'gst_rate',
        'billing_notes',
        'remarks',
        'image_path',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'gst_rate' => 'decimal:2',
        ];
    }

    public function productRequest(): BelongsTo
    {
        return $this->belongsTo(ProductRequest::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function subCategory(): BelongsTo
    {
        return $this->belongsTo(SubCategory::class);
    }

    public function displayName(): string
    {
        return $this->product?->name ?? $this->item_name ?? '—';
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image_path) {
            return null;
        }

        if (str_starts_with($this->image_path, 'uploads/')) {
            return asset($this->image_path);
        }

        return \Illuminate\Support\Facades\Storage::url($this->image_path);
    }
}
