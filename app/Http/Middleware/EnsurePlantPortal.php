<?php

namespace App\Http\Middleware;

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

        if (
            ! $user
            || ! $user->is_active
            || ! $user->plant_id
            || ! $user->hasPermission(PermissionSlug::PLANT_ACCESS)
        ) {
            abort(403, 'Plant portal access required.');
        }

        return $next($request);
    }
}
