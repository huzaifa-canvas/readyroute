<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Dispatcher\AuthController;

// ═══════════════════════════════════════════════════
// DISPATCHER MOBILE APP API ROUTES
// ═══════════════════════════════════════════════════

Route::prefix('dispatcher')->group(function () {

    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('/user', [AuthController::class, 'user']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});
