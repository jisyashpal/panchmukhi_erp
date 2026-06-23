<?php

use App\Http\Controllers\Ho\RequestPo\ProductRequestController;
use App\Http\Controllers\Ho\RequestPo\PurchaseController;
use App\Http\Controllers\Ho\RequestPo\PurchaseListController;
use App\Support\PermissionSlug;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:'.PermissionSlug::HO_MANAGE_PRODUCT_REQUESTS)->group(function () {
    Route::get('product-requests', [ProductRequestController::class, 'index'])->name('product-requests.index');
    Route::get('product-requests/create', [ProductRequestController::class, 'create'])->name('product-requests.create');
    Route::post('product-requests', [ProductRequestController::class, 'store'])->name('product-requests.store');
    Route::get('product-requests/{product_request}', [ProductRequestController::class, 'show'])->name('product-requests.show');
    Route::get('product-requests/{product_request}/edit', [ProductRequestController::class, 'edit'])->name('product-requests.edit');
    Route::put('product-requests/{product_request}', [ProductRequestController::class, 'update'])->name('product-requests.update');
    Route::post('product-requests/{product_request}/status', [ProductRequestController::class, 'updateStatus'])->name('product-requests.status');
});

Route::middleware('permission:'.PermissionSlug::HO_VIEW_PURCHASES)->group(function () {
    Route::get('purchase-list', [PurchaseListController::class, 'index'])->name('purchase-list.index');
    Route::get('purchases', [PurchaseController::class, 'index'])->name('purchases.index');
});

Route::middleware('permission:'.PermissionSlug::HO_VIEW_PURCHASES)->group(function () {
    Route::get('purchases/{purchase}', [PurchaseController::class, 'show'])->name('purchases.show');
    Route::get('purchases/{purchase}/pdf', [PurchaseController::class, 'pdf'])->name('purchases.pdf');
    Route::get('purchases/{purchase}/share-links', [PurchaseController::class, 'shareLinks'])->name('purchases.share-links');
    Route::post('purchases/{purchase}/email', [PurchaseController::class, 'email'])->name('purchases.email');
    Route::post('purchases/{purchase}/comments', [PurchaseController::class, 'storeComment'])->name('purchases.comments.store');
});
