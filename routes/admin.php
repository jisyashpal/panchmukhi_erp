<?php

use App\Http\Controllers\Admin\Operation\DashboardController;
use App\Http\Controllers\Admin\Notifications\NotificationController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Api\StockCheckController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\Master\PlantController;
use App\Http\Controllers\Admin\Master\SupplierController;
use App\Http\Controllers\Admin\Master\UserController;
use App\Http\Controllers\Admin\Master\AccessControl\PermissionController;
use App\Http\Controllers\Admin\Master\AccessControl\RoleController;
use App\Http\Controllers\Admin\Product\BrandController;
use App\Http\Controllers\Admin\Product\CategoryController;
use App\Http\Controllers\Admin\Product\ProductController;
use App\Http\Controllers\Admin\Product\SubCategoryController;
use App\Http\Controllers\Admin\Product\UnitController;
use App\Support\PermissionSlug;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'permission:'.PermissionSlug::ADMIN_ACCESS])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        // Master Routes
        Route::middleware('permission:'.PermissionSlug::ADMIN_MANAGE_PLANTS)
            ->resource('plants', PlantController::class)->except(['show']);

        Route::middleware('permission:'.PermissionSlug::ADMIN_MANAGE_SUPPLIERS)
            ->resource('suppliers', SupplierController::class)->except(['show']);

        Route::middleware('permission:'.PermissionSlug::ADMIN_MANAGE_USERS)
            ->resource('users', UserController::class)->except(['show']);

        // Access Control Routes
        Route::middleware('permission:'.PermissionSlug::ADMIN_MANAGE_ROLES)
            ->resource('roles', RoleController::class)
            ->except(['show']);

        Route::middleware('permission:'.PermissionSlug::ADMIN_MANAGE_PERMISSIONS)
            ->resource('permissions', PermissionController::class)
            ->except(['show']);

        // Product Catalog Routes
        Route::middleware('permission:'.PermissionSlug::ADMIN_MANAGE_PRODUCTS)->group(function () {
            Route::resource('categories', CategoryController::class)->except(['show']);
            Route::resource('sub-categories', SubCategoryController::class)->except(['show']);
            Route::resource('brands', BrandController::class)->except(['show']);
            Route::resource('units', UnitController::class)->except(['show']);
            Route::resource('products', ProductController::class)->except(['show']);
        });

        // Notifications & Profile
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

        Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
        Route::put('password', [PasswordController::class, 'update'])->name('password.update');
    });

Route::middleware('auth')->group(function () {
    Route::get('api/plants/{plant}/products/{product}/stock', StockCheckController::class)->name('api.stock.check');
});
