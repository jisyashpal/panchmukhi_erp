<?php

use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Ho\DashboardController;
use App\Http\Controllers\Ho\ProductLifeController;
use App\Http\Controllers\Ho\ProductRequestController;
use App\Http\Controllers\Ho\PurchaseController;
use App\Http\Controllers\Ho\PurchaseListController;
use App\Http\Controllers\Ho\ReportController;
use App\Http\Controllers\Ho\StockController;
use App\Http\Controllers\Ho\StockInController;
use App\Http\Controllers\Ho\StockOutController;
use App\Http\Controllers\Ho\NotificationController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'ho_data_operator'])
    ->prefix('ho')
    ->name('ho.')
    ->group(function () {
        Route::redirect('/', '/ho/dashboard');
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        Route::get('profile', [ProfileController::class, 'editHo'])->name('profile.edit');
        Route::patch('profile', [ProfileController::class, 'updateHo'])->name('profile.update');
        Route::put('profile/password', [PasswordController::class, 'update'])->name('password.update');

        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

        Route::get('product-requests', [ProductRequestController::class, 'index'])->name('product-requests.index');
        Route::get('product-requests/{product_request}', [ProductRequestController::class, 'show'])->name('product-requests.show');
        Route::get('product-requests/{product_request}/edit', [ProductRequestController::class, 'edit'])->name('product-requests.edit');
        Route::put('product-requests/{product_request}', [ProductRequestController::class, 'update'])->name('product-requests.update');
        Route::post('product-requests/{product_request}/status', [ProductRequestController::class, 'updateStatus'])->name('product-requests.status');

        Route::get('purchase-list', [PurchaseListController::class, 'index'])->name('purchase-list.index');

        Route::get('purchases', [PurchaseController::class, 'index'])->name('purchases.index');
        Route::get('purchases/create', [PurchaseController::class, 'create'])->name('purchases.create');
        Route::post('purchases', [PurchaseController::class, 'store'])->name('purchases.store');
        Route::get('purchases/{purchase}', [PurchaseController::class, 'show'])->name('purchases.show');

        Route::get('stocks', [StockController::class, 'index'])->name('stocks.index');
        Route::get('stocks/stock-in', [StockInController::class, 'create'])->name('stocks.stock-in');
        Route::post('stocks/stock-in', [StockInController::class, 'store'])->name('stocks.stock-in.store');
        Route::get('stocks/stock-out', [StockOutController::class, 'create'])->name('stocks.stock-out');
        Route::post('stocks/stock-out', [StockOutController::class, 'store'])->name('stocks.stock-out.store');

        Route::get('product-life', [ProductLifeController::class, 'index'])->name('product-life.index');
        Route::get('product-life/create', [ProductLifeController::class, 'create'])->name('product-life.create');
        Route::post('product-life', [ProductLifeController::class, 'store'])->name('product-life.store');

        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/product-life', [ReportController::class, 'productLife'])->name('reports.product-life');
        Route::get('reports/category-purchase', [ReportController::class, 'categoryPurchase'])->name('reports.category-purchase');
        Route::get('reports/multiple-usage', [ReportController::class, 'multipleUsage'])->name('reports.multiple-usage');
        Route::get('reports/usage-by-category', [ReportController::class, 'usageByCategory'])->name('reports.usage-by-category');
    });
