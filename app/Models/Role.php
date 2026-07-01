<?php

namespace App\Models;

use Illuminate\Support\Arr;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    public const SLUG_SUPER_ADMIN = 'super_admin';

    public const SLUG_HO_DATA_OPERATOR = 'ho_data_operator';

    public const SLUG_PLANT_IN_CHARGE = 'plant_in_charge';

    public const SLUG_PURCHASER = 'purchaser';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'requires_plant',
        'is_system',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'requires_plant' => 'boolean',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function hasPermission(string $slug): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($this->relationLoaded('permissions')) {
            if ($this->permissions->contains('slug', $slug)) {
                return true;
            }
        }

        // If permissions catalog is not seeded yet, allow system roles based on defaults.
        // This keeps the app usable (and tests passing) even when `db:seed` isn't executed.
        $permissionExists = Permission::query()->where('slug', $slug)->exists();
        if (! $permissionExists) {
            $definition = collect(config('permissions.defaults', []))
                ->firstWhere('slug', $slug);

            if (! $definition) {
                return false;
            }

            $roles = Arr::wrap($definition['roles'] ?? []);

            return in_array($this->slug, $roles, true);
        }

        return $this->permissions()->where('slug', $slug)->exists();
    }

    public function syncPermissions(array $permissionIds): void
    {
        $this->permissions()->sync($permissionIds);
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
