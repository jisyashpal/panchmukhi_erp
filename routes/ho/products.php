<?php

use App\Http\Controllers\Ho\Product\BrandController;
use App\Http\Controllers\Ho\Product\CategoryController;
use App\Http\Controllers\Ho\Product\ProductController;
use App\Http\Controllers\Ho\Product\SubCategoryController;
use App\Http\Controllers\Ho\Product\UnitController;
use App\Support\PermissionSlug;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:'.PermissionSlug::HO_MANAGE_PRODUCTS)->group(function () {
    Route::resource('categories', CategoryController::class)->except(['show']);
    Route::resource('sub-categories', SubCategoryController::class)->except(['show']);
    Route::resource('brands', BrandController::class)->except(['show']);
    Route::resource('units', UnitController::class)->except(['show']);
    Route::resource('products', ProductController::class)->except(['show']);
});
