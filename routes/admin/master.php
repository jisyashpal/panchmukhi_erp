<?php

use App\Http\Controllers\Admin\Master\MachineController;
use App\Http\Controllers\Admin\Master\SiteController;
use App\Http\Controllers\Admin\Master\SupplierController;
use App\Http\Controllers\Admin\Master\UserController;
use App\Support\PermissionSlug;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:'.PermissionSlug::ADMIN_MANAGE_SITES)
    ->resource('sites', SiteController::class)->except(['show']);

Route::middleware('permission:'.PermissionSlug::ADMIN_MANAGE_MACHINES)
    ->resource('machines', MachineController::class)->except(['show']);

Route::middleware('permission:'.PermissionSlug::ADMIN_MANAGE_SUPPLIERS)
    ->resource('suppliers', SupplierController::class)->except(['show']);

Route::middleware('permission:'.PermissionSlug::ADMIN_MANAGE_USERS)
    ->resource('users', UserController::class)->except(['show']);
