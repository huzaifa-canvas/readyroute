<?php

namespace App\Http\Controllers\Web\Dispatcher;

use App\Http\Controllers\Controller;
use App\Services\DriverScheduleGuard;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;

class DashboardController extends Controller
{
    public function index()
    {
        $dispatcherId = auth()->user()->companyId();

        // 1. Stat Metrics
        $activeTripsCount = Trip::where('dispatcher_id', $dispatcherId)
            ->whereIn('status', ['in_progress', 'scheduled'])
            ->count();

        $totalDrivers = User::where('dispatcher_id', $dispatcherId)
            ->where('role', 'driver')
            ->get();

        // Presence is reported by the socket server on connect and disconnect,
        // and goes stale on its own if an app dies without disconnecting.
        $driversOnlineCount = $totalDrivers->filter->isCurrentlyOnline()->count();
        $driversTotalCount  = $totalDrivers->count();

        $pendingAssignmentsCount = Trip::where('dispatcher_id', $dispatcherId)
            ->whereNull('driver_id')
            ->count();

        // 2. Next Upcoming Trips
        $upcomingTrips = Trip::with(['driver', 'vehicle', 'client'])
            ->where('dispatcher_id', $dispatcherId)
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->orderBy('pickup_date', 'asc')
            ->orderBy('pickup_time', 'asc')
            ->take(6)
            ->get();

        // 3. Points for the map preview, from real records only. A company
        //    with nothing booked gets an empty map and is told so, rather
        //    than a set of invented pins in another city.
        $mapPoints = [
            'pickups' => $upcomingTrips
                ->filter(fn (Trip $trip) => $trip->pickup_lat !== null && $trip->pickup_lng !== null)
                ->map(fn (Trip $trip) => [
                    'lat'   => (float) $trip->pickup_lat,
                    'lng'   => (float) $trip->pickup_lng,
                    'label' => 'Pickup: ' . $trip->pickup_address,
                ])
                ->values(),

            'drivers' => $totalDrivers
                ->filter(fn (User $driver) => $driver->last_lat !== null && $driver->last_lng !== null)
                ->map(fn (User $driver) => [
                    'lat'    => (float) $driver->last_lat,
                    'lng'    => (float) $driver->last_lng,
                    'label'  => $driver->name . ($driver->isCurrentlyOnline() ? ' (online)' : ' (offline)'),
                    'online' => $driver->isCurrentlyOnline(),
                ])
                ->values(),
        ];

        return view('content.dispatcher.dashboard', compact(
            'activeTripsCount',
            'driversOnlineCount',
            'driversTotalCount',
            'pendingAssignmentsCount',
            'upcomingTrips',
            'totalDrivers',
            'mapPoints'
        ));
    }

    public function assignDriver(Request $request, $id, DriverScheduleGuard $guard)
    {
        $companyId = auth()->user()->companyId();

        $request->validate([
            // Scoped to this company and to drivers: a bare exists rule would let
            // one company put another's driver — or a non-driver — on a trip.
            'driver_id' => ['required', Rule::exists('users', 'id')
                ->where('role', 'driver')
                ->where('dispatcher_id', $companyId)],
            'force' => ['nullable', 'boolean'],
        ]);

        $trip = Trip::where('dispatcher_id', $companyId)->findOrFail($id);

        /*
         * Unless the dispatcher has chosen to force it, refuse an assignment
         * that stacks this driver onto another trip too close in time. The panel
         * answers a 409 by offering "Force Assign" or "Change Driver"; a plain
         * form post (no JS) falls back to a flashed error.
         */
        if (! $request->boolean('force')) {
            $conflicts = $guard->conflicts(
                $companyId,
                (int) $request->input('driver_id'),
                $trip->scheduledPickupAt(),
                $trip->id
            );

            if ($conflicts->isNotEmpty()) {
                $payload = $guard->payload($conflicts, $trip->scheduledPickupAt());

                if ($request->expectsJson()) {
                    return response()->json($payload, 409);
                }

                return redirect()->back()->withErrors(['driver_id' => $payload['message']]);
            }
        }

        // The vehicle comes from whoever is driving, as everywhere else a driver
        // is set — so assigning from the dashboard never leaves the trip without
        // the vehicle it will actually run in.
        $trip->update([
            'driver_id'  => $request->input('driver_id'),
            'vehicle_id' => $this->vehicleForDriver($companyId, $request->input('driver_id')),
        ]);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => 'Driver assigned successfully!']);
        }

        return redirect()->back()->with('success', 'Driver assigned successfully!');
    }

    /**
     * The vehicle a driver runs, taken from the driver rather than any input.
     */
    private function vehicleForDriver(int $companyId, mixed $driverId): ?int
    {
        if (blank($driverId)) {
            return null;
        }

        return User::driversOf($companyId)->whereKey($driverId)->first()?->assignedVehicle?->id;
    }

    public function liveMap()
    {
        $dispatcherId = auth()->user()->companyId();

        $trips = Trip::with(['driver', 'vehicle', 'client'])
            ->where('dispatcher_id', $dispatcherId)
            ->get();

        $drivers = User::where('dispatcher_id', $dispatcherId)
            ->where('role', 'driver')
            ->get();

        // Collection filtering, not a query: comparing an enum-cast attribute
        // against raw strings here would silently match nothing.
        $activeTripsCount = $trips
            ->filter(fn ($trip) => ! ($trip->statusEnum()?->isTerminal() ?? true))
            ->count();

        $onlineDriversCount = $drivers->filter->isCurrentlyOnline()->count();

        // Real positions for the map, reported by the driver app. Drivers who
        // have never sent a fix are left out rather than placed at (0, 0).
        $driverPositions = $drivers
            ->filter(fn (User $driver) => $driver->last_lat !== null && $driver->last_lng !== null)
            ->map(fn (User $driver) => [
                'id'        => $driver->id,
                'name'      => $driver->name,
                'code'      => $driver->driver_code,
                'lat'       => (float) $driver->last_lat,
                'lng'       => (float) $driver->last_lng,
                'is_online' => $driver->isCurrentlyOnline(),
                'seen'      => optional($driver->last_location_at)->diffForHumans(),
            ])
            ->values();

        // Pickup and drop-off pins, built here rather than in the view: a
        // closure this shape inside @json() is more than Blade's directive
        // parser can follow.
        $tripPoints = $trips->flatMap(function (Trip $trip) {
            $points = [];

            if ($trip->pickup_lat !== null && $trip->pickup_lng !== null) {
                $points[] = [
                    'kind'  => 'pickup',
                    'lat'   => (float) $trip->pickup_lat,
                    'lng'   => (float) $trip->pickup_lng,
                    'title' => 'Pickup: ' . $trip->passengerName(),
                    'note'  => $trip->pickup_address,
                ];
            }

            if ($trip->dropoff_lat !== null && $trip->dropoff_lng !== null) {
                $points[] = [
                    'kind'  => 'dropoff',
                    'lat'   => (float) $trip->dropoff_lat,
                    'lng'   => (float) $trip->dropoff_lng,
                    'title' => 'Drop-off: ' . $trip->passengerName(),
                    'note'  => $trip->dropoff_address,
                ];
            }

            return $points;
        })->values();

        return view('content.dispatcher.live-map', compact(
            'trips',
            'drivers',
            'activeTripsCount',
            'onlineDriversCount',
            'driverPositions'
        ,
            'tripPoints'
        ));
    }
}
