<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHoDataOperator
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $user?->loadMissing('role');

        if (! $user || ! $user->is_active) {
            abort(403, 'HO access required.');
        }

        if (! $user->isHoDataOperator() && ! $user->isSuperAdmin()) {
            abort(403, 'HO Data Operator access required.');
        }

        return $next($request);
    }
}
