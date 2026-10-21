<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Dispatcher\AuthController;
use App\Http\Controllers\Api\Dispatcher\AutoDispatchController;
use App\Http\Controllers\Api\Dispatcher\BillingController;
use App\Http\Controllers\Api\Dispatcher\ClientController;
use App\Http\Controllers\Api\Dispatcher\ComplianceController;
use App\Http\Controllers\Api\Dispatcher\DashboardController;
use App\Http\Controllers\Api\Dispatcher\DriverController;
use App\Http\Controllers\Api\Dispatcher\IncidentController;
use App\Http\Controllers\Api\Dispatcher\LiveMapController;
use App\Http\Controllers\Api\Dispatcher\MessageController;
use App\Http\Controllers\Api\Dispatcher\NotificationController;
use App\Http\Controllers\Api\Dispatcher\PanelUserController;
use App\Http\Controllers\Api\Dispatcher\ReportController;
use App\Http\Controllers\Api\Dispatcher\ScheduleController;
use App\Http\Controllers\Api\Dispatcher\SearchController;
use App\Http\Controllers\Api\Dispatcher\SettingsController;
use App\Http\Controllers\Api\Dispatcher\SubscriptionController;
use App\Http\Controllers\Api\Dispatcher\TripController;
use App\Http\Controllers\Api\Dispatcher\VehicleController;

// ═══════════════════════════════════════════════════
// DISPATCHER MOBILE APP API ROUTES
// ═══════════════════════════════════════════════════
//
// Everything the dispatcher app can reach, in the order the app meets it.
// Each section matches a screen or a group of screens, and mirrors a feature
// that already exists in the web panel — the panel and the app are two front
// ends over one set of rules, not two products.
//
// Four gates run on the authenticated routes, and they answer four different
// questions:
//
//   subscribed    is this company paying at all?      -> 402, read-only
//   plan:x        does their tier include x?          -> 402, upgrade
//   limit:x       is there room left inside the tier? -> 402, upgrade
//   permission:x  is THIS person allowed?             -> 403
//
// They are the same middleware the web panel uses, so the app and the panel
// can never disagree about what is allowed. On top of them every query is
// scoped to the signed-in user's company by BaseDispatcherController, so an id
// from another tenant answers 404.

Route::prefix('dispatcher')->group(function () {

    // ── Public ────────────────────────────────────
    // Rate limited because these are the only endpoints that can be probed
    // without credentials.
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    });

    // ── Authenticated ─────────────────────────────
    // Without a live plan the app becomes read-only: every GET still answers,
    // every write is refused with 402. The routes that must keep working while
    // locked are named here and listed in EnsureActiveSubscription — otherwise
    // a company could never pay, because paying is itself a write.
    Route::middleware(['auth:sanctum', 'role:dispatcher,admin', 'subscribed'])->group(function () {

        // ── Session & profile ─────────────────────
        Route::post('/logout', [AuthController::class, 'logout'])->name('api.dispatcher.logout');
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/user', [AuthController::class, 'me']); // legacy alias
        Route::post('/device-token', [AuthController::class, 'storeDeviceToken'])->name('api.dispatcher.device-token');

        Route::get('/profile', [SettingsController::class, 'profile']);
        Route::post('/profile', [SettingsController::class, 'updateProfile'])->name('api.dispatcher.profile.update');
        Route::post('/profile/password', [SettingsController::class, 'updatePassword'])->name('api.dispatcher.profile.password');
        Route::post('/profile/2fa', [SettingsController::class, 'toggleTwoFactor'])->name('api.dispatcher.profile.2fa');

        // ── Home ──────────────────────────────────
        Route::get('/dashboard', [DashboardController::class, 'index']);
        // The badge poll, for while the app is on another screen.
        Route::get('/summary', [DashboardController::class, 'summary']);

        // ── Trips ─────────────────────────────────
        // The static paths come before /trips/{id}, or "calendar" is read as a
        // trip id and answers 404.
        Route::middleware('plan:trips')->group(function () {
            Route::get('/trips', [TripController::class, 'index'])->middleware('permission:trips.view');
            // The monthly trip cap is checked here and nowhere else: this is
            // the only route that adds one.
            Route::post('/trips', [TripController::class, 'store'])->middleware(['permission:trips.create', 'limit:trip']);
            Route::get('/trips/form-options', [TripController::class, 'formOptions'])->middleware('permission:trips.create');
            Route::get('/trips/calendar', [ScheduleController::class, 'calendar'])->middleware('permission:trips.view');
            Route::get('/trips/driver-load', [ScheduleController::class, 'driverLoad'])->middleware('permission:trips.view');

            Route::get('/trips/{id}', [TripController::class, 'show'])->whereNumber('id')->middleware('permission:trips.view');
            Route::put('/trips/{id}', [TripController::class, 'update'])->whereNumber('id')->middleware('permission:trips.edit');
            Route::post('/trips/{id}/assign', [TripController::class, 'assign'])->whereNumber('id')->middleware('permission:trips.assign,trips.edit');
            Route::post('/trips/{id}/cancel', [TripController::class, 'cancel'])->whereNumber('id')->middleware('permission:trips.cancel');
            Route::delete('/trips/{id}', [TripController::class, 'destroy'])->whereNumber('id')->middleware('permission:trips.delete');
            Route::post('/trips/{id}/tracking-link', [TripController::class, 'trackingLink'])->whereNumber('id')->middleware('permission:trips.edit');
        });

        // ── Drivers ───────────────────────────────
        Route::middleware('plan:drivers')->group(function () {
            Route::get('/drivers', [DriverController::class, 'index'])->middleware('permission:drivers.view');
            Route::post('/drivers', [DriverController::class, 'store'])->middleware(['permission:drivers.manage', 'limit:driver']);
            Route::get('/drivers/assignable-vehicles', [DriverController::class, 'assignableVehicles'])->middleware('permission:drivers.manage');
            Route::get('/drivers/{id}', [DriverController::class, 'show'])->whereNumber('id')->middleware('permission:drivers.view');
            // POST rather than PUT: the form carries a photo, and multipart
            // does not survive a PUT in most mobile HTTP clients.
            Route::post('/drivers/{id}', [DriverController::class, 'update'])->whereNumber('id')->middleware('permission:drivers.manage');
            Route::delete('/drivers/{id}', [DriverController::class, 'destroy'])->whereNumber('id')->middleware('permission:drivers.manage');

            // Switch a driver off without losing their record. Their work has
            // to be moved first; the refusal lists what is in the way, so the
            // app can send the dispatcher straight to it.
            Route::get('/drivers/{id}/blocking-trips', [DriverController::class, 'blockingTrips'])->whereNumber('id')->middleware('permission:drivers.view');
            Route::post('/drivers/{id}/deactivate', [DriverController::class, 'deactivate'])->whereNumber('id')->middleware('permission:drivers.manage');
            Route::post('/drivers/{id}/activate', [DriverController::class, 'reactivate'])->whereNumber('id')->middleware('permission:drivers.manage');
        });

        // ── Vehicles ──────────────────────────────
        Route::middleware('plan:fleet')->group(function () {
            Route::get('/vehicles', [VehicleController::class, 'index'])->middleware('permission:fleet.view');
            Route::post('/vehicles', [VehicleController::class, 'store'])->middleware(['permission:fleet.manage', 'limit:vehicle']);
            Route::get('/vehicles/{id}', [VehicleController::class, 'show'])->whereNumber('id')->middleware('permission:fleet.view');
            Route::post('/vehicles/{id}', [VehicleController::class, 'update'])->whereNumber('id')->middleware('permission:fleet.manage');
            Route::delete('/vehicles/{id}', [VehicleController::class, 'destroy'])->whereNumber('id')->middleware('permission:fleet.manage');

            // Service history, and the drivers' pre-trip checks, per vehicle.
            Route::get('/vehicles/{id}/maintenance', [VehicleController::class, 'maintenance'])->whereNumber('id')->middleware('permission:fleet.view');
            Route::post('/vehicles/{id}/maintenance', [VehicleController::class, 'storeMaintenance'])->whereNumber('id')->middleware('permission:fleet.manage');
            Route::put('/vehicles/{id}/maintenance/{logId}', [VehicleController::class, 'updateMaintenance'])->whereNumber('id')->whereNumber('logId')->middleware('permission:fleet.manage');
            Route::delete('/vehicles/{id}/maintenance/{logId}', [VehicleController::class, 'destroyMaintenance'])->whereNumber('id')->whereNumber('logId')->middleware('permission:fleet.manage');

            // Read-only: a signed check is the driver's record, not the
            // office's to amend.
            Route::get('/vehicles/{id}/inspections', [VehicleController::class, 'inspections'])->whereNumber('id')->middleware('permission:fleet.view');
        });

        // ── Clients ───────────────────────────────
        Route::middleware('plan:clients')->group(function () {
            Route::get('/clients', [ClientController::class, 'index'])->middleware('permission:clients.view');
            Route::post('/clients', [ClientController::class, 'store'])->middleware('permission:clients.manage');
            Route::get('/clients/{id}', [ClientController::class, 'show'])->whereNumber('id')->middleware('permission:clients.view');
            Route::put('/clients/{id}', [ClientController::class, 'update'])->whereNumber('id')->middleware('permission:clients.manage');
            Route::delete('/clients/{id}', [ClientController::class, 'destroy'])->whereNumber('id')->middleware('permission:clients.manage');

            Route::get('/clients/{id}/notes', [ClientController::class, 'notes'])->whereNumber('id')->middleware('permission:clients.view');
            Route::post('/clients/{id}/notes', [ClientController::class, 'storeNote'])->whereNumber('id')->middleware('permission:clients.notes');
            Route::put('/clients/{id}/notes/{noteId}', [ClientController::class, 'updateNote'])->whereNumber('id')->whereNumber('noteId')->middleware('permission:clients.notes');
            Route::delete('/clients/{id}/notes/{noteId}', [ClientController::class, 'destroyNote'])->whereNumber('id')->whereNumber('noteId')->middleware('permission:clients.notes');
        });

        // ── Live map ──────────────────────────────
        Route::middleware(['plan:live_map', 'permission:live_map.view'])->group(function () {
            Route::get('/live-map', [LiveMapController::class, 'index']);
            Route::get('/live-map/driver/{id}', [LiveMapController::class, 'driver'])->whereNumber('id');
        });

        // ── Messages ──────────────────────────────
        // Real-time delivery is the socket's job; poll is the fallback so a
        // dropped socket never means a missed message.
        Route::middleware(['plan:messaging'])->group(function () {
            Route::get('/messages', [MessageController::class, 'index'])->middleware('permission:messages.view');
            Route::get('/messages/{driverId}', [MessageController::class, 'thread'])->whereNumber('driverId')->middleware('permission:messages.view');
            Route::post('/messages/{driverId}', [MessageController::class, 'store'])->whereNumber('driverId')->middleware('permission:messages.send');
            Route::post('/messages/{driverId}/read', [MessageController::class, 'markRead'])->whereNumber('driverId')->middleware('permission:messages.view');
            Route::get('/messages/{driverId}/poll', [MessageController::class, 'poll'])->whereNumber('driverId')->middleware('permission:messages.view');
        });

        // ── Notifications ─────────────────────────
        // No plan gate: being told what happened in your own company is not a
        // feature anyone should have to buy.
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);
        Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);

        // ── Incidents & SOS ───────────────────────
        Route::middleware('plan:incidents')->group(function () {
            Route::get('/incidents', [IncidentController::class, 'index'])->middleware('permission:incidents.view');
            Route::get('/incidents/{id}', [IncidentController::class, 'show'])->whereNumber('id')->middleware('permission:incidents.view');
            Route::post('/incidents/{id}/acknowledge', [IncidentController::class, 'acknowledge'])->whereNumber('id')->middleware('permission:incidents.manage');
            Route::post('/incidents/{id}/resolve', [IncidentController::class, 'resolve'])->whereNumber('id')->middleware('permission:incidents.manage');
            Route::post('/incidents/{id}/severity', [IncidentController::class, 'updateSeverity'])->whereNumber('id')->middleware('permission:incidents.manage');
        });

        // ── Compliance ────────────────────────────
        Route::middleware('plan:compliance')->group(function () {
            Route::get('/compliance', [ComplianceController::class, 'index'])->middleware('permission:drivers.compliance');
            Route::get('/compliance/driver/{driverId}', [ComplianceController::class, 'driver'])->whereNumber('driverId')->middleware('permission:drivers.compliance');
            Route::post('/compliance/driver/{driverId}', [ComplianceController::class, 'store'])->whereNumber('driverId')->middleware('permission:drivers.compliance');
            Route::post('/compliance/driver/{driverId}/{documentId}', [ComplianceController::class, 'update'])->whereNumber('driverId')->whereNumber('documentId')->middleware('permission:drivers.compliance');
            Route::delete('/compliance/driver/{driverId}/{documentId}', [ComplianceController::class, 'destroy'])->whereNumber('driverId')->whereNumber('documentId')->middleware('permission:drivers.compliance');
            Route::get('/compliance/driver/{driverId}/{documentId}/download', [ComplianceController::class, 'download'])->whereNumber('driverId')->whereNumber('documentId')->middleware('permission:drivers.compliance');
        });

        // ── Reports ───────────────────────────────
        Route::middleware(['plan:reports', 'permission:reports.view'])->group(function () {
            Route::get('/reports', [ReportController::class, 'index']);
            Route::get('/reports/export', [ReportController::class, 'export']);
        });

        // ── Search ────────────────────────────────
        // Reads across trips, clients and drivers; each of those already has
        // its own gate on the screens the results link to.
        Route::get('/search', [SearchController::class, 'index']);

        // ── Auto-dispatch ─────────────────────────
        Route::middleware(['plan:auto_dispatch', 'permission:auto_dispatch.run'])->group(function () {
            Route::get('/auto-dispatch', [AutoDispatchController::class, 'index']);
            Route::post('/auto-dispatch/optimize', [AutoDispatchController::class, 'optimize']);
        });

        // ── Settings ──────────────────────────────
        Route::get('/settings', [SettingsController::class, 'show']);
        Route::put('/settings', [SettingsController::class, 'update']);
        Route::get('/settings/company', [SettingsController::class, 'company'])->middleware('permission:settings.manage');
        Route::put('/settings/company', [SettingsController::class, 'updateCompany'])->middleware('permission:settings.manage');
        Route::get('/settings/support', [SettingsController::class, 'support']);

        // ── Billing: invoices the company raises ──
        Route::middleware('plan:billing')->group(function () {
            Route::get('/billing', [BillingController::class, 'index'])->middleware('permission:billing.view');
            Route::post('/billing', [BillingController::class, 'store'])->middleware('permission:billing.manage');
            Route::get('/billing/unbilled', [BillingController::class, 'unbilled'])->middleware('permission:billing.view');
            Route::get('/billing/{id}', [BillingController::class, 'show'])->whereNumber('id')->middleware('permission:billing.view');
            Route::put('/billing/{id}', [BillingController::class, 'update'])->whereNumber('id')->middleware('permission:billing.manage');
            Route::post('/billing/{id}/status', [BillingController::class, 'updateStatus'])->whereNumber('id')->middleware('permission:billing.manage');
        });

        // ── Subscription: what the company pays us ─
        // Deliberately outside every plan gate, and named so the read-only
        // lock lets these through: this is the way back for a company whose
        // subscription has lapsed.
        Route::get('/subscription', [SubscriptionController::class, 'index'])->name('api.dispatcher.subscription');
        Route::get('/subscription/invoices', [SubscriptionController::class, 'invoices']);
        Route::post('/subscription/checkout', [SubscriptionController::class, 'checkout'])->middleware('permission:subscription.manage')->name('api.dispatcher.subscription.checkout');
        Route::post('/subscription/confirm', [SubscriptionController::class, 'confirm'])->middleware('permission:subscription.manage')->name('api.dispatcher.subscription.confirm');
        Route::post('/subscription/cancel', [SubscriptionController::class, 'cancel'])->middleware('permission:subscription.manage')->name('api.dispatcher.subscription.cancel');
        Route::post('/subscription/resume', [SubscriptionController::class, 'resume'])->middleware('permission:subscription.manage')->name('api.dispatcher.subscription.resume');
        Route::post('/subscription/plan-change/cancel', [SubscriptionController::class, 'cancelPlanChange'])->middleware('permission:subscription.manage')->name('api.dispatcher.subscription.plan-change.cancel');

        // ── Panel users & roles ───────────────────
        Route::middleware('plan:system_users')->group(function () {
            Route::get('/users', [PanelUserController::class, 'index'])->middleware('permission:users.manage');
            Route::post('/users', [PanelUserController::class, 'store'])->middleware('permission:users.manage');
            Route::put('/users/{id}', [PanelUserController::class, 'update'])->whereNumber('id')->middleware('permission:users.manage');
            Route::post('/users/{id}/suspend', [PanelUserController::class, 'suspend'])->whereNumber('id')->middleware('permission:users.manage');
            Route::post('/users/{id}/activate', [PanelUserController::class, 'activate'])->whereNumber('id')->middleware('permission:users.manage');
            Route::delete('/users/{id}', [PanelUserController::class, 'destroy'])->whereNumber('id')->middleware('permission:users.manage');
        });

        Route::middleware(['plan:custom_roles', 'permission:users.manage'])->group(function () {
            Route::post('/roles', [PanelUserController::class, 'storeRole']);
            Route::put('/roles/{id}', [PanelUserController::class, 'updateRole'])->whereNumber('id');
            Route::delete('/roles/{id}', [PanelUserController::class, 'destroyRole'])->whereNumber('id');
        });
    });
});
