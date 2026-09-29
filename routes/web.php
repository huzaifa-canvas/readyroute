<?php

use Illuminate\Support\Facades\Route;

// ═══════════════════════════════════════════════════
// SHARED / AUTH ROUTES
// ═══════════════════════════════════════════════════

Route::get('/', function () {
    return redirect('/login');
});

// ═══════════════════════════════════════════════════
// ROLE-BASED WEB ROUTES (loaded from separate files)
// ═══════════════════════════════════════════════════

// ═══════════════════════════════════════════════════
// PUBLIC PASSENGER TRACKING
// ═══════════════════════════════════════════════════
// No auth: the token in the URL is the only credential, which is why it is
// long, random and per-trip. Throttled so the link cannot be brute-forced.
Route::middleware('throttle:60,1')->group(function () {
    Route::get('track/{token}', [App\Http\Controllers\Web\TrackingController::class, 'show'])->name('track.show');
    Route::get('track/{token}/position', [App\Http\Controllers\Web\TrackingController::class, 'position'])->name('track.position');
});

// ═══════════════════════════════════════════════════
// NAVBAR SEARCH PALETTE
// ═══════════════════════════════════════════════════
// Vuexy's main.js fetches this path as if it were a static file; serving it
// from a route is what lets the page list follow the signed-in user's role.
Route::get('assets/json/search-vertical.json', [App\Http\Controllers\Web\SearchPaletteController::class, 'vertical'])
    ->middleware('auth')
    ->name('search.palette');

// Admin Web Routes
require __DIR__ . '/web/admin.php';

// Dispatcher Web Routes
require __DIR__ . '/web/dispatcher.php';
