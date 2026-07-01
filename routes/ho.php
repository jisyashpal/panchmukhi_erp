<?php

use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Ho\FinancialYearController;
use App\Http\Controllers\Ho\Operation\DashboardController;
use App\Http\Controllers\Ho\Notifications\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Support\PermissionSlug;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'permission:'.PermissionSlug::HO_ACCESS])
    ->prefix('ho')
    ->name('ho.')
    ->group(function () {
        Route::get('profile', [ProfileController::class, 'editHo'])->name('profile.edit');
        Route::patch('profile', [ProfileController::class, 'updateHo'])->name('profile.update');
        Route::put('profile/password', [PasswordController::class, 'update'])->name('password.update');
        Route::delete('profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

        require __DIR__.'/ho/products.php';

        Route::middleware('share_financial_year')->group(function () {
            Route::redirect('/', '/ho/dashboard');
            Route::get('/dashboard', DashboardController::class)->name('dashboard');
            Route::post('financial-year', FinancialYearController::class)->name('financial-year.set');

            require __DIR__.'/ho/request-po.php';
            require __DIR__.'/ho/inventory.php';
            require __DIR__.'/ho/reports.php';
        });
    });
