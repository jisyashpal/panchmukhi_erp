<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    $user = $request->user();
    if (!$user) {
        return redirect()->route('login');
    }
    $user->loadMissing('role.permissions');

    return redirect()->route($user->defaultDashboardRoute());
});

Route::get('/dashboard', function (Request $request) {
    $user = $request->user();
    $user?->loadMissing('role.permissions');

    return redirect()->route($user?->defaultDashboardRoute() ?? 'login');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', function (Request $request) {
        $user = $request->user();
        $user->loadMissing('role.permissions');

        if ($user->canAccessAdmin()) {
            return redirect()->route('admin.profile.edit');
        }
        if ($user->canAccessHo()) {
            return redirect()->route('ho.profile.edit');
        }
        if ($user->canAccessPlant()) {
            return redirect()->route('plant.profile.edit');
        }

        return redirect()->route('login');
    })->name('profile.edit');
});

require __DIR__ . '/auth.php';
require __DIR__ . '/admin.php';
require __DIR__ . '/shared-purchases.php';
