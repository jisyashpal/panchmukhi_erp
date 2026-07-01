<?php

use App\Http\Controllers\Ho\Inventory\ProductLifeController;
use App\Http\Controllers\Ho\Inventory\StockController;
use App\Http\Controllers\Ho\Inventory\StockInController;
use App\Http\Controllers\Ho\Inventory\StockOutController;
use App\Support\PermissionSlug;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:'.PermissionSlug::HO_MANAGE_STOCKS)->group(function () {
    Route::get('stocks', [StockController::class, 'index'])->name('stocks.index');
    Route::get('stocks/movements', [StockController::class, 'show'])->name('stocks.show');
    Route::get('stocks/stock-in', [StockInController::class, 'create'])->name('stocks.stock-in');
    Route::post('stocks/stock-in', [StockInController::class, 'store'])->name('stocks.stock-in.store');
    Route::put('stocks/stock-in/{purchase}', [StockInController::class, 'updatePurchase'])->name('stocks.stock-in.update-purchase');
    Route::get('stocks/stock-out', [StockOutController::class, 'create'])->name('stocks.stock-out');
    Route::post('stocks/stock-out', [StockOutController::class, 'store'])->name('stocks.stock-out.store');
});

Route::middleware('permission:'.PermissionSlug::HO_MANAGE_PRODUCT_LIFE)->group(function () {
    Route::get('product-life', [ProductLifeController::class, 'index'])->name('product-life.index');
    Route::get('product-life/last-entry', [ProductLifeController::class, 'lastEntry'])->name('product-life.last-entry');
    Route::get('product-life/create', [ProductLifeController::class, 'create'])->name('product-life.create');
    Route::post('product-life', [ProductLifeController::class, 'store'])->name('product-life.store');
});
