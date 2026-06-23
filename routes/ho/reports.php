<?php

use App\Http\Controllers\Ho\Reports\ReportController;
use App\Support\PermissionSlug;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:'.PermissionSlug::HO_VIEW_REPORTS)->group(function () {
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/product-life', [ReportController::class, 'productLife'])->name('reports.product-life');
    Route::get('reports/category-purchase', [ReportController::class, 'categoryPurchase'])->name('reports.category-purchase');
    Route::get('reports/multiple-usage', [ReportController::class, 'multipleUsage'])->name('reports.multiple-usage');
    Route::get('reports/usage-by-category', [ReportController::class, 'usageByCategory'])->name('reports.usage-by-category');
});
