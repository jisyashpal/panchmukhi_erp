<?php

namespace App\Http\Middleware;

use App\Models\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlantUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $user?->loadMissing('role');

        $isPurchaser = $user?->role?->slug === Role::SLUG_PURCHASER;

        if (
            ! $user
            || ! $user->hasPlantRole()
            || ! $user->is_active
            || (! $user->plant_id && ! $isPurchaser)
        ) {
            abort(403, 'Plant user access required.');
        }

        return $next($request);
    }
}
