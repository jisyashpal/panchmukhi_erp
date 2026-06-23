<?php

use App\Http\Controllers\Admin\Operation\DashboardController;
use App\Http\Controllers\Admin\Notifications\NotificationController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Api\StockCheckController;
use App\Http\Controllers\ProfileController;
use App\Support\PermissionSlug;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'permission:'.PermissionSlug::ADMIN_ACCESS])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        require __DIR__.'/admin/master.php';
        require __DIR__.'/admin/access-control.php';
        require __DIR__.'/admin/product.php';

        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

        Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
        Route::put('password', [PasswordController::class, 'update'])->name('password.update');
    });

Route::middleware('auth')->group(function () {
    Route::get('api/sites/{site}/products/{product}/stock', StockCheckController::class)->name('api.stock.check');
});
