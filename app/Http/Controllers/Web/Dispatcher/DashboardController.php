<?php

namespace App\Http\Controllers\Web\Dispatcher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;

class DashboardController extends Controller
{
    public function index()
    {
        $dispatcherId = auth()->id();

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

        // If no trips exist in database, create demonstration collection for display
        if ($upcomingTrips->isEmpty()) {
            // Keep empty or let view handle demonstration
        }

        return view('content.dispatcher.dashboard', compact(
            'activeTripsCount',
            'driversOnlineCount',
            'driversTotalCount',
            'pendingAssignmentsCount',
            'upcomingTrips',
            'totalDrivers'
        ));
    }

    public function assignDriver(Request $request, $id)
    {
        $request->validate([
            'driver_id' => 'required|exists:users,id',
        ]);

        $trip = Trip::where('dispatcher_id', auth()->id())->findOrFail($id);
        $trip->driver_id = $request->driver_id;
        $trip->save();

        return redirect()->back()->with('success', 'Driver assigned successfully!');
    }

    public function liveMap()
    {
        $dispatcherId = auth()->id();

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

        return view('content.dispatcher.live-map', compact(
            'trips',
            'drivers',
            'activeTripsCount',
            'onlineDriversCount',
            'driverPositions'
        ));
    }
}
