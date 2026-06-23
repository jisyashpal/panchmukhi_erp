<?php

use App\Http\Controllers\Ho\RequestPo\PurchaseController as HoPurchaseController;
use App\Http\Controllers\Site\RequestPo\PurchaseController as SitePurchaseController;
use Illuminate\Support\Facades\Route;

Route::get('site/purchases/{purchase}/pdf/shared', [SitePurchaseController::class, 'sharedPdf'])
    ->middleware('signed')
    ->name('site.purchases.shared-pdf');

Route::get('ho/purchases/{purchase}/pdf/shared', [HoPurchaseController::class, 'sharedPdf'])
    ->middleware('signed')
    ->name('ho.purchases.shared-pdf');
