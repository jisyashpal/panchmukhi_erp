<?php

use App\Http\Controllers\Ho\RequestPo\PurchaseController as HoPurchaseController;
use App\Http\Controllers\Plant\RequestPo\PurchaseController as PlantPurchaseController;
use Illuminate\Support\Facades\Route;

Route::get('plant/purchases/{purchase}/pdf/shared', [PlantPurchaseController::class, 'sharedPdf'])
    ->middleware('signed')
    ->name('plant.purchases.shared-pdf');

Route::get('ho/purchases/{purchase}/pdf/shared', [HoPurchaseController::class, 'sharedPdf'])
    ->middleware('signed')
    ->name('ho.purchases.shared-pdf');
