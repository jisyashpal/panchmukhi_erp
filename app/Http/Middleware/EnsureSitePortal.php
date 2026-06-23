<?php

namespace App\Http\Middleware;

use App\Support\PermissionSlug;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSitePortal
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $user?->loadMissing('role.permissions');

        if (
            ! $user
            || ! $user->is_active
            || ! $user->site_id
            || ! $user->hasPermission(PermissionSlug::SITE_ACCESS)
        ) {
            abort(403, 'Site portal access required.');
        }

        return $next($request);
    }
}
