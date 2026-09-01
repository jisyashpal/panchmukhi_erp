<?php

namespace App\Models;

use App\Enums\RequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'plant_id',
        'product_request_id',
        'requested_by',
        'plant_status',
        'plant_reviewed_by',
        'plant_reviewed_at',
        'plant_decline_reason',
        'status',
        'remarks',
        'decline_reason',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'plant_status' => RequestStatus::class,
            'plant_reviewed_at' => 'datetime',
            'status' => RequestStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function plantReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'plant_reviewed_by');
    }

    public function scopePlantApproved($query)
    {
        return $query->whereNot('plant_status', RequestStatus::Cancelled);
    }

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    public function productRequest(): BelongsTo
    {
        return $this->belongsTo(ProductRequest::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function quotations(): HasMany
    {
        return $this->purchases()->quotations();
    }

    public function purchaseOrders(): HasMany
    {
        return $this->purchases()->purchaseOrders();
    }
}
