<?php

namespace App\Http\Controllers\Web\Dispatcher;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Models\Client;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\Request;

class TripController extends Controller
{
    public function index(Request $request)
    {
        $dispatcher = auth()->user();

        $query = Trip::with(['client', 'driver', 'vehicle'])
            ->where('dispatcher_id', $dispatcher->id);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('pickup_address', 'like', "%{$search}%")
                  ->orWhere('dropoff_address', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $trips = $query->latest()->paginate(15)->withQueryString();

        return view('content.dispatcher.trip.list', compact('trips'));
    }

    public function create()
    {
        $dispatcherId = auth()->id();

        $clients = Client::where('dispatcher_id', $dispatcherId)->get();
        $drivers = User::where('dispatcher_id', $dispatcherId)->where('role', 'driver')->get();
        $vehicles = Vehicle::where('dispatcher_id', $dispatcherId)->get();

        return view('content.dispatcher.trip.create', compact('clients', 'drivers', 'vehicles'));
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'client_id' => 'nullable|exists:clients,id',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone_number' => 'nullable|string|max:20',
            'member_id' => 'nullable|string|max:100',
            
            'pickup_date' => 'required|date',
            'pickup_time' => 'required',
            'pickup_address' => 'required|string|max:255',
            'pickup_lat' => 'nullable|numeric',
            'pickup_lng' => 'nullable|numeric',
            'dropoff_address' => 'required|string|max:255',
            'dropoff_lat' => 'nullable|numeric',
            'dropoff_lng' => 'nullable|numeric',
            'distance' => 'nullable|numeric',
            'trip_type' => 'required|in:one_way,round_trip,recurring',
            'notes' => 'nullable|string',
            
            'driver_id' => 'nullable|exists:users,id',
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'billing_type' => 'nullable|string|max:100',
        ]);

        $validatedData['req_wheelchair'] = $request->has('req_wheelchair') ? 1 : 0;
        $validatedData['req_stretcher'] = $request->has('req_stretcher') ? 1 : 0;
        $validatedData['req_o2_tank'] = $request->has('req_o2_tank') ? 1 : 0;
        $validatedData['req_bariatric'] = $request->has('req_bariatric') ? 1 : 0;
        $validatedData['req_no_steps'] = $request->has('req_no_steps') ? 1 : 0;
        
        if (!empty($validatedData['pickup_time'])) {
            $validatedData['pickup_time'] = \Carbon\Carbon::parse($validatedData['pickup_time'])->format('H:i:s');
        }

        $validatedData['dispatcher_id'] = auth()->id();
        $validatedData['status'] = 'scheduled';

        Trip::create($validatedData);

        return redirect()->route('dispatcher.trip.create')->with('success', 'New Trip created successfully!');
    }

    public function calendar()
    {
        $dispatcherId = auth()->id();
        $drivers = User::where('dispatcher_id', $dispatcherId)->where('role', 'driver')->get();
        return view('content.dispatcher.trip.calendar', compact('drivers'));
    }

    public function events()
    {
        $dispatcher = auth()->user();

        $trips = Trip::with(['driver', 'vehicle', 'client'])
            ->where('dispatcher_id', $dispatcher->id)
            ->get();

        // Vuexy theme label color palette (soft badges)
        $vuexyColors = [
            ['name' => 'primary',   'hex' => '#696cff'],
            ['name' => 'success',   'hex' => '#71dd37'],
            ['name' => 'info',      'hex' => '#03c3ec'],
            ['name' => 'warning',   'hex' => '#ffab00'],
            ['name' => 'danger',    'hex' => '#ff3e1d'],
        ];

        // Build a driver-to-color map
        $driverColorMap = [];
        $colorIndex = 0;
        foreach ($trips as $trip) {
            if ($trip->driver_id && !isset($driverColorMap[$trip->driver_id])) {
                $driverColorMap[$trip->driver_id] = $vuexyColors[$colorIndex % count($vuexyColors)];
                $colorIndex++;
            }
        }

        $events = $trips->map(function ($trip) use ($driverColorMap) {
            // Combine pickup_date and pickup_time for start datetime
            $timeString = $trip->pickup_time ? $trip->pickup_time : '09:00:00';
            $startDateTime = $trip->pickup_date ? $trip->pickup_date->format('Y-m-d') . 'T' . $timeString : now()->format('Y-m-d') . 'T' . $timeString;

            // Color: per-driver if assigned, else secondary/unassigned
            $colorObj = ($trip->driver_id && isset($driverColorMap[$trip->driver_id]))
                ? $driverColorMap[$trip->driver_id]
                : ['name' => 'secondary', 'hex' => '#8a8d93'];

            $passengerName = trim($trip->first_name . ' ' . $trip->last_name);

            return [
                'id'            => $trip->id,
                'title'         => $passengerName,
                'start'         => $startDateTime,
                'extendedProps' => [
                    'calendar'   => $colorObj['name'],
                    'color'      => $colorObj['hex'],
                    'passenger'  => $passengerName,
                    'phone'      => $trip->phone_number ?: 'N/A',
                    'pickup'     => $trip->pickup_address,
                    'dropoff'    => $trip->dropoff_address,
                    'driver'     => $trip->driver ? $trip->driver->name : 'Unassigned',
                    'driver_id'  => $trip->driver_id ?: 'unassigned',
                    'vehicle'    => $trip->vehicle ? $trip->vehicle->name : 'Unassigned',
                    'status'     => $trip->status,
                    'type'       => str_replace('_', ' ', $trip->trip_type),
                    'distance'   => $trip->distance ? $trip->distance . ' Miles' : 'N/A',
                    'notes'      => $trip->notes ?: 'None',
                ]
            ];
        });

        return response()->json($events);
    }

    public function show($id)
    {
        $trip = Trip::with(['client', 'driver', 'vehicle'])
            ->where('dispatcher_id', auth()->id())
            ->findOrFail($id);

        return view('content.dispatcher.trip.show', compact('trip'));
    }

    public function edit($id)
    {
        $dispatcherId = auth()->id();
        $trip = Trip::where('dispatcher_id', $dispatcherId)->findOrFail($id);

        $clients = Client::where('dispatcher_id', $dispatcherId)->get();
        $drivers = User::where('dispatcher_id', $dispatcherId)->where('role', 'driver')->get();
        $vehicles = Vehicle::where('dispatcher_id', $dispatcherId)->get();

        return view('content.dispatcher.trip.edit', compact('trip', 'clients', 'drivers', 'vehicles'));
    }

    public function update(Request $request, $id)
    {
        $dispatcherId = auth()->id();
        $trip = Trip::where('dispatcher_id', $dispatcherId)->findOrFail($id);

        $validatedData = $request->validate([
            'client_id' => 'nullable|exists:clients,id',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone_number' => 'nullable|string|max:20',
            'member_id' => 'nullable|string|max:100',
            
            'pickup_date' => 'required|date',
            'pickup_time' => 'required',
            'pickup_address' => 'required|string|max:255',
            'pickup_lat' => 'nullable|numeric',
            'pickup_lng' => 'nullable|numeric',
            'dropoff_address' => 'required|string|max:255',
            'dropoff_lat' => 'nullable|numeric',
            'dropoff_lng' => 'nullable|numeric',
            'distance' => 'nullable|numeric',
            'trip_type' => 'required|in:one_way,round_trip,recurring',
            'notes' => 'nullable|string',
            'status' => 'required|in:scheduled,in_progress,completed,cancelled',
            
            'driver_id' => 'nullable|exists:users,id',
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'billing_type' => 'nullable|string|max:100',
        ]);

        $validatedData['req_wheelchair'] = $request->has('req_wheelchair') ? 1 : 0;
        $validatedData['req_stretcher'] = $request->has('req_stretcher') ? 1 : 0;
        $validatedData['req_o2_tank'] = $request->has('req_o2_tank') ? 1 : 0;
        $validatedData['req_bariatric'] = $request->has('req_bariatric') ? 1 : 0;
        $validatedData['req_no_steps'] = $request->has('req_no_steps') ? 1 : 0;
        
        if (!empty($validatedData['pickup_time'])) {
            $validatedData['pickup_time'] = \Carbon\Carbon::parse($validatedData['pickup_time'])->format('H:i:s');
        }

        $trip->update($validatedData);

        return redirect()->route('dispatcher.trip.details', $trip->id)->with('success', 'Trip updated successfully!');
    }

    public function destroy($id)
    {
        $trip = Trip::where('dispatcher_id', auth()->id())->findOrFail($id);
        $trip->delete();

        return redirect()->route('dispatcher.trip.list')->with('success', 'Trip deleted successfully!');
    }
}
