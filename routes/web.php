<?php

use Illuminate\Support\Facades\Route;

// ═══════════════════════════════════════════════════
// SHARED / AUTH ROUTES
// ═══════════════════════════════════════════════════

// A signed-in user goes to their own panel. Sending everyone to /login made
// an endless loop, since /login sends a signed-in user back here.
Route::get('/', function () {
    $user = auth()->user();

    if ($user && ($home = $user->panelHome())) {
        return redirect($home);
    }

    // A driver has no web panel, so a stray web session is ended rather than
    // bounced between here and the login page.
    if ($user) {
        auth()->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
    }

    return redirect('/login');
});

// A fresh CSRF token for a form that has been open a long time, so a login page
// left overnight still submits instead of failing with 419.
Route::get('csrf-token', function () {
    return response()->json(['token' => csrf_token()])
        ->header('Cache-Control', 'no-store');
})->middleware('throttle:30,1')->name('csrf.token');

// Vuexy's template customizer sends the Direction switch and its reset button
// here. LocaleMiddleware applies the stored locale; the direction itself comes
// from the customizer's cookie, so this only has to remember the language.
Route::get('lang/{locale}', function (string $locale) {
    if (in_array($locale, ['en', 'fr', 'ar', 'de'], true)) {
        session()->put('locale', $locale);
    }

    return redirect()->back();
})->name('lang.switch');

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
