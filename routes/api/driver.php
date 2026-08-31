<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Driver\AuthController;

// ═══════════════════════════════════════════════════
// DRIVER MOBILE APP API ROUTES
// ═══════════════════════════════════════════════════

Route::prefix('driver')->group(function () {

    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('/user', [AuthController::class, 'user']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});
