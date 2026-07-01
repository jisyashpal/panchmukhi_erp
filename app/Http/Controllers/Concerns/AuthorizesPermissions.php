<?php

namespace App\Http\Controllers\Concerns;

use App\Support\PermissionSlug;

trait AuthorizesPermissions
{
    protected function authorizePermission(string $permission): void
    {
        abort_unless(auth()->user()?->hasPermission($permission), 403);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    protected function authorizeAnyPermission(array $permissions): void
    {
        $user = auth()->user();

        foreach ($permissions as $permission) {
            if ($user?->hasPermission($permission)) {
                return;
            }
        }

        abort(403);
    }

    protected function authorizePurchaseReceipt(): void
    {
        if (auth()->user()?->isPurchaser()) {
            abort(403, 'Purchasers cannot receive stock.');
        }

        $this->authorizePermission(PermissionSlug::PLANT_MANAGE_STOCKS);
    }
}
