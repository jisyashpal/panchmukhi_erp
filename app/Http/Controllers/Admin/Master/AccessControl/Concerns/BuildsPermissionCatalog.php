<?php

namespace App\Http\Controllers\Admin\Master\AccessControl\Concerns;

use App\Models\Permission;
use Illuminate\Support\Collection;

trait BuildsPermissionCatalog
{
    /**
     * @return array<string, Collection<int, Permission>>
     */
    protected function permissionsByModule(): array
    {
        $grouped = Permission::query()
            ->orderBy('module')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->groupBy('module');

        $modules = config('permissions.modules', []);
        $ordered = [];

        foreach ($modules as $key => $label) {
            if ($grouped->has($key)) {
                $ordered[$key] = $grouped[$key];
            }
        }

        foreach ($grouped as $key => $items) {
            if (! isset($ordered[$key])) {
                $ordered[$key] = $items;
            }
        }

        return $ordered;
    }
}
