<?php

use App\Http\Controllers\Api\V1\Auth\TokenController;
use App\Http\Controllers\Api\V1\HealthController;
use Illuminate\Support\Facades\Route;

// Everything lives under /api/v1 (see docs/design/01-domain-design.md §6).
// This file grows context-by-context in later stages; Stage 2 wires only
// the skeleton: health/readiness and Sanctum token issuance.
Route::prefix('v1')->group(function (): void {
    Route::get('/health', [HealthController::class, 'health']);
    Route::get('/ready', [HealthController::class, 'ready']);

    Route::post('/auth/token', [TokenController::class, 'store']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/auth/tokens', [TokenController::class, 'index']);
        Route::delete('/auth/tokens/{tokenId}', [TokenController::class, 'destroy'])
            ->whereNumber('tokenId');
        Route::post('/auth/logout', [TokenController::class, 'destroyCurrent']);
    });

    // Exists only so the exception-handling test suite can assert the 500
    // path renders JSON like everything else. Never reachable outside tests.
    if (app()->runningUnitTests()) {
        Route::get('/_test/boom', function (): never {
            throw new RuntimeException('Boom — forced failure for exception-handling tests.');
        });
    }
});
