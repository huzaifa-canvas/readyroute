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

        $driversOnlineCount = $totalDrivers->count();
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
}
