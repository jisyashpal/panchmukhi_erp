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
        'site_id',
        'product_request_id',
        'requested_by',
        'site_status',
        'site_reviewed_by',
        'site_reviewed_at',
        'site_decline_reason',
        'status',
        'remarks',
        'decline_reason',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'site_status' => RequestStatus::class,
            'site_reviewed_at' => 'datetime',
            'status' => RequestStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function siteReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'site_reviewed_by');
    }

    public function scopeSiteApproved($query)
    {
        return $query->whereNot('site_status', RequestStatus::Cancelled);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
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
}
