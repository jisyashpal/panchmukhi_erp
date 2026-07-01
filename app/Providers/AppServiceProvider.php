<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        Gate::before(function (?User $user, string $ability) {
            if (! $user || ! str_contains($ability, '.')) {
                return null;
            }

            $module = explode('.', $ability, 2)[0];

            if (! in_array($module, ['admin', 'ho', 'plant'], true)) {
                return null;
            }

            return $user->hasPermission($ability);
        });

        // Prevent 419 when APP_URL (localhost) differs from browser URL (127.0.0.1)
        if ($this->app->environment('local') && ! $this->app->runningInConsole()) {
            $request = request();
            if ($request->hasHeader('Host')) {
                URL::forceRootUrl($request->getSchemeAndHttpHost());
            }
        }
    }
}
