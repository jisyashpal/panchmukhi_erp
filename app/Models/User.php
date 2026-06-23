<?php

namespace App\Models;

use App\Enums\UserApprovalStatus;
use App\Support\PermissionSlug;
use Illuminate\Database\Eloquent\Builder;
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

    public function roleSlug(): ?string
    {
        return $this->role?->slug;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role?->isSuperAdmin() ?? false;
    }

    /**
     * Compatibility helpers.
     * Older codebase (controllers/blades) uses role-style methods.
     * We re-map them to permission/module capabilities.
     */
    public function isHoDataOperator(): bool
    {
        return $this->canAccessHo();
    }

    public function isSiteInCharge(): bool
    {
        return $this->canCreateProductRequests();
    }

    public function isPurchaser(): bool
    {
        return $this->canManagePurchaseRequests();
    }

    public function hasSiteRole(): bool
    {
        return $this->role?->requires_site ?? false;
    }

    public function hasPermission(string $slug): bool
    {
        $this->loadMissing('role.permissions');

        return $this->role?->hasPermission($slug) ?? false;
    }

    public function canAccessAdmin(): bool
    {
        return $this->hasPermission(PermissionSlug::ADMIN_ACCESS);
    }

    public function canAccessHo(): bool
    {
        return $this->hasPermission(PermissionSlug::HO_ACCESS);
    }

    public function canViewHoPurchases(): bool
    {
        return $this->hasPermission(PermissionSlug::HO_VIEW_PURCHASES);
    }

    public function canManageHoProducts(): bool
    {
        return $this->hasPermission(PermissionSlug::HO_MANAGE_PRODUCTS);
    }

    public function canAccessSite(): bool
    {
        return $this->hasPermission(PermissionSlug::SITE_ACCESS) && $this->site_id !== null;
    }

    public function canCreateProductRequests(): bool
    {
        return $this->hasPermission(PermissionSlug::SITE_CREATE_PRODUCT_REQUESTS);
    }

    public function canViewProductRequests(): bool
    {
        return $this->hasPermission(PermissionSlug::SITE_VIEW_PRODUCT_REQUESTS);
    }

    public function canManagePurchaseRequests(): bool
    {
        return $this->hasPermission(PermissionSlug::SITE_MANAGE_PURCHASE_REQUESTS);
    }

    public function canManageSiteStocks(): bool
    {
        return $this->hasPermission(PermissionSlug::SITE_MANAGE_STOCKS);
    }

    public function canManageSuppliers(): bool
    {
        return $this->hasPermission(PermissionSlug::SITE_MANAGE_SUPPLIERS);
    }

    public function canManageSitePurchases(): bool
    {
        return $this->hasPermission(PermissionSlug::SITE_MANAGE_PURCHASES);
    }

    public function canViewSitePurchases(): bool
    {
        return $this->hasPermission(PermissionSlug::SITE_VIEW_PURCHASES);
    }

    public function canDispatchOrders(): bool
    {
        return $this->hasPermission(PermissionSlug::SITE_DISPATCH_ORDERS);
    }

    public function canEditProductRequests(): bool
    {
        return $this->hasPermission(PermissionSlug::SITE_MANAGE_PURCHASE_REQUESTS);
    }

    public function scopeWithPermission(Builder $query, string $permission): Builder
    {
        return $query->whereHas('role.permissions', fn (Builder $q) => $q->where('slug', $permission));
    }

    public function defaultDashboardRoute(): string
    {
        if ($this->canAccessAdmin()) {
            return 'admin.dashboard';
        }

        if ($this->canAccessHo()) {
            return 'ho.dashboard';
        }

        if ($this->canAccessSite()) {
            return 'site.dashboard';
        }

        return 'login';
    }
}
