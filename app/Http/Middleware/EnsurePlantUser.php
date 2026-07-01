<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSiteUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $user?->loadMissing('role');

        if (! $user || ! $user->hasSiteRole() || ! $user->is_active || ! $user->site_id) {
            abort(403, 'Site user access required.');
        }

        return $next($request);
    }
}
