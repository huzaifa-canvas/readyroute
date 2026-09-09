<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\Admin\DashboardController;
use App\Http\Controllers\Web\Admin\UserController;
use App\Http\Controllers\Web\Admin\CompanyController;

// ═══════════════════════════════════════════════════
// ADMIN WEB ROUTES
// ═══════════════════════════════════════════════════

Route::prefix('admin')->middleware(['auth', 'role:admin'])->group(function () {

    // System Overview (Dashboard)
    Route::get('/', [DashboardController::class, 'index'])->name('admin.dashboard');

    // User Management (kept for backward compatibility, removed from menu)
    Route::get('user/list', [UserController::class, 'index'])->name('admin.user.list');
    Route::get('user/create', [UserController::class, 'create'])->name('admin.user.create');
    Route::post('user/store', [UserController::class, 'store'])->name('admin.user.store');
    Route::get('user/edit/{id}', [UserController::class, 'edit'])->name('admin.user.edit');
    Route::put('user/update/{id}', [UserController::class, 'update'])->name('admin.user.update');
    Route::delete('user/delete/{id}', [UserController::class, 'destroy'])->name('admin.user.delete');

    // Company Management (Dispatchers as Companies)
    Route::get('companies', [CompanyController::class, 'index'])->name('admin.company.list');
    Route::get('companies/create', [CompanyController::class, 'create'])->name('admin.company.create');
    Route::post('companies/store', [CompanyController::class, 'store'])->name('admin.company.store');
    Route::delete('companies/delete/{id}', [CompanyController::class, 'destroy'])->name('admin.company.delete');

    // SaaS Subscription & Plans
    Route::get('subscription', [\App\Http\Controllers\Web\Admin\SubscriptionController::class, 'index'])->name('admin.subscription');
    Route::get('subscription/create', [\App\Http\Controllers\Web\Admin\SubscriptionController::class, 'create'])->name('admin.subscription.create');
    Route::post('subscription/store', [\App\Http\Controllers\Web\Admin\SubscriptionController::class, 'store'])->name('admin.subscription.store');
    Route::get('subscription/edit/{id}', [\App\Http\Controllers\Web\Admin\SubscriptionController::class, 'edit'])->name('admin.subscription.edit');
    Route::put('subscription/update/{id}', [\App\Http\Controllers\Web\Admin\SubscriptionController::class, 'update'])->name('admin.subscription.update');
    Route::delete('subscription/delete/{id}', [\App\Http\Controllers\Web\Admin\SubscriptionController::class, 'destroy'])->name('admin.subscription.delete');

    // Platform Security (My Profile & Security)
    Route::get('security', [\App\Http\Controllers\Web\Dispatcher\ProfileController::class, 'index'])->name('admin.security');
});
