<?php

use Illuminate\Support\Facades\Route;

// ═══════════════════════════════════════════════════
// API FALLBACK
// ═══════════════════════════════════════════════════

Route::fallback(function () {
    return response()->json([
        'status'  => false,
        'message' => 'API route not found.',
        'data'    => null,
        'errors'  => null,
    ], 404);
});

// ═══════════════════════════════════════════════════
// ROLE-BASED API ROUTES (loaded from separate files)
// ═══════════════════════════════════════════════════

// Incoming provider webhooks (Stripe)
require __DIR__ . '/api/webhooks.php';

// Internal server-to-server routes (socket.io process)
require __DIR__ . '/api/internal.php';

// Dispatcher Mobile App API Routes
require __DIR__ . '/api/dispatcher.php';

// Driver Mobile App API Routes
require __DIR__ . '/api/driver.php';
