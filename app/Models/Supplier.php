<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    /** @use HasFactory<\Database\Factories\SupplierFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'contact_person',
        'phone',
        'email',
        'gstin',
        'address',
        'opening_balance',
        'opening_balance_type',
        'opening_balance_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'opening_balance_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function hasOpeningBalance(): bool
    {
        return $this->opening_balance_date !== null
            && (float) $this->opening_balance > 0;
    }

    /** Debit (amount we owe) is positive. Credit (advance already paid) is negative. */
    public function signedOpeningBalance(): float
    {
        if (! $this->hasOpeningBalance()) {
            return 0.0;
        }

        $amount = round((float) $this->opening_balance, 2);

        return $this->opening_balance_type === 'credit' ? -$amount : $amount;
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }
}
