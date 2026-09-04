<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\Dispatcher\DashboardController;
use App\Http\Controllers\Web\Dispatcher\DriverController;

// ═══════════════════════════════════════════════════
// DISPATCHER WEB ROUTES
// ═══════════════════════════════════════════════════

Route::prefix('dispatcher')->middleware(['auth', 'role:dispatcher,admin'])->group(function () {

    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dispatcher.dashboard');

    // Driver Management (Managed by Dispatcher)
    Route::get('driver/list', [DriverController::class, 'index'])->name('dispatcher.driver.list');
    Route::get('driver/create', [DriverController::class, 'create'])->name('dispatcher.driver.create');
    Route::post('driver/store', [DriverController::class, 'store'])->name('dispatcher.driver.store');
    Route::get('driver/edit/{id}', [DriverController::class, 'edit'])->name('dispatcher.driver.edit');
    Route::put('driver/update/{id}', [DriverController::class, 'update'])->name('dispatcher.driver.update');
    Route::delete('driver/delete/{id}', [DriverController::class, 'destroy'])->name('dispatcher.driver.delete');

    // Trip Management
    Route::get('trip/list', [App\Http\Controllers\Web\Dispatcher\TripController::class, 'index'])->name('dispatcher.trip.list');
    Route::get('trip/create', [App\Http\Controllers\Web\Dispatcher\TripController::class, 'create'])->name('dispatcher.trip.create');
    Route::post('trip/store', [App\Http\Controllers\Web\Dispatcher\TripController::class, 'store'])->name('dispatcher.trip.store');
    Route::get('trip/calendar', [App\Http\Controllers\Web\Dispatcher\TripController::class, 'calendar'])->name('dispatcher.trip.calendar');
    Route::get('trip/events', [App\Http\Controllers\Web\Dispatcher\TripController::class, 'events'])->name('dispatcher.trip.events');
    Route::get('trip/details/{id}', [App\Http\Controllers\Web\Dispatcher\TripController::class, 'show'])->name('dispatcher.trip.details');
    Route::get('trip/edit/{id}', [App\Http\Controllers\Web\Dispatcher\TripController::class, 'edit'])->name('dispatcher.trip.edit');
    Route::put('trip/update/{id}', [App\Http\Controllers\Web\Dispatcher\TripController::class, 'update'])->name('dispatcher.trip.update');
    Route::delete('trip/delete/{id}', [App\Http\Controllers\Web\Dispatcher\TripController::class, 'destroy'])->name('dispatcher.trip.delete');
    Route::post('trip/assign/{id}', [App\Http\Controllers\Web\Dispatcher\DashboardController::class, 'assignDriver'])->name('dispatcher.trip.assign');
    Route::view('live-map', 'content.dispatcher.coming-soon')->name('dispatcher.live-map');
    Route::get('auto-dispatch', [App\Http\Controllers\Web\Dispatcher\AutoDispatchController::class, 'index'])->name('dispatcher.auto-dispatch');
    Route::post('auto-dispatch/optimize', [App\Http\Controllers\Web\Dispatcher\AutoDispatchController::class, 'optimize'])->name('dispatcher.auto-dispatch.optimize');

    // Profile & Password Management
    Route::get('profile', [App\Http\Controllers\Web\Dispatcher\ProfileController::class, 'index'])->name('dispatcher.profile.index');
    Route::put('profile/update', [App\Http\Controllers\Web\Dispatcher\ProfileController::class, 'updateProfile'])->name('dispatcher.profile.update');
    Route::put('profile/password', [App\Http\Controllers\Web\Dispatcher\ProfileController::class, 'updatePassword'])->name('dispatcher.profile.password');
    // Fleet Management (Vehicle Management System)
    Route::get('fleet', [App\Http\Controllers\Web\Dispatcher\FleetController::class, 'index'])->name('dispatcher.fleet.index');
    Route::get('fleet/create', [App\Http\Controllers\Web\Dispatcher\FleetController::class, 'create'])->name('dispatcher.fleet.create');
    Route::post('fleet/store', [App\Http\Controllers\Web\Dispatcher\FleetController::class, 'store'])->name('dispatcher.fleet.store');
    Route::get('fleet/edit/{id}', [App\Http\Controllers\Web\Dispatcher\FleetController::class, 'edit'])->name('dispatcher.fleet.edit');
    Route::put('fleet/update/{id}', [App\Http\Controllers\Web\Dispatcher\FleetController::class, 'update'])->name('dispatcher.fleet.update');
    Route::delete('fleet/delete/{id}', [App\Http\Controllers\Web\Dispatcher\FleetController::class, 'destroy'])->name('dispatcher.fleet.delete');
    // Client Management
    Route::get('client', [App\Http\Controllers\Web\Dispatcher\ClientController::class, 'index'])->name('dispatcher.client.index');
    Route::get('client/create', [App\Http\Controllers\Web\Dispatcher\ClientController::class, 'create'])->name('dispatcher.client.create');
    Route::post('client/store', [App\Http\Controllers\Web\Dispatcher\ClientController::class, 'store'])->name('dispatcher.client.store');
    Route::get('client/edit/{id}', [App\Http\Controllers\Web\Dispatcher\ClientController::class, 'edit'])->name('dispatcher.client.edit');
    Route::put('client/update/{id}', [App\Http\Controllers\Web\Dispatcher\ClientController::class, 'update'])->name('dispatcher.client.update');
    Route::delete('client/delete/{id}', [App\Http\Controllers\Web\Dispatcher\ClientController::class, 'destroy'])->name('dispatcher.client.delete');

    Route::view('compliance', 'content.dispatcher.coming-soon')->name('dispatcher.compliance');
    Route::view('subscription', 'content.dispatcher.coming-soon')->name('dispatcher.subscription');
    Route::view('settings', 'content.dispatcher.coming-soon')->name('dispatcher.settings');
});
