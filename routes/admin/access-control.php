<?php

use App\Http\Controllers\Admin\Master\AccessControl\PermissionController;
use App\Http\Controllers\Admin\Master\AccessControl\RoleController;
use App\Support\PermissionSlug;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:'.PermissionSlug::ADMIN_MANAGE_ROLES)
    ->resource('roles', RoleController::class)
    ->except(['show']);

Route::middleware('permission:'.PermissionSlug::ADMIN_MANAGE_PERMISSIONS)
    ->resource('permissions', PermissionController::class)
    ->except(['show']);
