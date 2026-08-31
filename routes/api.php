<?php

use Illuminate\Support\Facades\Route;

// ═══════════════════════════════════════════════════
// API FALLBACK
// ═══════════════════════════════════════════════════

Route::fallback(function () {
    return response()->json([
        'status' => false,
        'message' => 'API route not found.',
    ], 404);
});

// ═══════════════════════════════════════════════════
// ROLE-BASED API ROUTES (loaded from separate files)
// ═══════════════════════════════════════════════════

// Dispatcher Mobile App API Routes
require __DIR__ . '/api/dispatcher.php';

// Driver Mobile App API Routes
require __DIR__ . '/api/driver.php';
