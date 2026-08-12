<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class SharePortalLayout
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        View::share(
            'portalLayout',
            $user?->canAccessAdmin() ? 'layouts.admin' : 'layouts.ho'
        );

        return $next($request);
    }
}
