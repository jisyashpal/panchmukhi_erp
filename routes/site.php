<?php

use App\Http\Controllers\Api\StockCheckController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Site\DashboardController;
use App\Http\Controllers\Site\DispatchController;
use App\Http\Controllers\Site\NotificationController;
use App\Http\Controllers\Site\ProductRequestController;
use App\Http\Controllers\Site\PurchaseController;
use App\Http\Controllers\Site\PurchaseRequestController;
use App\Http\Controllers\Site\SupplierController;
use App\Models\Role;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'site_user'])
    ->prefix('site')
    ->name('site.')
    ->group(function () {
        Route::redirect('/', '/site/dashboard');
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        Route::get('profile', [ProfileController::class, 'editSite'])->name('profile.edit');
        Route::patch('profile', [ProfileController::class, 'updateSite'])->name('profile.update');
        Route::put('profile/password', [PasswordController::class, 'update'])->name('password.update');
        Route::delete('profile', [ProfileController::class, 'destroySite'])->name('profile.destroy');

        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

        Route::get('api/sites/{site}/products/{product}/stock', StockCheckController::class)
            ->name('api.stock.check');

        Route::middleware('site_role:'.Role::SLUG_SITE_IN_CHARGE)->group(function () {
            Route::get('product-requests', [ProductRequestController::class, 'index'])->name('product-requests.index');
            Route::get('product-requests/create', [ProductRequestController::class, 'create'])->name('product-requests.create');
            Route::post('product-requests', [ProductRequestController::class, 'store'])->name('product-requests.store');
            Route::get('product-requests/{product_request}', [ProductRequestController::class, 'show'])->name('product-requests.show');
        });

        Route::middleware('site_role:'.Role::SLUG_SITE_IN_CHARGE.','.Role::SLUG_PURCHASER)->group(function () {
            Route::get('purchase-requests', [PurchaseRequestController::class, 'index'])->name('purchase-requests.index');
            Route::get('purchase-requests/{purchase_request}', [PurchaseRequestController::class, 'show'])->name('purchase-requests.show');
        });

        Route::middleware('site_role:'.Role::SLUG_PURCHASER)->group(function () {
            Route::get('suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
            Route::get('suppliers/create', [SupplierController::class, 'create'])->name('suppliers.create');
            Route::post('suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
            Route::get('suppliers/{supplier}/edit', [SupplierController::class, 'edit'])->name('suppliers.edit');
            Route::put('suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');

            Route::get('purchase-requests/create', [PurchaseRequestController::class, 'create'])->name('purchase-requests.create');
            Route::post('purchase-requests', [PurchaseRequestController::class, 'store'])->name('purchase-requests.store');
            Route::get('purchase-requests/{purchase_request}/edit', [PurchaseRequestController::class, 'edit'])->name('purchase-requests.edit');
            Route::put('purchase-requests/{purchase_request}', [PurchaseRequestController::class, 'update'])->name('purchase-requests.update');
            Route::post('purchase-requests/{purchase_request}/status', [PurchaseRequestController::class, 'updateStatus'])->name('purchase-requests.status');

            Route::get('dispatch', [DispatchController::class, 'index'])->name('dispatch.index');
            Route::post('dispatch/{purchase}', [DispatchController::class, 'dispatch'])->name('dispatch.store');

            Route::get('purchases', [PurchaseController::class, 'index'])->name('purchases.index');
            Route::get('purchases/{purchase}/receive', [PurchaseController::class, 'receiveForm'])->name('purchases.receive');
            Route::post('purchases/{purchase}/receive', [PurchaseController::class, 'receive'])->name('purchases.receive.store');
        });
    });
