<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Driver\AuthController;
use App\Http\Controllers\Api\Driver\DashboardController;
use App\Http\Controllers\Api\Driver\ProfileController;
use App\Http\Controllers\Api\Driver\SettingsController;
use App\Http\Controllers\Api\Driver\TripController;

// ═══════════════════════════════════════════════════
// DRIVER MOBILE APP API ROUTES
// ═══════════════════════════════════════════════════

Route::prefix('driver')->group(function () {

    // ── Public ────────────────────────────────────
    // Login is rate limited because it is the one endpoint that can be probed
    // without credentials.
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    });

    // ── Authenticated ─────────────────────────────
    Route::middleware(['auth:sanctum', 'role:driver'])->group(function () {

        Route::post('/logout', [AuthController::class, 'logout']);

        // Profile
        Route::get('/me', [ProfileController::class, 'show']);
        Route::get('/user', [ProfileController::class, 'show']); // legacy alias
        Route::post('/device-token', [ProfileController::class, 'storeDeviceToken']);

        // Settings & support
        Route::get('/settings', [SettingsController::class, 'show']);
        Route::put('/settings', [SettingsController::class, 'update']);
        Route::get('/support', [SettingsController::class, 'support']);

        // Home screen
        Route::get('/dashboard', [DashboardController::class, 'index']);

        // Trips
        Route::get('/trips', [TripController::class, 'index']);
        Route::get('/trips/{id}', [TripController::class, 'show'])->whereNumber('id');
    });
});
