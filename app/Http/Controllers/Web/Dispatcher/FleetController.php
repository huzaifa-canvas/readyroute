<?php

namespace App\Http\Controllers\Web\Dispatcher;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FleetController extends Controller
{
    public function index(Request $request)
    {
        $dispatcher = auth()->user();

        $query = Vehicle::where('dispatcher_id', $dispatcher->id);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Search by name or plate
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('number_plate', 'like', "%{$search}%");
            });
        }

        $vehicles = $query->latest()->paginate(15)->withQueryString();
        
        $totalVehicles = Vehicle::where('dispatcher_id', $dispatcher->id)->count();

        return view('content.dispatcher.fleet.list', compact('vehicles', 'totalVehicles'));
    }

    public function create()
    {
        return view('content.dispatcher.fleet.form');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'make_model_year' => 'nullable|string|max:255',
            'number_plate' => 'nullable|string|max:100',
            'status' => 'required|in:available,in_use,maintenance',
            'year' => 'nullable|string|max:4',
            'color' => 'nullable|string|max:50',
            'seating_capacity' => 'nullable|integer|min:1',
            'vin_number' => 'nullable|string|max:100',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('vehicles', 'public');
        }

        Vehicle::create([
            'dispatcher_id' => auth()->id(),
            'name' => $request->name,
            'image' => $imagePath,
            'make_model_year' => $request->make_model_year,
            'year' => $request->year,
            'color' => $request->color,
            'number_plate' => $request->number_plate,
            'status' => $request->status,
            'seating_capacity' => $request->seating_capacity,
            'wheelchair_ramp' => $request->has('wheelchair_ramp') ? 1 : 0,
            'vin_number' => $request->vin_number,
        ]);

        return redirect()->route('dispatcher.fleet.index')->with('success', 'Vehicle added successfully.');
    }

    public function edit($id)
    {
        $vehicle = Vehicle::where('dispatcher_id', auth()->id())->findOrFail($id);
        
        return view('content.dispatcher.fleet.form', compact('vehicle'));
    }

    public function update(Request $request, $id)
    {
        $vehicle = Vehicle::where('dispatcher_id', auth()->id())->findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'make_model_year' => 'nullable|string|max:255',
            'number_plate' => 'nullable|string|max:100',
            'status' => 'required|in:available,in_use,maintenance',
            'year' => 'nullable|string|max:4',
            'color' => 'nullable|string|max:50',
            'seating_capacity' => 'nullable|integer|min:1',
            'vin_number' => 'nullable|string|max:100',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $imagePath = $vehicle->image;
        if ($request->hasFile('image')) {
            if ($imagePath && Storage::disk('public')->exists($imagePath)) {
                Storage::disk('public')->delete($imagePath);
            }
            $imagePath = $request->file('image')->store('vehicles', 'public');
        }

        $vehicle->update([
            'name' => $request->name,
            'image' => $imagePath,
            'make_model_year' => $request->make_model_year,
            'year' => $request->year,
            'color' => $request->color,
            'number_plate' => $request->number_plate,
            'status' => $request->status,
            'seating_capacity' => $request->seating_capacity,
            'wheelchair_ramp' => $request->has('wheelchair_ramp') ? 1 : 0,
            'vin_number' => $request->vin_number,
        ]);

        return redirect()->route('dispatcher.fleet.index')->with('success', 'Vehicle updated successfully.');
    }

    public function destroy($id)
    {
        $vehicle = Vehicle::where('dispatcher_id', auth()->id())->findOrFail($id);
        
        if ($vehicle->image && Storage::disk('public')->exists($vehicle->image)) {
            Storage::disk('public')->delete($vehicle->image);
        }
        
        $vehicle->delete();

        return redirect()->back()->with('success', 'Vehicle deleted successfully.');
    }
}
