<?php

use App\Http\Controllers\Api\StockCheckController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Plant\Operation\DashboardController;
use App\Http\Controllers\Plant\Notifications\NotificationController;
use App\Http\Controllers\Plant\RequestPo\DispatchController;
use App\Http\Controllers\Plant\RequestPo\ProductRequestController;
use App\Http\Controllers\Plant\RequestPo\PurchaseController;
use App\Http\Controllers\Plant\RequestPo\SupplierController;
use App\Http\Controllers\Plant\Inventory\StockController;
use App\Http\Controllers\Plant\Product\BrandController;
use App\Http\Controllers\Plant\Product\CategoryController;
use App\Http\Controllers\Plant\Product\ProductController;
use App\Http\Controllers\Plant\Product\SubCategoryController;
use App\Http\Controllers\Plant\Product\UnitController;
use App\Support\PermissionSlug;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'plant_portal'])
    ->prefix('plant')
    ->name('plant.')
    ->group(function () {
        Route::redirect('/', '/plant/dashboard');
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        Route::get('profile', [ProfileController::class, 'editPlant'])->name('profile.edit');
        Route::patch('profile', [ProfileController::class, 'updatePlant'])->name('profile.update');
        Route::put('profile/password', [PasswordController::class, 'update'])->name('password.update');
        Route::delete('profile', [ProfileController::class, 'destroyPlant'])->name('profile.destroy');

        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

        Route::get('api/plants/{plant}/products/{product}/stock', StockCheckController::class)
            ->name('api.stock.check');

        Route::middleware('permission:'.PermissionSlug::PLANT_CREATE_PRODUCT_REQUESTS)->group(function () {
            Route::get('product-requests/create', [ProductRequestController::class, 'create'])->name('product-requests.create');
            Route::post('product-requests', [ProductRequestController::class, 'store'])->name('product-requests.store');
        });

        Route::middleware('permission:'.PermissionSlug::PLANT_MANAGE_STOCKS)->group(function () {
            Route::get('stocks', [StockController::class, 'index'])->name('stocks.index');
            Route::get('purchases/{purchase}/receive', [PurchaseController::class, 'receiveForm'])->name('purchases.receive');
            Route::post('purchases/{purchase}/receive', [PurchaseController::class, 'receive'])->name('purchases.receive.store');
        });

        Route::middleware('permission:'.PermissionSlug::PLANT_VIEW_PRODUCT_REQUESTS)->group(function () {
            Route::get('product-requests', [ProductRequestController::class, 'index'])->name('product-requests.index');
            Route::get('product-requests/{product_request}', [ProductRequestController::class, 'show'])->name('product-requests.show');
        });

        Route::middleware('permission:'.PermissionSlug::PLANT_VIEW_PURCHASES.','.PermissionSlug::PLANT_MANAGE_PURCHASES)->group(function () {
            Route::get('purchases', [PurchaseController::class, 'index'])->name('purchases.index');
        });

        Route::middleware('permission:'.PermissionSlug::PLANT_MANAGE_PURCHASES)->group(function () {
            Route::get('purchases/bulk-upload', [PurchaseController::class, 'bulkUploadForm'])->name('purchases.bulk-upload');
            Route::post('purchases/bulk-upload', [PurchaseController::class, 'bulkUpload'])->name('purchases.bulk-upload.store');
            Route::get('purchases/bulk-upload/sample', [PurchaseController::class, 'downloadBulkSample'])->name('purchases.bulk-upload.sample');
            Route::get('purchases/create', [PurchaseController::class, 'create'])->name('purchases.create');
            Route::post('purchases', [PurchaseController::class, 'store'])->name('purchases.store');
            Route::get('product-requests/{product_request}/export-supplier', [ProductRequestController::class, 'exportForSupplier'])->name('product-requests.export-supplier');
            Route::post('product-requests/export-supplier', [ProductRequestController::class, 'bulkExportForSupplier'])->name('product-requests.bulk-export-supplier');
            Route::post('product-requests/{product_request}/convert', [ProductRequestController::class, 'convertToPurchaseRequest'])->name('product-requests.convert');
            Route::post('product-requests/bulk-convert', [ProductRequestController::class, 'bulkConvertToPurchaseRequest'])->name('product-requests.bulk-convert');
        });

        Route::middleware('permission:'.PermissionSlug::PLANT_VIEW_PURCHASES.','.PermissionSlug::PLANT_MANAGE_PURCHASES)->group(function () {
            Route::get('purchases/{purchase}', [PurchaseController::class, 'show'])->name('purchases.show');
        });

        Route::middleware('permission:'.PermissionSlug::PLANT_MANAGE_PURCHASES)->group(function () {
            Route::get('purchases/{purchase}/pdf', [PurchaseController::class, 'pdf'])->name('purchases.pdf');
            Route::get('purchases/{purchase}/excel', [PurchaseController::class, 'excel'])->name('purchases.excel');
            Route::get('purchases/{purchase}/word', [PurchaseController::class, 'word'])->name('purchases.word');
            Route::get('purchases/{purchase}/share-links', [PurchaseController::class, 'shareLinks'])->name('purchases.share-links');
            Route::post('purchases/{purchase}/email', [PurchaseController::class, 'email'])->name('purchases.email');
        });

        Route::middleware('permission:'.PermissionSlug::PLANT_MANAGE_PURCHASES.','.PermissionSlug::PLANT_DISPATCH_ORDERS)->group(function () {
            Route::get('purchases/{purchase}/edit', [PurchaseController::class, 'edit'])->name('purchases.edit');
            Route::put('purchases/{purchase}', [PurchaseController::class, 'update'])->name('purchases.update');
        });

        Route::middleware('permission:'.PermissionSlug::PLANT_MANAGE_PURCHASE_REQUESTS)->group(function () {
            Route::delete('product-requests/items/{product_request_item}', [ProductRequestController::class, 'destroyItem'])->name('product-requests.items.destroy');
            Route::get('product-requests/{product_request}/edit', [ProductRequestController::class, 'edit'])->name('product-requests.edit');
            Route::put('product-requests/{product_request}', [ProductRequestController::class, 'update'])->name('product-requests.update');
        });

        Route::middleware('permission:'.PermissionSlug::PLANT_MANAGE_SUPPLIERS)->group(function () {
            Route::get('suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
            Route::get('suppliers/create', [SupplierController::class, 'create'])->name('suppliers.create');
            Route::post('suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
            Route::get('suppliers/{supplier}/edit', [SupplierController::class, 'edit'])->name('suppliers.edit');
            Route::put('suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
        });

        Route::middleware('permission:'.PermissionSlug::PLANT_DISPATCH_ORDERS)->group(function () {
            Route::get('dispatch', [DispatchController::class, 'index'])->name('dispatch.index');
            Route::get('dispatch/{purchase}', [DispatchController::class, 'show'])->name('dispatch.show');
            Route::post('dispatch/{purchase}', [DispatchController::class, 'dispatch'])->name('dispatch.store');
        });

        Route::middleware('permission:'.PermissionSlug::PLANT_MANAGE_PRODUCTS)->group(function () {
            Route::resource('categories', CategoryController::class)->except(['show']);
            Route::resource('sub-categories', SubCategoryController::class)->except(['show']);
            Route::resource('brands', BrandController::class)->except(['show']);
            Route::resource('units', UnitController::class)->except(['show']);
            Route::resource('products', ProductController::class)->except(['show']);
        });
    });
