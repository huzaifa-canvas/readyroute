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

// Admin Web Routes
require __DIR__ . '/web/admin.php';

// Dispatcher Web Routes
require __DIR__ . '/web/dispatcher.php';
