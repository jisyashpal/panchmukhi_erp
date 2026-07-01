<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Arr;

class MenuBuilder
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function visibleItems(string $portal, ?User $user = null): array
    {
        $user ??= auth()->user();

        if (! $user) {
            return [];
        }

        $user->loadMissing('role.permissions');

        return collect(config("menus.{$portal}.items", []))
            ->filter(fn (array $item) => static::itemVisible($item, $user))
            ->map(function (array $item) use ($user) {
                if (($item['type'] ?? '') === 'group') {
                    $item['children'] = collect($item['children'] ?? [])
                        ->filter(fn (array $child) => static::itemVisible($child, $user))
                        ->values()
                        ->all();
                }

                return $item;
            })
            ->filter(function (array $item) {
                if (($item['type'] ?? '') === 'group') {
                    return count($item['children'] ?? []) > 0;
                }

                return true;
            })
            ->values()
            ->all();
    }

    public static function itemVisible(array $item, User $user): bool
    {
        if (isset($item['hide_if']) && $user->hasPermission($item['hide_if'])) {
            return false;
        }

        if (isset($item['any_permission'])) {
            foreach ($item['any_permission'] as $permission) {
                if ($user->hasPermission($permission)) {
                    return true;
                }
            }

            return false;
        }

        $permission = $item['permission'] ?? null;

        return $permission ? $user->hasPermission($permission) : true;
    }

    public static function isActive(array $routes): bool
    {
        foreach ($routes as $pattern) {
            if (request()->routeIs($pattern)) {
                return true;
            }
        }

        return false;
    }

    public static function groupOpen(array $item): bool
    {
        $routes = $item['routes'] ?? [];

        if ($routes !== []) {
            return static::isActive($routes);
        }

        foreach ($item['children'] ?? [] as $child) {
            if (static::isActive($child['routes'] ?? [])) {
                return true;
            }
        }

        return false;
    }

    public static function portalLabel(string $portal, ?User $user = null): string
    {
        $user ??= auth()->user();

        return match ($portal) {
            'plant' => $user?->hasPermission(PermissionSlug::PLANT_DISPATCH_ORDERS) ? 'Procurement Menu' : 'Plant Menu',
            'ho' => config('menus.ho.label', 'HO Operations'),
            'admin' => config('menus.admin.label', 'Main Menu'),
            default => 'Menu',
        };
    }
}
