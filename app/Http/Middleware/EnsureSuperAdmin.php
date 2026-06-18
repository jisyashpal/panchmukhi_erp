<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $user?->loadMissing('role');

        if (! $user || ! $user->isSuperAdmin() || ! $user->is_active) {
            abort(403, 'Super Admin access required.');
        }

        return $next($request);
    }
}
