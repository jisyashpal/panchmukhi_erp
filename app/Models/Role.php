<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    public const SLUG_SUPER_ADMIN = 'super_admin';

    public const SLUG_HO_DATA_OPERATOR = 'ho_data_operator';

    public const SLUG_SITE_IN_CHARGE = 'site_in_charge';

    public const SLUG_PURCHASER = 'purchaser';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'requires_site',
        'is_system',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'requires_site' => 'boolean',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->slug === self::SLUG_SUPER_ADMIN;
    }

    public function isHoDataOperator(): bool
    {
        return $this->slug === self::SLUG_HO_DATA_OPERATOR;
    }
}
