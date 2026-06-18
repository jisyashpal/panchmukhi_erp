<?php

namespace App\Models;

use App\Enums\UserApprovalStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'site_id',
        'is_active',
        'approval_status',
        'created_by',
        'rejection_reason',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'approval_status' => UserApprovalStatus::class,
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isApproved(): bool
    {
        return $this->approval_status === UserApprovalStatus::Approved;
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role?->isSuperAdmin() ?? false;
    }

    public function isHoDataOperator(): bool
    {
        return $this->roleSlug() === Role::SLUG_HO_DATA_OPERATOR;
    }

    public function hasSiteRole(): bool
    {
        return $this->role?->requires_site ?? false;
    }

    public function roleSlug(): ?string
    {
        return $this->role?->slug;
    }

    public function isSiteInCharge(): bool
    {
        return $this->roleSlug() === Role::SLUG_SITE_IN_CHARGE;
    }

    public function isPurchaser(): bool
    {
        return $this->roleSlug() === Role::SLUG_PURCHASER;
    }

    public function canCreateProductRequests(): bool
    {
        return $this->isSiteInCharge();
    }

    public function canManagePurchaseRequests(): bool
    {
        return $this->isPurchaser() || $this->isSiteInCharge();
    }

    public function canManageSiteStocks(): bool
    {
        return $this->isPurchaser();
    }

    public function canManageSuppliers(): bool
    {
        return $this->isPurchaser();
    }

    public function defaultDashboardRoute(): string
    {
        if ($this->isSuperAdmin()) {
            return 'admin.dashboard';
        }
        if ($this->isHoDataOperator()) {
            return 'ho.dashboard';
        }

        return 'site.dashboard';
    }
}
