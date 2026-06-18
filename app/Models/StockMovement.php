<?php

namespace App\Models;

use App\Enums\StockMovementType;
use App\Enums\StockReferenceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $fillable = [
        'site_id',
        'product_id',
        'machine_id',
        'type',
        'quantity',
        'reference_type',
        'reference_id',
        'performed_by',
        'photo_path',
        'photo_confirmed',
        'reason',
        'notes',
        'moved_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'reference_type' => StockReferenceType::class,
            'quantity' => 'decimal:2',
            'photo_confirmed' => 'boolean',
            'moved_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
