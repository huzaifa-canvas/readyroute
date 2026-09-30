<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\StripeWebhookController;

// ═══════════════════════════════════════════════════
// INCOMING WEBHOOKS
// ═══════════════════════════════════════════════════
// Server-to-server, so no auth and no session. What proves a request is
// genuine is the provider's signature, checked inside the controller.
//
// These live under the API routes rather than the web ones because they are
// not browser traffic: no CSRF token, no cookies, nothing to redirect.

Route::prefix('stripe')->group(function () {

    /*
     * Stripe posts every subscription event here.
     *
     * Register the endpoint and see which events it needs with:
     *   php artisan stripe:webhook https://your-domain.com
     *
     * Throttled generously — Stripe retries a failed delivery, and a busy
     * month of renewals should never be rate limited into failing.
     */
    Route::post('webhook', [StripeWebhookController::class, 'handle'])
        ->middleware('throttle:300,1')
        ->name('stripe.webhook');
});
