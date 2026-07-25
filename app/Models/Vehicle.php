<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'registration_no',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function productUseEntries(): HasMany
    {
        return $this->hasMany(ProductUseEntry::class);
    }

    public function displayLabel(): string
    {
        if (filled($this->registration_no)) {
            return $this->name.' ('.$this->registration_no.')';
        }

        return $this->name;
    }
}
