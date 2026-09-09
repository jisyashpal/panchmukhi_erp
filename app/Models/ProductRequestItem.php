<?php

namespace App\Models;

use App\Enums\PriorityLevel;
use App\Enums\ProductRequestItemWorkflowStatus;
use App\Enums\RequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

class ProductRequestItem extends Model
{
    protected $fillable = [
        'product_request_id',
        'product_id',
        'item_name',
        'size',
        'unit',
        'sub_category_id',
        'supplier_id',
        'quantity',
        'priority',
        'unit_price',
        'gst_rate',
        'billing_notes',
        'remarks',
        'image_path',
    ];

    protected function casts(): array
    {
        return [
            'priority' => PriorityLevel::class,
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

    public function purchaseRequestItem(): HasOne
    {
        return $this->hasOne(PurchaseRequestItem::class);
    }

    public function displayName(): string
    {
        return $this->product?->name ?? $this->item_name ?? '—';
    }

    public function displayUnit(): string
    {
        return $this->unit
            ?: ($this->product?->unit?->abbreviation ?: $this->product?->unit?->name)
            ?: '—';
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return Collection<int, Purchase>
     */
    public function relatedPurchases(): Collection
    {
        $purchases = $this->purchaseRequestItem?->purchaseRequest?->purchases ?? collect();

        if ($purchases->isEmpty() || ! $this->product_id) {
            return $purchases;
        }

        $matched = $purchases->filter(function (Purchase $purchase) {
            if (! $purchase->relationLoaded('items')) {
                return true;
            }

            return $purchase->items->contains(function (PurchaseItem $item) {
                return (int) $item->product_id === (int) $this->product_id
                    || (int) ($item->original_product_id ?? 0) === (int) $this->product_id;
            });
        });

        return $matched;
    }

    /**
     * @return Collection<int, Purchase>
     */
    public function relatedQuotations(): Collection
    {
        return $this->relatedPurchases()->filter(fn (Purchase $purchase) => $purchase->isQuotation());
    }

    /**
     * @return Collection<int, Purchase>
     */
    public function relatedPurchaseOrders(): Collection
    {
        return $this->relatedPurchases()->filter(fn (Purchase $purchase) => $purchase->isPurchaseOrder());
    }

    public function workflowStatus(): ProductRequestItemWorkflowStatus
    {
        if ($this->productRequest?->status === RequestStatus::Cancelled) {
            return ProductRequestItemWorkflowStatus::Cancelled;
        }

        $purchaseOrders = $this->relatedPurchaseOrders();

        if ($purchaseOrders->isNotEmpty()) {
            $isDispatched = $purchaseOrders->contains(function (Purchase $purchase) {
                if ($purchase->dispatched_at !== null) {
                    return true;
                }

                if (! $purchase->relationLoaded('items')) {
                    return false;
                }

                return $purchase->items->contains(
                    fn (PurchaseItem $item) => $item->dispatch_status !== null
                );
            });

            return $isDispatched
                ? ProductRequestItemWorkflowStatus::Dispatch
                : ProductRequestItemWorkflowStatus::PoCreated;
        }

        if ($this->relatedQuotations()->isNotEmpty()) {
            return ProductRequestItemWorkflowStatus::CreatePo;
        }

        return ProductRequestItemWorkflowStatus::SendQuotation;
    }

    public function canSelectForPo(): bool
    {
        return in_array($this->workflowStatus(), [
            ProductRequestItemWorkflowStatus::SendQuotation,
            ProductRequestItemWorkflowStatus::Pending,
        ], true);
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
