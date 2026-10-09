<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\Admin\CompanyController;
use App\Http\Controllers\Web\Admin\DashboardController;
use App\Http\Controllers\Web\Admin\RoleController;
use App\Http\Controllers\Web\Admin\SecurityController;
use App\Http\Controllers\Web\Admin\SubscriptionController;
use App\Http\Controllers\Web\Admin\UserController;

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

    // ── Company Management ────────────────────────
    // Archived must be declared before the {id} routes, or "archived" is
    // swallowed as a company id.
    Route::get('companies', [CompanyController::class, 'index'])->name('admin.company.list');
    Route::get('companies/archived', [CompanyController::class, 'archived'])->name('admin.company.archived');
    Route::get('companies/create', [CompanyController::class, 'create'])->name('admin.company.create');
    Route::post('companies/store', [CompanyController::class, 'store'])->name('admin.company.store');

    Route::get('companies/{id}', [CompanyController::class, 'show'])->whereNumber('id')->name('admin.company.show');
    Route::get('companies/{id}/edit', [CompanyController::class, 'edit'])->whereNumber('id')->name('admin.company.edit');
    Route::put('companies/{id}', [CompanyController::class, 'update'])->whereNumber('id')->name('admin.company.update');

    Route::post('companies/{id}/suspend', [CompanyController::class, 'suspend'])->whereNumber('id')->name('admin.company.suspend');
    // Grant, extend or withdraw a company's free access to a plan.
    Route::post('companies/{id}/free-access', [CompanyController::class, 'freeAccess'])->whereNumber('id')->name('admin.company.free-access');
    Route::post('companies/{id}/activate', [CompanyController::class, 'activate'])->whereNumber('id')->name('admin.company.activate');
    Route::post('companies/{id}/restore', [CompanyController::class, 'restore'])->whereNumber('id')->name('admin.company.restore');
    Route::delete('companies/{id}/force', [CompanyController::class, 'forceDelete'])->whereNumber('id')->name('admin.company.force-delete');
    Route::delete('companies/delete/{id}', [CompanyController::class, 'destroy'])->name('admin.company.delete');

    // ── Role Management ───────────────────────────
    Route::get('roles', [RoleController::class, 'index'])->name('admin.role.list');
    Route::get('roles/create', [RoleController::class, 'create'])->name('admin.role.create');
    Route::post('roles', [RoleController::class, 'store'])->name('admin.role.store');
    Route::get('roles/{id}/edit', [RoleController::class, 'edit'])->whereNumber('id')->name('admin.role.edit');
    Route::put('roles/{id}', [RoleController::class, 'update'])->whereNumber('id')->name('admin.role.update');
    Route::delete('roles/{id}', [RoleController::class, 'destroy'])->whereNumber('id')->name('admin.role.delete');

    // SaaS Subscription & Plans
    Route::get('subscription', [SubscriptionController::class, 'index'])->name('admin.subscription');
    Route::get('subscription/create', [SubscriptionController::class, 'create'])->name('admin.subscription.create');
    Route::post('subscription/store', [SubscriptionController::class, 'store'])->name('admin.subscription.store');
    Route::get('subscription/edit/{id}', [SubscriptionController::class, 'edit'])->name('admin.subscription.edit');
    Route::put('subscription/update/{id}', [SubscriptionController::class, 'update'])->name('admin.subscription.update');
    Route::delete('subscription/delete/{id}', [SubscriptionController::class, 'destroy'])->name('admin.subscription.delete');

    // ── Platform Security ─────────────────────────
    Route::get('security', [SecurityController::class, 'index'])->name('admin.security');
    Route::post('security/profile', [SecurityController::class, 'updateProfile'])->name('admin.security.profile');
    Route::post('security/password', [SecurityController::class, 'updatePassword'])->name('admin.security.password');
    Route::post('security/sessions', [SecurityController::class, 'signOutOtherSessions'])->name('admin.security.sessions');
});
