<?php

namespace App\Http\Controllers\Api\Dispatcher;

use App\Enums\TripStatus;
use App\Http\Resources\Dispatcher\DriverResource;
use App\Http\Resources\Dispatcher\TripListResource;
use App\Http\Resources\Dispatcher\VehicleResource;
use App\Models\DriverDocument;
use App\Models\Trip;
use App\Models\TripIncident;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Drivers: the roster, one driver in full, and the forms that add or change
 * them.
 *
 * A driver cannot exist without a vehicle here, exactly as on the web panel —
 * without one they can neither open a pre-trip inspection nor start a run, so
 * the vehicle is chosen when the driver is created rather than left for later.
 */
class DriverController extends BaseDispatcherController
{
    public function index(Request $request): JsonResponse
    {
        $query = $this->drivers()
            // Counted rather than queried per row: dutyState() needs to know
            // whether each driver has a run under way.
            ->withCount('activeTrips')
            ->with(['metas', 'assignedVehicle']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('driver_code', 'like', "%{$search}%");
            });
        }

        $drivers = $query->orderBy('name')->get();

        // The filter chips along the top of the list are duty states, which
        // are worked out in PHP, so the filter is applied after loading
        // rather than as a where clause that could never see them.
        if ($request->filled('duty') && $request->input('duty') !== 'all') {
            $wanted  = $request->input('duty');
            $drivers = $drivers->filter(fn (User $driver) => $driver->dutyState() === $wanted)->values();
        }

        return $this->ok([
            'drivers' => DriverResource::collection($drivers),
            // "12 drivers · 8 active now" above the list.
            'summary' => [
                'total'      => $this->drivers()->count(),
                'active_now' => $this->drivers()->get()->filter->isCurrentlyOnline()->count(),
            ],
            'duty_filters' => [
                ['value' => 'all',      'label' => 'All'],
                ['value' => 'active',   'label' => 'Active'],
                ['value' => 'on_trip',  'label' => 'On Trip'],
                ['value' => 'off_duty', 'label' => 'Off Duty'],
                ['value' => 'inactive', 'label' => 'Inactive'],
            ],
        ]);
    }

    /**
     * One driver in full: where they are, what they are carrying, how they
     * have been performing and what paperwork is due.
     */
    public function show(int $id): JsonResponse
    {
        $driver = $this->drivers()->with(['metas', 'assignedVehicle'])->find($id);

        if (! $driver) {
            return $this->notFound('Driver not found.');
        }

        $completed = Trip::forDriver($driver->id)->where('status', TripStatus::Completed->value);
        $scored    = (clone $completed)->whereNotNull('was_on_time')->count();

        return $this->ok([
            'driver' => new DriverResource($driver),

            'active_trip' => Trip::forDriver($driver->id)->active()->with(['client', 'driver', 'vehicle'])->first()
                ? new TripListResource(Trip::forDriver($driver->id)->active()->with(['client', 'driver', 'vehicle'])->first())
                : null,

            'todays_trips' => TripListResource::collection(
                Trip::forDriver($driver->id)
                    ->with(['client', 'driver', 'vehicle'])
                    ->whereDate('pickup_date', today())
                    ->orderBy('pickup_time')
                    ->get()
            ),

            'recent_trips' => TripListResource::collection(
                Trip::forDriver($driver->id)
                    ->with(['client', 'driver', 'vehicle'])
                    ->latest('pickup_date')
                    ->limit(10)
                    ->get()
            ),

            // Lifetime figures, so the screen says something even for a driver
            // who has had a quiet month.
            'stats' => [
                'total_trips' => Trip::forDriver($driver->id)->count(),
                'completed'   => (clone $completed)->count(),
                'miles'       => round((float) (clone $completed)->sum('actual_distance'), 1),
                'today'       => Trip::forDriver($driver->id)->whereDate('pickup_date', today())->count(),
                'open_incidents' => TripIncident::where('driver_id', $driver->id)->open()->count(),
                // Only meaningful for runs that were actually scored.
                'on_time_rate' => $scored > 0
                    ? round((clone $completed)->where('was_on_time', true)->count() / $scored * 100)
                    : null,
            ],

            'documents' => DriverDocument::where('driver_id', $driver->id)
                ->orderByRaw('expires_on IS NULL')
                ->orderBy('expires_on')
                ->get()
                ->map(fn (DriverDocument $doc) => [
                    'id'         => $doc->id,
                    'type'       => $doc->type,
                    'label'      => $doc->label ?? $doc->type,
                    'reference'  => $doc->reference,
                    'status'     => $doc->status,
                    'expires_on' => optional($doc->expires_on)->toDateString(),
                    'is_expired' => $doc->expires_on ? $doc->expires_on->isPast() : false,
                    'days_left'  => $doc->expires_on ? now()->startOfDay()->diffInDays($doc->expires_on, false) : null,
                ]),

            'incidents' => TripIncident::where('driver_id', $driver->id)
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn (TripIncident $incident) => [
                    'id'       => $incident->id,
                    'type'     => $incident->type,
                    'severity' => $incident->severity,
                    'status'   => $incident->status,
                    'at'       => optional($incident->created_at)->toIso8601String(),
                ]),

            // The last few pings, newest first, for the position panel.
            'trail' => $driver->locations()->latest()->limit(10)->get()
                ->map(fn ($point) => [
                    'lat' => (float) $point->lat,
                    'lng' => (float) $point->lng,
                    'at'  => optional($point->created_at)->toIso8601String(),
                ]),
        ]);
    }

    /**
     * The vehicles this company can put a driver in.
     *
     * A vehicle carries one driver, so anything already taken is left out —
     * except the one the driver being edited already has, which must stay in
     * the list or saving would look like it was unassigning them.
     */
    public function assignableVehicles(Request $request): JsonResponse
    {
        $keepFor = $request->integer('driver_id') ?: null;

        return $this->ok(
            VehicleResource::collection($this->availableVehicles($keepFor))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'phone_number'  => ['nullable', 'string', 'max:50'],
            'profile_image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
            'driver_license_number' => ['nullable', 'string', 'max:100'],
            'license_state'         => ['nullable', 'string', 'max:50'],
            'license_expiry_date'   => ['nullable', 'date'],
            'cdl_class'             => ['nullable', 'string', 'max:100'],
            'internal_notes'        => ['nullable', 'string'],
            'vehicle_id'            => $this->vehicleRules(),
        ], [
            'vehicle_id.required' => 'Choose the vehicle this driver will run.',
            'vehicle_id.exists'   => 'That vehicle is not available — it belongs to another driver.',
        ]);

        $driver = User::create([
            'name'          => $request->input('name'),
            'email'         => $request->input('email'),
            'password'      => Hash::make($request->input('password')),
            'role'          => 'driver',
            'dispatcher_id' => $this->companyId(),
            'phone_number'  => $request->input('phone_number'),
            'profile_image' => $request->hasFile('profile_image')
                ? $request->file('profile_image')->store('profile_images', 'public')
                : null,
        ]);

        $driver->syncMetas($this->metaFrom($request));

        $this->assignVehicle($driver, $request->integer('vehicle_id'));

        return $this->created(
            new DriverResource($driver->fresh()->load(['metas', 'assignedVehicle'])),
            'Driver added and assigned ' . $driver->fresh()->assignedVehicle?->name . '.'
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $driver = $this->drivers()->find($id);

        if (! $driver) {
            return $this->notFound('Driver not found.');
        }

        $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($driver->id)],
            'password'      => ['nullable', 'string', 'min:8'],
            'phone_number'  => ['nullable', 'string', 'max:50'],
            'profile_image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
            'driver_license_number' => ['nullable', 'string', 'max:100'],
            'license_state'         => ['nullable', 'string', 'max:50'],
            'license_expiry_date'   => ['nullable', 'date'],
            'cdl_class'             => ['nullable', 'string', 'max:100'],
            'internal_notes'        => ['nullable', 'string'],
            'vehicle_id'            => $this->vehicleRules($driver->id),
        ], [
            'vehicle_id.required' => 'Choose the vehicle this driver will run.',
            'vehicle_id.exists'   => 'That vehicle is not available — it belongs to another driver.',
        ]);

        $data = [
            'name'         => $request->input('name'),
            'email'        => $request->input('email'),
            'phone_number' => $request->input('phone_number'),
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->input('password'));
        }

        if ($request->hasFile('profile_image')) {
            if ($driver->profile_image) {
                Storage::disk('public')->delete($driver->profile_image);
            }

            $data['profile_image'] = $request->file('profile_image')->store('profile_images', 'public');
        }

        $driver->update($data);
        $driver->syncMetas($this->metaFrom($request));

        $this->assignVehicle($driver, $request->integer('vehicle_id'));

        return $this->ok(
            new DriverResource($driver->fresh()->load(['metas', 'assignedVehicle'])),
            'Driver updated.'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $driver = $this->drivers()->find($id);

        if (! $driver) {
            return $this->notFound('Driver not found.');
        }

        if ($driver->profile_image) {
            Storage::disk('public')->delete($driver->profile_image);
        }

        // Put the vehicle back in the pool, or it stays held by a driver who
        // is gone and can never be given to anyone else.
        Vehicle::where('assigned_driver_id', $driver->id)->update(['assigned_driver_id' => null]);

        $driver->delete();

        return $this->ok(null, 'Driver deleted.');
    }

    // ── Shared pieces ────────────────────────────────────────────────

    private function availableVehicles(?int $keepForDriverId = null)
    {
        return $this->vehicles()
            ->where(function ($query) use ($keepForDriverId) {
                $query->whereNull('assigned_driver_id');

                if ($keepForDriverId) {
                    $query->orWhere('assigned_driver_id', $keepForDriverId);
                }
            })
            ->with('assignedDriver')
            ->orderBy('name')
            ->get();
    }

    /**
     * The rule a vehicle choice has to pass: it belongs to this company, and
     * it is not already someone else's.
     */
    private function vehicleRules(?int $forDriverId = null): array
    {
        return [
            'required',
            Rule::exists('vehicles', 'id')
                ->where('dispatcher_id', $this->companyId())
                ->where(function ($query) use ($forDriverId) {
                    $query->whereNull('assigned_driver_id');

                    if ($forDriverId) {
                        $query->orWhere('assigned_driver_id', $forDriverId);
                    }
                }),
        ];
    }

    /**
     * Hand a vehicle to a driver, taking it off whoever had it.
     *
     * One transaction: a half-finished swap would leave either two drivers
     * holding one vehicle, or a driver holding none.
     */
    private function assignVehicle(User $driver, int $vehicleId): void
    {
        DB::transaction(function () use ($driver, $vehicleId) {
            Vehicle::where('assigned_driver_id', $driver->id)
                ->where('id', '!=', $vehicleId)
                ->update(['assigned_driver_id' => null]);

            Vehicle::where('id', $vehicleId)
                ->where('dispatcher_id', $this->companyId())
                ->update(['assigned_driver_id' => $driver->id]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function metaFrom(Request $request): array
    {
        return [
            'driver_license_number' => $request->input('driver_license_number'),
            'license_state'         => $request->input('license_state'),
            'license_expiry_date'   => $request->input('license_expiry_date'),
            'cdl_class'             => $request->input('cdl_class'),
            'availability_mon_fri'  => $request->boolean('availability_mon_fri') ? '1' : '0',
            'availability_sat'      => $request->boolean('availability_sat') ? '1' : '0',
            'availability_sun'      => $request->boolean('availability_sun') ? '1' : '0',
            'availability_on_call'  => $request->boolean('availability_on_call') ? '1' : '0',
            'internal_notes'        => $request->input('internal_notes'),
        ];
    }

    // ── Switching a driver off ───────────────────────────────────────

    /**
     * Deactivate a driver without deleting them.
     *
     * Deleting loses the history; deactivating keeps the account and its
     * record while stopping the person working. They cannot sign in to the
     * driver app afterwards, and the tokens they were holding are revoked, so
     * a session open at the time ends too.
     *
     * Work has to be moved first. A driver who cannot sign in but is still
     * named on a run leaves that run in limbo: nobody is driving it, and
     * nothing on the board says so. Both a trip under way and a booking two
     * weeks out count, because neither has a driver once this is done — and
     * the refusal lists them, so the app can send the dispatcher straight to
     * the work rather than leaving them to find it.
     */
    public function deactivate(Request $request, int $id): JsonResponse
    {
        $driver = $this->drivers()->find($id);

        if (! $driver) {
            return $this->notFound('Driver not found.');
        }

        $request->validate([
            'suspension_reason' => ['nullable', 'string', 'max:255'],
        ]);

        if ($driver->isSuspended()) {
            return $this->fail($driver->name . ' is already deactivated.', 422);
        }

        $blocking = $driver->tripsBlockingDeactivation()
            ->with('client')
            ->orderBy('pickup_date')
            ->get();

        if ($blocking->isNotEmpty()) {
            return response()->json([
                'status'  => false,
                'message' => sprintf(
                    '%s still has %d %s assigned. Reassign or cancel %s before deactivating them.',
                    $driver->name,
                    $blocking->count(),
                    \Illuminate\Support\Str::plural('trip', $blocking->count()),
                    $blocking->count() === 1 ? 'it' : 'them'
                ),
                'data' => [
                    'blocking_trips' => TripListResource::collection($blocking),
                    'blocking_count' => $blocking->count(),
                ],
                'errors' => null,
            ], 422);
        }

        $driver->suspend($request->input('suspension_reason'));

        return $this->ok(
            new DriverResource($driver->fresh()->load(['metas', 'assignedVehicle'])),
            $driver->name . ' has been deactivated and can no longer sign in to the driver app.'
        );
    }

    public function reactivate(int $id): JsonResponse
    {
        $driver = $this->drivers()->find($id);

        if (! $driver) {
            return $this->notFound('Driver not found.');
        }

        if (! $driver->isSuspended()) {
            return $this->fail($driver->name . ' is already active.', 422);
        }

        $driver->activate();

        return $this->ok(
            new DriverResource($driver->fresh()->load(['metas', 'assignedVehicle'])),
            $driver->name . ' can sign in again.'
        );
    }

    /**
     * What stands in the way of deactivating this driver.
     *
     * Asked before showing the button, so the app can grey it out and say why
     * instead of offering an action that will be refused.
     */
    public function blockingTrips(int $id): JsonResponse
    {
        $driver = $this->drivers()->find($id);

        if (! $driver) {
            return $this->notFound('Driver not found.');
        }

        $blocking = $driver->tripsBlockingDeactivation()
            ->with(['client', 'driver', 'vehicle'])
            ->orderBy('pickup_date')
            ->orderBy('pickup_time')
            ->get();

        return $this->ok([
            'can_deactivate' => $blocking->isEmpty(),
            'blocking_count' => $blocking->count(),
            'blocking_trips' => TripListResource::collection($blocking),
        ]);
    }
}
