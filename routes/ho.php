<?php

use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Ho\FinancialYearController;
use App\Http\Controllers\Ho\Operation\DashboardController;
use App\Http\Controllers\Ho\Notifications\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Ho\Product\BrandController;
use App\Http\Controllers\Ho\Product\CategoryController;
use App\Http\Controllers\Ho\Product\ProductController;
use App\Http\Controllers\Ho\Product\SubCategoryController;
use App\Http\Controllers\Ho\Product\UnitController;
use App\Http\Controllers\Ho\Product\VehicleController;
use App\Http\Controllers\Ho\RequestPo\ProductRequestController;
use App\Http\Controllers\Ho\RequestPo\PurchaseController;
use App\Http\Controllers\Ho\RequestPo\PurchaseListController;
use App\Http\Controllers\Ho\Inventory\ProductLifeController;
use App\Http\Controllers\Ho\Inventory\StockController;
use App\Http\Controllers\Ho\Inventory\StockInController;
use App\Http\Controllers\Ho\Inventory\StockOutController;
use App\Http\Controllers\Ho\Reports\ReportController;
use App\Support\PermissionSlug;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'permission:'.PermissionSlug::HO_ACCESS, 'share_portal_layout'])
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

        // Products Routes
        Route::middleware('permission:'.PermissionSlug::HO_MANAGE_PRODUCTS)->group(function () {
            Route::resource('categories', CategoryController::class)->except(['show']);
            Route::resource('sub-categories', SubCategoryController::class)->except(['show']);
            Route::resource('brands', BrandController::class)->except(['show']);
            Route::resource('units', UnitController::class)->except(['show']);
            Route::resource('vehicles', VehicleController::class)->except(['show']);
            Route::get('products/import-template', [ProductController::class, 'downloadTemplate'])->name('products.import-template');
            Route::get('products/import', [ProductController::class, 'importForm'])->name('products.import-form');
            Route::post('products/import', [ProductController::class, 'import'])->name('products.import');
            Route::resource('products', ProductController::class)->except(['show']);
        });

        Route::middleware('share_financial_year')->group(function () {
            Route::redirect('/', '/ho/dashboard');
            Route::get('/dashboard', DashboardController::class)->name('dashboard');
            Route::post('financial-year', FinancialYearController::class)->name('financial-year.set');

            // Request / PO Routes
            Route::middleware('permission:'.PermissionSlug::HO_MANAGE_PRODUCT_REQUESTS)->group(function () {
                Route::get('product-requests', [ProductRequestController::class, 'index'])->name('product-requests.index');
                Route::get('product-requests/create', [ProductRequestController::class, 'create'])->name('product-requests.create');
                Route::post('product-requests', [ProductRequestController::class, 'store'])->name('product-requests.store');
                Route::get('product-requests/{product_request}', [ProductRequestController::class, 'show'])->name('product-requests.show');
                Route::post('product-requests/{product_request}/status', [ProductRequestController::class, 'updateStatus'])->name('product-requests.status');
            });

            Route::middleware('permission:'.PermissionSlug::HO_VIEW_PURCHASES)->group(function () {
                Route::get('purchase-list', [PurchaseListController::class, 'index'])->name('purchase-list.index');
                Route::post('purchase-list/{supplier}/payments', [PurchaseListController::class, 'storePayment'])->name('purchase-list.payments.store');
                Route::get('purchases', [PurchaseController::class, 'index'])->name('purchases.index');
                Route::get('purchases/{purchase}', [PurchaseController::class, 'show'])->name('purchases.show');
                Route::get('purchases/{purchase}/pdf', [PurchaseController::class, 'pdf'])->name('purchases.pdf');
                Route::get('purchases/{purchase}/excel', [PurchaseController::class, 'excel'])->name('purchases.excel');
                Route::get('purchases/{purchase}/word', [PurchaseController::class, 'word'])->name('purchases.word');
                Route::get('purchases/{purchase}/share-links', [PurchaseController::class, 'shareLinks'])->name('purchases.share-links');
                Route::post('purchases/{purchase}/email', [PurchaseController::class, 'email'])->name('purchases.email');
                Route::post('purchases/{purchase}/comments', [PurchaseController::class, 'storeComment'])->name('purchases.comments.store');
            });

            // Inventory Routes
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

            // Reports Routes
            Route::middleware('permission:'.PermissionSlug::HO_VIEW_REPORTS)->group(function () {
                Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
                Route::get('reports/product-life', [ReportController::class, 'productLife'])->name('reports.product-life');
                Route::get('reports/category-purchase', [ReportController::class, 'categoryPurchase'])->name('reports.category-purchase');
                Route::get('reports/multiple-usage', [ReportController::class, 'multipleUsage'])->name('reports.multiple-usage');
                Route::get('reports/usage-by-category', [ReportController::class, 'usageByCategory'])->name('reports.usage-by-category');
                Route::get('reports/party-purchase', [ReportController::class, 'partyPurchase'])->name('reports.party-purchase');
                Route::get('reports/party-ledger/{supplier}', [ReportController::class, 'partyLedger'])->name('reports.party-ledger');
                Route::get('reports/category-stock-in-out', [ReportController::class, 'categoryStockInOut'])->name('reports.category-stock-in-out');
                Route::get('reports/category-purchase/{category}/ledger', [ReportController::class, 'categoryPurchaseLedger'])->name('reports.category-purchase.ledger');
            });
        });
    });
