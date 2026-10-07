<?php

namespace App\Http\Controllers\Web\Dispatcher;

use App\Http\Controllers\Controller;
use App\Models\DriverDocument;
use App\Models\Trip;
use App\Models\TripIncident;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class DriverController extends Controller
{
    public function index(Request $request)
    {
        $dispatcher = auth()->user();

        // Get drivers owned ONLY by this logged-in dispatcher
        $drivers = User::where('role', 'driver')
            ->where('dispatcher_id', $dispatcher->companyId())
            // Eager loaded, or the list would run a query per driver for a
            // column that is on every row.
            ->with(['metas', 'assignedVehicle'])
            ->latest()
            ->paginate(15);

        $totalDrivers = User::where('role', 'driver')
            ->where('dispatcher_id', $dispatcher->companyId())
            ->count();

        return view('content.dispatcher.drivers.list', compact('drivers', 'totalDrivers'));
    }

    /**
     * One driver, in full: where they are, what they are carrying, how they
     * have been performing and what paperwork is due.
     */
    public function show($id)
    {
        $companyId = auth()->user()->companyId();

        $driver = User::driversOf($companyId)
            ->with(['metas', 'assignedVehicle'])
            ->findOrFail($id);

        $activeTrip = Trip::forDriver($driver->id)->active()->with('client')->first();

        $todaysTrips = Trip::forDriver($driver->id)
            ->whereDate('pickup_date', today())
            ->orderBy('pickup_time')
            ->get();

        $recentTrips = Trip::forDriver($driver->id)
            ->with('client')
            ->latest('pickup_date')
            ->limit(10)
            ->get();

        // Lifetime figures, so the page says something even for a driver with
        // a quiet month.
        $completed = Trip::forDriver($driver->id)->where('status', \App\Enums\TripStatus::Completed->value);

        $stats = [
            'total_trips'    => Trip::forDriver($driver->id)->count(),
            'completed'      => (clone $completed)->count(),
            'miles'          => round((float) (clone $completed)->sum('actual_distance'), 1),
            'today'          => $todaysTrips->count(),
            'open_incidents' => TripIncident::where('driver_id', $driver->id)->open()->count(),
        ];

        $scored = (clone $completed)->whereNotNull('was_on_time')->count();
        $stats['on_time_rate'] = $scored > 0
            ? round((clone $completed)->where('was_on_time', true)->count() / $scored * 100)
            : null;

        $documents = DriverDocument::where('driver_id', $driver->id)
            ->orderByRaw('expires_on IS NULL')
            ->orderBy('expires_on')
            ->get();

        $incidents = TripIncident::where('driver_id', $driver->id)
            ->latest()
            ->limit(5)
            ->get();

        // The last few pings, newest first, for the position panel.
        $trail = $driver->locations()->latest()->limit(10)->get();

        return view('content.dispatcher.drivers.show', compact(
            'driver', 'activeTrip', 'todaysTrips', 'recentTrips', 'stats', 'documents', 'incidents', 'trail'
        ));
    }

    public function create()
    {
        return view('content.dispatcher.drivers.create', [
            'vehicles' => $this->assignableVehicles(),
        ]);
    }

    /**
     * Vehicles this company can put a driver in.
     *
     * A vehicle carries one driver, so anything already taken is left out —
     * except the one the driver being edited already has, which must stay in
     * the list or saving the form would look like it was unassigning them.
     */
    private function assignableVehicles(?int $keepForDriverId = null)
    {
        return Vehicle::where('dispatcher_id', auth()->user()->companyId())
            ->where(function ($query) use ($keepForDriverId) {
                $query->whereNull('assigned_driver_id');

                if ($keepForDriverId) {
                    $query->orWhere('assigned_driver_id', $keepForDriverId);
                }
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * Hand a vehicle to a driver, taking it off whoever had it.
     *
     * Done in one transaction: a half-finished swap would leave either two
     * drivers holding one vehicle or a driver holding none.
     */
    private function assignVehicle(User $driver, int $vehicleId): void
    {
        DB::transaction(function () use ($driver, $vehicleId) {
            Vehicle::where('assigned_driver_id', $driver->id)
                ->where('id', '!=', $vehicleId)
                ->update(['assigned_driver_id' => null]);

            Vehicle::where('id', $vehicleId)
                ->where('dispatcher_id', auth()->user()->companyId())
                ->update(['assigned_driver_id' => $driver->id]);
        });
    }

    /**
     * The rule a vehicle choice has to pass: it must belong to this company
     * and must not already be someone else's.
     */
    private function vehicleRules(?int $forDriverId = null): array
    {
        return [
            'required',
            Rule::exists('vehicles', 'id')
                ->where('dispatcher_id', auth()->user()->companyId())
                ->where(function ($query) use ($forDriverId) {
                    $query->whereNull('assigned_driver_id');

                    if ($forDriverId) {
                        $query->orWhere('assigned_driver_id', $forDriverId);
                    }
                }),
        ];
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'phone_number' => 'nullable|string|max:50',
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'driver_license_number' => 'nullable|string|max:100',
            'license_state' => 'nullable|string|max:50',
            'license_expiry_date' => 'nullable|date',
            'cdl_class' => 'nullable|string|max:100',
            'internal_notes' => 'nullable|string',
            // A driver without a vehicle cannot run a trip or open a pre-trip
            // inspection, so one is picked here rather than later.
            'vehicle_id' => $this->vehicleRules(),
        ], [
            'vehicle_id.required' => 'Choose the vehicle this driver will run.',
            'vehicle_id.exists'   => 'That vehicle is not available — it belongs to another driver.',
        ]);

        $profileImagePath = null;
        if ($request->hasFile('profile_image')) {
            $profileImagePath = $request->file('profile_image')->store('profile_images', 'public');
        }

        // Create driver user linked to this dispatcher
        $driver = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'driver',
            'dispatcher_id' => auth()->user()->companyId(),
            'phone_number' => $request->phone_number,
            'profile_image' => $profileImagePath,
        ]);

        // Store extra meta data
        $driver->syncMetas([
            'driver_license_number' => $request->driver_license_number,
            'license_state' => $request->license_state,
            'license_expiry_date' => $request->license_expiry_date,
            'cdl_class' => $request->cdl_class,
            'availability_mon_fri' => $request->has('availability_mon_fri') ? '1' : '0',
            'availability_sat' => $request->has('availability_sat') ? '1' : '0',
            'availability_sun' => $request->has('availability_sun') ? '1' : '0',
            'availability_on_call' => $request->has('availability_on_call') ? '1' : '0',
            'internal_notes' => $request->internal_notes,
        ]);

        $this->assignVehicle($driver, (int) $request->vehicle_id);

        return redirect()->route('dispatcher.driver.list')
            ->with('success', 'Driver added and assigned ' . $driver->fresh()->assignedVehicle?->name . '.');
    }

    public function edit($id)
    {
        // Enforce ownership: only driver belonging to this dispatcher
        $driver = User::where('role', 'driver')
            ->where('dispatcher_id', auth()->user()->companyId())
            ->with('metas')
            ->findOrFail($id);

        return view('content.dispatcher.drivers.edit', [
            'driver'   => $driver,
            'vehicles' => $this->assignableVehicles($driver->id),
        ]);
    }

    public function update(Request $request, $id)
    {
        // Enforce ownership
        $driver = User::where('role', 'driver')
            ->where('dispatcher_id', auth()->user()->companyId())
            ->findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $driver->id,
            'phone_number' => 'nullable|string|max:50',
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'driver_license_number' => 'nullable|string|max:100',
            'license_state' => 'nullable|string|max:50',
            'license_expiry_date' => 'nullable|date',
            'cdl_class' => 'nullable|string|max:100',
            'internal_notes' => 'nullable|string',
            'vehicle_id' => $this->vehicleRules($driver->id),
        ], [
            'vehicle_id.required' => 'Choose the vehicle this driver will run.',
            'vehicle_id.exists'   => 'That vehicle is not available - it belongs to another driver.',
        ]);

        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'phone_number' => $request->phone_number,
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        if ($request->hasFile('profile_image')) {
            if ($driver->profile_image) {
                Storage::disk('public')->delete($driver->profile_image);
            }
            $data['profile_image'] = $request->file('profile_image')->store('profile_images', 'public');
        }

        $driver->update($data);

        // Update meta data
        $driver->syncMetas([
            'driver_license_number' => $request->driver_license_number,
            'license_state' => $request->license_state,
            'license_expiry_date' => $request->license_expiry_date,
            'cdl_class' => $request->cdl_class,
            'availability_mon_fri' => $request->has('availability_mon_fri') ? '1' : '0',
            'availability_sat' => $request->has('availability_sat') ? '1' : '0',
            'availability_sun' => $request->has('availability_sun') ? '1' : '0',
            'availability_on_call' => $request->has('availability_on_call') ? '1' : '0',
            'internal_notes' => $request->internal_notes,
        ]);

        $this->assignVehicle($driver, (int) $request->vehicle_id);

        return redirect()->route('dispatcher.driver.list')->with('success', 'Driver updated successfully.');
    }

    public function destroy($id)
    {
        // Enforce ownership
        $driver = User::where('role', 'driver')
            ->where('dispatcher_id', auth()->user()->companyId())
            ->findOrFail($id);

        if ($driver->profile_image) {
            Storage::disk('public')->delete($driver->profile_image);
        }

        // Put the vehicle back in the pool, or it stays held by a driver who
        // is gone and can never be given to anyone else.
        Vehicle::where('assigned_driver_id', $driver->id)->update(['assigned_driver_id' => null]);

        $driver->delete();

        return redirect()->back()->with('success', 'Driver deleted successfully.');
    }
}
