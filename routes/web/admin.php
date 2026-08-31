<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\Admin\DashboardController;
use App\Http\Controllers\Web\Admin\UserController;

// ═══════════════════════════════════════════════════
// ADMIN WEB ROUTES
// ═══════════════════════════════════════════════════

Route::prefix('admin')->middleware(['auth', 'role:admin'])->group(function () {

    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('admin.dashboard');

    // User Management
    Route::get('user/list', [UserController::class, 'index'])->name('admin.user.list');
    Route::get('user/create', [UserController::class, 'create'])->name('admin.user.create');
    Route::post('user/store', [UserController::class, 'store'])->name('admin.user.store');
    Route::get('user/edit/{id}', [UserController::class, 'edit'])->name('admin.user.edit');
    Route::put('user/update/{id}', [UserController::class, 'update'])->name('admin.user.update');
    Route::delete('user/delete/{id}', [UserController::class, 'destroy'])->name('admin.user.delete');
});
