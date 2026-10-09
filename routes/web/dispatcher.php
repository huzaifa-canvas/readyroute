<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\Dispatcher\DashboardController;
use App\Http\Controllers\Web\Dispatcher\DriverController;

// ═══════════════════════════════════════════════════
// DISPATCHER WEB ROUTES
// ═══════════════════════════════════════════════════

Route::prefix('dispatcher')->middleware(['auth', 'role:dispatcher,admin', 'subscribed'])->group(function () {

    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dispatcher.dashboard');

    // Driver Management (Managed by Dispatcher)
    Route::get('driver/list', [DriverController::class, 'index'])->middleware('plan:drivers')->middleware('permission:drivers.view')->name('dispatcher.driver.list');
    Route::get('driver/create', [DriverController::class, 'create'])->middleware('plan:drivers')->middleware('permission:drivers.manage')->name('dispatcher.driver.create');
    Route::get('driver/{id}', [DriverController::class, 'show'])->whereNumber('id')->middleware('plan:drivers')->middleware('permission:drivers.view')->name('dispatcher.driver.show');
    Route::post('driver/store', [DriverController::class, 'store'])->middleware('plan:drivers')->middleware('permission:drivers.manage')->name('dispatcher.driver.store');
    Route::get('driver/edit/{id}', [DriverController::class, 'edit'])->middleware('plan:drivers')->middleware('permission:drivers.manage')->name('dispatcher.driver.edit');
    Route::put('driver/update/{id}', [DriverController::class, 'update'])->middleware('plan:drivers')->middleware('permission:drivers.manage')->name('dispatcher.driver.update');
    Route::delete('driver/delete/{id}', [DriverController::class, 'destroy'])->middleware('plan:drivers')->middleware('permission:drivers.manage')->name('dispatcher.driver.delete');

    // Trip Management
    Route::get('trip/list', [App\Http\Controllers\Web\Dispatcher\TripController::class, 'index'])->middleware('plan:trips')->middleware('permission:trips.view')->name('dispatcher.trip.list');
    Route::get('trip/create', [App\Http\Controllers\Web\Dispatcher\TripController::class, 'create'])->middleware('plan:trips')->middleware('permission:trips.create')->name('dispatcher.trip.create');
    Route::post('trip/store', [App\Http\Controllers\Web\Dispatcher\TripController::class, 'store'])->middleware('plan:trips')->middleware('permission:trips.create')->name('dispatcher.trip.store');
    Route::get('trip/driver-load', [App\Http\Controllers\Web\Dispatcher\TripController::class, 'driverLoad'])->middleware('plan:trips')->middleware('permission:trips.view')->name('dispatcher.trip.driver-load');
    Route::get('trip/calendar', [App\Http\Controllers\Web\Dispatcher\TripController::class, 'calendar'])->middleware('plan:trips')->middleware('permission:trips.view')->name('dispatcher.trip.calendar');
    Route::get('trip/events', [App\Http\Controllers\Web\Dispatcher\TripController::class, 'events'])->middleware('plan:trips')->middleware('permission:trips.view')->name('dispatcher.trip.events');
    Route::get('trip/details/{id}', [App\Http\Controllers\Web\Dispatcher\TripController::class, 'show'])->middleware('plan:trips')->middleware('permission:trips.view')->name('dispatcher.trip.details');
    Route::get('trip/edit/{id}', [App\Http\Controllers\Web\Dispatcher\TripController::class, 'edit'])->middleware('plan:trips')->middleware('permission:trips.edit')->name('dispatcher.trip.edit');
    Route::put('trip/update/{id}', [App\Http\Controllers\Web\Dispatcher\TripController::class, 'update'])->middleware('plan:trips')->middleware('permission:trips.edit')->name('dispatcher.trip.update');
    // Two different things, behind two different permissions. Cancelling marks
    // a booked run as not happening and keeps it on the board; deleting takes
    // the trip off the panel altogether.
    Route::patch('trip/cancel/{id}', [App\Http\Controllers\Web\Dispatcher\TripController::class, 'cancel'])->middleware('plan:trips')->middleware('permission:trips.cancel')->name('dispatcher.trip.cancel');
    Route::delete('trip/delete/{id}', [App\Http\Controllers\Web\Dispatcher\TripController::class, 'destroy'])->middleware('plan:trips')->middleware('permission:trips.delete')->name('dispatcher.trip.delete');
    Route::post('trip/{id}/tracking-link', [App\Http\Controllers\Web\Dispatcher\TripController::class, 'trackingLink'])->whereNumber('id')->middleware('plan:trips')->middleware('permission:trips.edit')->name('dispatcher.trip.tracking-link');
    Route::post('trip/assign/{id}', [App\Http\Controllers\Web\Dispatcher\DashboardController::class, 'assignDriver'])->middleware('plan:trips')->middleware('permission:trips.assign')->name('dispatcher.trip.assign');
    Route::get('live-map', [App\Http\Controllers\Web\Dispatcher\DashboardController::class, 'liveMap'])->middleware('plan:live_map')->middleware('permission:live_map.view')->name('dispatcher.live-map');
    Route::get('auto-dispatch', [App\Http\Controllers\Web\Dispatcher\AutoDispatchController::class, 'index'])->middleware('plan:auto_dispatch')->middleware('permission:auto_dispatch.run')->name('dispatcher.auto-dispatch');
    Route::post('auto-dispatch/optimize', [App\Http\Controllers\Web\Dispatcher\AutoDispatchController::class, 'optimize'])->middleware('plan:auto_dispatch')->middleware('permission:auto_dispatch.run')->name('dispatcher.auto-dispatch.optimize');

    // Profile & Password Management
    Route::get('profile', [App\Http\Controllers\Web\Dispatcher\ProfileController::class, 'index'])->name('dispatcher.profile.index');
    Route::put('profile/update', [App\Http\Controllers\Web\Dispatcher\ProfileController::class, 'updateProfile'])->name('dispatcher.profile.update');
    Route::put('profile/password', [App\Http\Controllers\Web\Dispatcher\ProfileController::class, 'updatePassword'])->name('dispatcher.profile.password');
    Route::post('profile/2fa', [App\Http\Controllers\Web\Dispatcher\ProfileController::class, 'toggle2FA'])->name('dispatcher.profile.2fa');
    // Fleet Management (Vehicle Management System)
    Route::get('fleet', [App\Http\Controllers\Web\Dispatcher\FleetController::class, 'index'])->middleware('plan:fleet')->middleware('permission:fleet.view')->name('dispatcher.fleet.index');
    Route::get('fleet/create', [App\Http\Controllers\Web\Dispatcher\FleetController::class, 'create'])->middleware('plan:fleet')->middleware('permission:fleet.manage')->name('dispatcher.fleet.create');
    Route::post('fleet/store', [App\Http\Controllers\Web\Dispatcher\FleetController::class, 'store'])->middleware('plan:fleet')->middleware('permission:fleet.manage')->name('dispatcher.fleet.store');
    Route::get('fleet/edit/{id}', [App\Http\Controllers\Web\Dispatcher\FleetController::class, 'edit'])->middleware('plan:fleet')->middleware('permission:fleet.manage')->name('dispatcher.fleet.edit');
    Route::put('fleet/update/{id}', [App\Http\Controllers\Web\Dispatcher\FleetController::class, 'update'])->middleware('plan:fleet')->middleware('permission:fleet.manage')->name('dispatcher.fleet.update');
    Route::delete('fleet/delete/{id}', [App\Http\Controllers\Web\Dispatcher\FleetController::class, 'destroy'])->middleware('plan:fleet')->middleware('permission:fleet.manage')->name('dispatcher.fleet.delete');
    // Client Management
    Route::get('client', [App\Http\Controllers\Web\Dispatcher\ClientController::class, 'index'])->middleware('plan:clients')->middleware('permission:clients.view')->name('dispatcher.client.index');
    Route::get('client/create', [App\Http\Controllers\Web\Dispatcher\ClientController::class, 'create'])->middleware('plan:clients')->middleware('permission:clients.manage')->name('dispatcher.client.create');
    Route::post('client/store', [App\Http\Controllers\Web\Dispatcher\ClientController::class, 'store'])->middleware('plan:clients')->middleware('permission:clients.manage')->name('dispatcher.client.store');
    Route::get('client/edit/{id}', [App\Http\Controllers\Web\Dispatcher\ClientController::class, 'edit'])->middleware('plan:clients')->middleware('permission:clients.manage')->name('dispatcher.client.edit');
    Route::put('client/update/{id}', [App\Http\Controllers\Web\Dispatcher\ClientController::class, 'update'])->middleware('plan:clients')->middleware('permission:clients.manage')->name('dispatcher.client.update');
    Route::delete('client/delete/{id}', [App\Http\Controllers\Web\Dispatcher\ClientController::class, 'destroy'])->middleware('plan:clients')->middleware('permission:clients.manage')->name('dispatcher.client.delete');

    // Client profile + the dated note thread drivers read on the trip screen
    Route::get('client/{id}', [App\Http\Controllers\Web\Dispatcher\ClientController::class, 'show'])->whereNumber('id')->middleware('plan:clients')->middleware('permission:clients.view')->name('dispatcher.client.show');
    Route::post('client/{id}/notes', [App\Http\Controllers\Web\Dispatcher\ClientController::class, 'storeNote'])->whereNumber('id')->middleware('plan:clients')->middleware('permission:clients.notes')->name('dispatcher.client.note.store');
    Route::put('client/{id}/notes/{noteId}', [App\Http\Controllers\Web\Dispatcher\ClientController::class, 'updateNote'])->whereNumber('id')->whereNumber('noteId')->middleware('plan:clients')->middleware('permission:clients.notes')->name('dispatcher.client.note.update');
    Route::delete('client/{id}/notes/{noteId}', [App\Http\Controllers\Web\Dispatcher\ClientController::class, 'destroyNote'])->whereNumber('id')->whereNumber('noteId')->middleware('plan:clients')->middleware('permission:clients.notes')->name('dispatcher.client.note.delete');

    // ── Compliance Center ─────────────────────────
    Route::get('compliance', [App\Http\Controllers\Web\Dispatcher\ComplianceController::class, 'index'])->middleware('plan:compliance')->middleware('permission:drivers.view')->name('dispatcher.compliance');
    Route::get('compliance/driver/{driverId}', [App\Http\Controllers\Web\Dispatcher\ComplianceController::class, 'driver'])->whereNumber('driverId')->middleware('plan:compliance')->middleware('permission:drivers.compliance')->name('dispatcher.compliance.driver');
    Route::post('compliance/driver/{driverId}', [App\Http\Controllers\Web\Dispatcher\ComplianceController::class, 'store'])->whereNumber('driverId')->middleware('plan:compliance')->middleware('permission:drivers.compliance')->name('dispatcher.compliance.store');
    Route::put('compliance/driver/{driverId}/{documentId}', [App\Http\Controllers\Web\Dispatcher\ComplianceController::class, 'update'])->whereNumber('driverId')->whereNumber('documentId')->middleware('plan:compliance')->middleware('permission:drivers.compliance')->name('dispatcher.compliance.update');
    Route::delete('compliance/driver/{driverId}/{documentId}', [App\Http\Controllers\Web\Dispatcher\ComplianceController::class, 'destroy'])->whereNumber('driverId')->whereNumber('documentId')->middleware('plan:compliance')->middleware('permission:drivers.compliance')->name('dispatcher.compliance.delete');
    Route::get('compliance/driver/{driverId}/{documentId}/download', [App\Http\Controllers\Web\Dispatcher\ComplianceController::class, 'download'])->whereNumber('driverId')->whereNumber('documentId')->middleware('plan:compliance')->middleware('permission:drivers.compliance')->name('dispatcher.compliance.download');
    // ── Subscription ──────────────────────────────
    Route::get('subscription', [App\Http\Controllers\Web\Dispatcher\SubscriptionController::class, 'index'])->name('dispatcher.subscription');
    Route::post('subscription/checkout', [App\Http\Controllers\Web\Dispatcher\SubscriptionController::class, 'checkout'])->middleware('permission:subscription.manage')->name('dispatcher.subscription.checkout');
    Route::post('subscription/confirm', [App\Http\Controllers\Web\Dispatcher\SubscriptionController::class, 'confirm'])->middleware('permission:subscription.manage')->name('dispatcher.subscription.confirm');
    Route::post('subscription/cancel', [App\Http\Controllers\Web\Dispatcher\SubscriptionController::class, 'cancel'])->middleware('permission:subscription.manage')->name('dispatcher.subscription.cancel');
    Route::post('subscription/resume', [App\Http\Controllers\Web\Dispatcher\SubscriptionController::class, 'resume'])->middleware('permission:subscription.manage')->name('dispatcher.subscription.resume');

    // ── Billing & Claims ──────────────────────────
    Route::get('billing', [App\Http\Controllers\Web\Dispatcher\BillingController::class, 'index'])->middleware('plan:billing')->middleware('permission:billing.view')->name('dispatcher.billing.index');
    Route::get('billing/unbilled', [App\Http\Controllers\Web\Dispatcher\BillingController::class, 'unbilled'])->middleware('plan:billing')->middleware('permission:billing.view')->name('dispatcher.billing.unbilled');
    Route::get('billing/create', [App\Http\Controllers\Web\Dispatcher\BillingController::class, 'create'])->middleware('plan:billing')->middleware('permission:billing.manage')->name('dispatcher.billing.create');
    Route::post('billing', [App\Http\Controllers\Web\Dispatcher\BillingController::class, 'store'])->middleware('plan:billing')->middleware('permission:billing.manage')->name('dispatcher.billing.store');
    Route::get('billing/{id}', [App\Http\Controllers\Web\Dispatcher\BillingController::class, 'show'])->whereNumber('id')->middleware('plan:billing')->middleware('permission:billing.view')->name('dispatcher.billing.show');
    Route::get('billing/{id}/print', [App\Http\Controllers\Web\Dispatcher\BillingController::class, 'print'])->whereNumber('id')->middleware('plan:billing')->middleware('permission:billing.view')->name('dispatcher.billing.print');
    Route::put('billing/{id}', [App\Http\Controllers\Web\Dispatcher\BillingController::class, 'update'])->whereNumber('id')->middleware('plan:billing')->middleware('permission:billing.manage')->name('dispatcher.billing.update');
    Route::post('billing/{id}/status', [App\Http\Controllers\Web\Dispatcher\BillingController::class, 'updateStatus'])->whereNumber('id')->middleware('plan:billing')->middleware('permission:billing.manage')->name('dispatcher.billing.status');
    Route::delete('billing/{id}', [App\Http\Controllers\Web\Dispatcher\BillingController::class, 'destroy'])->whereNumber('id')->middleware('plan:billing')->middleware('permission:billing.manage')->name('dispatcher.billing.delete');
    // ── System Users & Permissions ────────────────
    Route::get('users', [App\Http\Controllers\Web\Dispatcher\UserController::class, 'index'])->middleware('plan:system_users')->middleware('permission:users.manage')->name('dispatcher.users.index');
    Route::post('users', [App\Http\Controllers\Web\Dispatcher\UserController::class, 'store'])->middleware('plan:system_users')->middleware('permission:users.manage')->name('dispatcher.users.store');
    Route::put('users/{id}', [App\Http\Controllers\Web\Dispatcher\UserController::class, 'update'])->whereNumber('id')->middleware('plan:system_users')->middleware('permission:users.manage')->name('dispatcher.users.update');
    Route::post('users/{id}/suspend', [App\Http\Controllers\Web\Dispatcher\UserController::class, 'suspend'])->whereNumber('id')->middleware('plan:system_users')->middleware('permission:users.manage')->name('dispatcher.users.suspend');
    Route::post('users/{id}/activate', [App\Http\Controllers\Web\Dispatcher\UserController::class, 'activate'])->whereNumber('id')->middleware('plan:system_users')->middleware('permission:users.manage')->name('dispatcher.users.activate');
    Route::delete('users/{id}', [App\Http\Controllers\Web\Dispatcher\UserController::class, 'destroy'])->whereNumber('id')->middleware('plan:system_users')->middleware('permission:users.manage')->name('dispatcher.users.delete');

    Route::get('roles/create', [App\Http\Controllers\Web\Dispatcher\UserController::class, 'createRole'])->middleware('plan:custom_roles')->middleware('permission:users.manage')->name('dispatcher.users.role.create');
    Route::post('roles', [App\Http\Controllers\Web\Dispatcher\UserController::class, 'storeRole'])->middleware('plan:custom_roles')->middleware('permission:users.manage')->name('dispatcher.users.role.store');
    Route::get('roles/{id}/edit', [App\Http\Controllers\Web\Dispatcher\UserController::class, 'editRole'])->whereNumber('id')->middleware('plan:custom_roles')->middleware('permission:users.manage')->name('dispatcher.users.role.edit');
    Route::put('roles/{id}', [App\Http\Controllers\Web\Dispatcher\UserController::class, 'updateRole'])->whereNumber('id')->middleware('plan:custom_roles')->middleware('permission:users.manage')->name('dispatcher.users.role.update');
    Route::delete('roles/{id}', [App\Http\Controllers\Web\Dispatcher\UserController::class, 'destroyRole'])->whereNumber('id')->middleware('plan:custom_roles')->middleware('permission:users.manage')->name('dispatcher.users.role.delete');

    // Every navbar badge in one poll.
    Route::get('navbar/summary', [App\Http\Controllers\Web\Dispatcher\NavbarController::class, 'summary'])->name('dispatcher.navbar.summary');
    // The token the panel opens its realtime socket with.
    Route::get('socket-token', [App\Http\Controllers\Web\Dispatcher\SocketTokenController::class, 'show'])->name('dispatcher.socket-token');

    // ── Notification Center ───────────────────────
    Route::get('notifications', [App\Http\Controllers\Web\Dispatcher\NotificationController::class, 'index'])->name('dispatcher.notifications.index');
    Route::get('notifications/summary', [App\Http\Controllers\Web\Dispatcher\NotificationController::class, 'summary'])->name('dispatcher.notifications.summary');
    Route::post('notifications/read-all', [App\Http\Controllers\Web\Dispatcher\NotificationController::class, 'markAllRead'])->name('dispatcher.notifications.read-all');
    Route::post('notifications/{id}/read', [App\Http\Controllers\Web\Dispatcher\NotificationController::class, 'markRead'])->name('dispatcher.notifications.read');
    Route::delete('notifications/{id}', [App\Http\Controllers\Web\Dispatcher\NotificationController::class, 'destroy'])->name('dispatcher.notifications.delete');

    // ── Advanced Search & Reports ─────────────────
    Route::get('search', [App\Http\Controllers\Web\Dispatcher\SearchController::class, 'index'])->middleware('plan:trips')->middleware('permission:trips.view')->name('dispatcher.search');
    Route::get('reports', [App\Http\Controllers\Web\Dispatcher\ReportController::class, 'index'])->middleware('plan:reports')->middleware('permission:reports.view')->name('dispatcher.reports');
    Route::get('reports/export', [App\Http\Controllers\Web\Dispatcher\ReportController::class, 'export'])->middleware('plan:reports')->middleware('permission:reports.view')->name('dispatcher.reports.export');

    // ── Incidents & SOS ───────────────────────────
    Route::get('incidents', [App\Http\Controllers\Web\Dispatcher\IncidentController::class, 'index'])->middleware('plan:incidents')->middleware('permission:incidents.view')->name('dispatcher.incidents.index');
    Route::get('incidents/summary', [App\Http\Controllers\Web\Dispatcher\IncidentController::class, 'summary'])->middleware('plan:incidents')->middleware('permission:incidents.view')->name('dispatcher.incidents.summary');
    Route::get('incidents/{id}', [App\Http\Controllers\Web\Dispatcher\IncidentController::class, 'show'])->whereNumber('id')->middleware('plan:incidents')->middleware('permission:incidents.view')->name('dispatcher.incidents.show');
    Route::post('incidents/{id}/acknowledge', [App\Http\Controllers\Web\Dispatcher\IncidentController::class, 'acknowledge'])->whereNumber('id')->middleware('plan:incidents')->middleware('permission:incidents.manage')->name('dispatcher.incidents.acknowledge');
    Route::post('incidents/{id}/resolve', [App\Http\Controllers\Web\Dispatcher\IncidentController::class, 'resolve'])->whereNumber('id')->middleware('plan:incidents')->middleware('permission:incidents.manage')->name('dispatcher.incidents.resolve');
    Route::post('incidents/{id}/severity', [App\Http\Controllers\Web\Dispatcher\IncidentController::class, 'updateSeverity'])->whereNumber('id')->middleware('plan:incidents')->middleware('permission:incidents.manage')->name('dispatcher.incidents.severity');

    // ── Driver chat ───────────────────────────────
    Route::get('messages', [App\Http\Controllers\Web\Dispatcher\MessageController::class, 'index'])->middleware('plan:messaging')->middleware('permission:messages.view')->name('dispatcher.messages.index');
    Route::get('messages/{driverId}', [App\Http\Controllers\Web\Dispatcher\MessageController::class, 'thread'])->whereNumber('driverId')->middleware('plan:messaging')->middleware('permission:messages.view')->name('dispatcher.messages.thread');
    Route::post('messages/{driverId}', [App\Http\Controllers\Web\Dispatcher\MessageController::class, 'store'])->whereNumber('driverId')->middleware('plan:messaging')->middleware('permission:messages.send')->name('dispatcher.messages.store');
    Route::get('messages/{driverId}/older', [App\Http\Controllers\Web\Dispatcher\MessageController::class, 'older'])->whereNumber('driverId')->middleware('plan:messaging')->middleware('permission:messages.view')->name('dispatcher.messages.older');
    Route::get('messages/{driverId}/poll', [App\Http\Controllers\Web\Dispatcher\MessageController::class, 'poll'])->whereNumber('driverId')->middleware('plan:messaging')->middleware('permission:messages.view')->name('dispatcher.messages.poll');

    Route::get('settings', [App\Http\Controllers\Web\Dispatcher\SettingsController::class, 'index'])->middleware('permission:settings.manage')->name('dispatcher.settings');
    Route::post('settings/update', [App\Http\Controllers\Web\Dispatcher\SettingsController::class, 'update'])->middleware('permission:settings.manage')->name('dispatcher.settings.update');
});
