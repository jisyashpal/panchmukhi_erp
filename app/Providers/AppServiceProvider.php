<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
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

        // Prevent 419 when APP_URL (localhost) differs from browser URL (127.0.0.1)
        if ($this->app->environment('local') && ! $this->app->runningInConsole()) {
            $request = request();
            if ($request->hasHeader('Host')) {
                URL::forceRootUrl($request->getSchemeAndHttpHost());
            }
        }
    }
}
