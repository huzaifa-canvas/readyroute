<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Apps\UserController;

// ═══════════════════════════════════════════════════
// ADMIN ROUTES (Vuexy Design — Admin roles only)
// ═══════════════════════════════════════════════════

Route::get('/', function () {
    return redirect('/login');
});

Route::prefix('admin')->middleware(['auth'])->group(function () {
    Route::get('/', function () {
        return view('content.pages.pages-home');
    })->name('pages-home');

    // User Management
    Route::get('user/list', [UserController::class, 'index'])->name('app-user-list');
    Route::get('user/create', [UserController::class, 'create'])->name('app-user-create');
    Route::post('user/store', [UserController::class, 'store'])->name('app-user-store');
    Route::get('user/edit/{id}', [UserController::class, 'edit'])->name('app-user-edit');
    Route::put('user/update/{id}', [UserController::class, 'update'])->name('app-user-update');
    Route::delete('user/delete/{id}', [UserController::class, 'destroy'])->name('app-user-delete');
});
