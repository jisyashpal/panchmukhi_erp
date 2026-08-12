<?php

namespace App\Http\Middleware;

use App\Models\Role;
use App\Support\PermissionSlug;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlantPortal
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $user?->loadMissing('role.permissions');

        $isPurchaser = $user?->role?->slug === Role::SLUG_PURCHASER;

        if (
            ! $user
            || ! $user->is_active
            || ! $user->hasPermission(PermissionSlug::PLANT_ACCESS)
            || (! $user->plant_id && ! $isPurchaser)
        ) {
            abort(403, 'Plant portal access required.');
        }

        return $next($request);
    }
}
