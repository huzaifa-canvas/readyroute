<?php

namespace App\Http\Controllers\Web\Dispatcher;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class DriverController extends Controller
{
    public function index(Request $request)
    {
        $dispatcher = auth()->user();

        // Get drivers owned ONLY by this logged-in dispatcher
        $drivers = User::where('role', 'driver')
            ->where('dispatcher_id', $dispatcher->id)
            ->with('metas')
            ->latest()
            ->paginate(15);

        $totalDrivers = User::where('role', 'driver')
            ->where('dispatcher_id', $dispatcher->id)
            ->count();

        return view('content.dispatcher.drivers.list', compact('drivers', 'totalDrivers'));
    }

    public function create()
    {
        return view('content.dispatcher.drivers.create');
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
            'assigned_vehicle' => 'nullable|string|max:100',
            'internal_notes' => 'nullable|string',
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
            'dispatcher_id' => auth()->id(),
            'phone_number' => $request->phone_number,
            'profile_image' => $profileImagePath,
        ]);

        // Store extra meta data
        $driver->syncMetas([
            'driver_license_number' => $request->driver_license_number,
            'license_state' => $request->license_state,
            'license_expiry_date' => $request->license_expiry_date,
            'cdl_class' => $request->cdl_class,
            'assigned_vehicle' => $request->assigned_vehicle,
            'availability_mon_fri' => $request->has('availability_mon_fri') ? '1' : '0',
            'availability_sat' => $request->has('availability_sat') ? '1' : '0',
            'availability_sun' => $request->has('availability_sun') ? '1' : '0',
            'availability_on_call' => $request->has('availability_on_call') ? '1' : '0',
            'internal_notes' => $request->internal_notes,
        ]);

        return redirect()->route('dispatcher.driver.list')->with('success', 'Driver added successfully.');
    }

    public function edit($id)
    {
        // Enforce ownership: only driver belonging to this dispatcher
        $driver = User::where('role', 'driver')
            ->where('dispatcher_id', auth()->id())
            ->findOrFail($id);

        return view('content.dispatcher.drivers.edit', compact('driver'));
    }

    public function update(Request $request, $id)
    {
        // Enforce ownership
        $driver = User::where('role', 'driver')
            ->where('dispatcher_id', auth()->id())
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
            'assigned_vehicle' => 'nullable|string|max:100',
            'internal_notes' => 'nullable|string',
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
            'assigned_vehicle' => $request->assigned_vehicle,
            'availability_mon_fri' => $request->has('availability_mon_fri') ? '1' : '0',
            'availability_sat' => $request->has('availability_sat') ? '1' : '0',
            'availability_sun' => $request->has('availability_sun') ? '1' : '0',
            'availability_on_call' => $request->has('availability_on_call') ? '1' : '0',
            'internal_notes' => $request->internal_notes,
        ]);

        return redirect()->route('dispatcher.driver.list')->with('success', 'Driver updated successfully.');
    }

    public function destroy($id)
    {
        // Enforce ownership
        $driver = User::where('role', 'driver')
            ->where('dispatcher_id', auth()->id())
            ->findOrFail($id);

        if ($driver->profile_image) {
            Storage::disk('public')->delete($driver->profile_image);
        }

        $driver->delete();

        return redirect()->back()->with('success', 'Driver deleted successfully.');
    }
}
