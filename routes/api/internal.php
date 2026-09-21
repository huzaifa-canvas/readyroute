<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Internal\SocketController;

// ═══════════════════════════════════════════════════
// INTERNAL ROUTES — SERVER TO SERVER ONLY
// ═══════════════════════════════════════════════════
//
// Called by the socket.io process, never by a client. Guarded by a shared
// secret rather than a user session, and intended to stay on localhost.

Route::prefix('internal')->middleware('socket.secret')->group(function () {

    Route::post('/socket/verify', [SocketController::class, 'verify']);
    Route::post('/socket/presence', [SocketController::class, 'presence']);
});
