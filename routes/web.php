<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    $user = $request->user();
    if (! $user) {
        return redirect()->route('login');
    }
    $user->loadMissing('role');

    return redirect()->route($user->defaultDashboardRoute());
});

Route::get('/dashboard', function (Request $request) {
    $user = $request->user();
    $user?->loadMissing('role');

    return redirect()->route($user?->defaultDashboardRoute() ?? 'login');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', function (Request $request) {
        $user = $request->user();
        $user->loadMissing('role');
        if ($user->isSuperAdmin()) {
            return redirect()->route('admin.profile.edit');
        }
        if ($user->isHoDataOperator()) {
            return redirect()->route('ho.profile.edit');
        }
        if ($user->hasSiteRole()) {
            return redirect()->route('site.profile.edit');
        }

        return redirect()->route('login');
    })->name('profile.edit');
});

require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
