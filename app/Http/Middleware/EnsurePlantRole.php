<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlantRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $user?->loadMissing('role');

        $slug = $user?->role?->slug;

        if (! $slug || ! in_array($slug, $roles, true)) {
            abort(403, 'You do not have permission for this action.');
        }

        return $next($request);
    }
}
