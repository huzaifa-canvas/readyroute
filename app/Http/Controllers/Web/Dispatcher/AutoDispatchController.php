<?php

namespace App\Http\Controllers\Web\Dispatcher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Trip;
use App\Models\User;

class AutoDispatchController extends Controller
{
    public function index()
    {
        $dispatcherId = auth()->id();

        // Fetch all active & scheduled trips (both assigned & unassigned)
        $allTrips = Trip::with(['client', 'driver'])
            ->where('dispatcher_id', $dispatcherId)
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->orderBy('pickup_date', 'asc')
            ->orderBy('pickup_time', 'asc')
            ->get();

        $unassignedCount = $allTrips->whereNull('driver_id')->count();
        $assignedCount   = $allTrips->whereNotNull('driver_id')->count();

        $availableDrivers = User::where('dispatcher_id', $dispatcherId)
            ->where('role', 'driver')
            ->get();

        return view('content.dispatcher.auto-dispatch', compact(
            'allTrips',
            'unassignedCount',
            'assignedCount',
            'availableDrivers'
        ));
    }

    public function optimize(Request $request)
    {
        $dispatcherId = auth()->id();

        $unassignedTrips = Trip::where('dispatcher_id', $dispatcherId)
            ->whereNull('driver_id')
            ->get();

        $drivers = User::where('dispatcher_id', $dispatcherId)
            ->where('role', 'driver')
            ->get();

        if ($unassignedTrips->isEmpty()) {
            return redirect()->back()->with('info', 'No unassigned trips available to optimize.');
        }

        if ($drivers->isEmpty()) {
            return redirect()->back()->with('error', 'No drivers available for auto-dispatch.');
        }

        // Auto-assign trips to available drivers in round-robin fashion
        $driverCount = $drivers->count();
        $assignedCount = 0;

        foreach ($unassignedTrips as $index => $trip) {
            $assignedDriver = $drivers[$index % $driverCount];
            $trip->driver_id = $assignedDriver->id;
            $trip->save();
            $assignedCount++;
        }

        return redirect()->back()->with('success', "Smart Auto-Dispatch completed! Optimized and assigned {$assignedCount} trips.");
    }
}
