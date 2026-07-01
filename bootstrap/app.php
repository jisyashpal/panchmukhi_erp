<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')
                ->group(base_path('routes/plant.php'));
            Route::middleware('web')
                ->group(base_path('routes/ho.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'permission' => \App\Http\Middleware\EnsurePermission::class,
            'plant_portal' => \App\Http\Middleware\EnsurePlantPortal::class,
            'super_admin' => \App\Http\Middleware\EnsureSuperAdmin::class,
            'ho_data_operator' => \App\Http\Middleware\EnsureHoDataOperator::class,
            'plant_user' => \App\Http\Middleware\EnsurePlantUser::class,
            'plant_role' => \App\Http\Middleware\EnsurePlantRole::class,
            'share_financial_year' => \App\Http\Middleware\ShareFinancialYear::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
