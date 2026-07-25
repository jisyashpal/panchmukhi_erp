<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductUseEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'plant_id',
        'vehicle_id',
        'product_id',
        'replaced_product_id',
        'sub_category_id',
        'meter_type',
        'current_reading',
        'previous_reading',
        'quantity',
        'life_hours',
        'usage_count',
        'used_at',
        'recorded_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'life_hours' => 'decimal:2',
            'current_reading' => 'decimal:2',
            'previous_reading' => 'decimal:2',
            'usage_count' => 'integer',
            'used_at' => 'datetime',
        ];
    }

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function replacedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'replaced_product_id');
    }

    public function subCategory(): BelongsTo
    {
        return $this->belongsTo(SubCategory::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function meterUnitLabel(): string
    {
        return $this->meter_type === 'km' ? 'km' : 'hrs';
    }
}
