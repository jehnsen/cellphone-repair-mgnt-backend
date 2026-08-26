<?php

use App\Http\Controllers\Api\V1\Auth\TokenController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\Catalog\DeviceBrandController;
use App\Http\Controllers\Api\V1\Catalog\DeviceModelController;
use App\Http\Controllers\Api\V1\Catalog\ProductCategoryController;
use App\Http\Controllers\Api\V1\Catalog\ProductController;
use App\Http\Controllers\Api\V1\Catalog\ServiceController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\CustomerDeviceController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

// Everything lives under /api/v1 (see docs/design/01-domain-design.md §6).
// This file grows context-by-context; Stage 2 wired the skeleton
// (health/readiness, Sanctum token issuance). Stage 4 adds master data.
Route::prefix('v1')->group(function (): void {
    Route::get('/health', [HealthController::class, 'health']);
    Route::get('/ready', [HealthController::class, 'ready']);

    Route::post('/auth/token', [TokenController::class, 'store']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/auth/tokens', [TokenController::class, 'index']);
        Route::delete('/auth/tokens/{tokenId}', [TokenController::class, 'destroy'])
            ->whereNumber('tokenId');
        Route::post('/auth/logout', [TokenController::class, 'destroyCurrent']);

        // Identity & shop — no destroy: branches deactivate via update(),
        // they don't get removed (see docs/design/01-domain-design.md Flag 1).
        Route::apiResource('branches', BranchController::class)->except(['destroy']);
        Route::apiResource('users', UserController::class);

        // Catalog
        Route::apiResource('device-brands', DeviceBrandController::class);
        Route::apiResource('device-models', DeviceModelController::class);
        Route::apiResource('services', ServiceController::class);
        Route::apiResource('product-categories', ProductCategoryController::class);
        Route::apiResource('products', ProductController::class);

        // Customers & devices
        Route::apiResource('customers', CustomerController::class);
        Route::apiResource('customers.devices', CustomerDeviceController::class)
            ->parameters(['devices' => 'device']);
        Route::get('/devices/by-imei/{imei}', [CustomerDeviceController::class, 'historyByImei']);
    });

    // Exists only so the exception-handling test suite can assert the 500
    // path renders JSON like everything else. Never reachable outside tests.
    if (app()->runningUnitTests()) {
        Route::get('/_test/boom', function (): never {
            throw new RuntimeException('Boom — forced failure for exception-handling tests.');
        });
    }
});
