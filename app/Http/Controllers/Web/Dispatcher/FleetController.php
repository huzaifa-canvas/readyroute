<?php

namespace App\Http\Controllers\Web\Dispatcher;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\VehicleInspection;
use App\Models\VehicleMaintenanceLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class FleetController extends Controller
{
    public function index(Request $request)
    {
        $dispatcher = auth()->user();

        $query = Vehicle::where('dispatcher_id', $dispatcher->companyId())
            // Counted rather than queried per card: every card asks whether
            // the vehicle is waiting on something and when it was last seen to.
            ->withCount(['openMaintenance', 'inspections']);

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
        
        $totalVehicles = Vehicle::where('dispatcher_id', $dispatcher->companyId())->count();

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
            'dispatcher_id' => auth()->user()->companyId(),
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
        $vehicle = Vehicle::where('dispatcher_id', auth()->user()->companyId())->findOrFail($id);
        
        return view('content.dispatcher.fleet.form', compact('vehicle'));
    }

    public function update(Request $request, $id)
    {
        $vehicle = Vehicle::where('dispatcher_id', auth()->user()->companyId())->findOrFail($id);

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
        $vehicle = Vehicle::where('dispatcher_id', auth()->user()->companyId())->findOrFail($id);
        
        if ($vehicle->image && Storage::disk('public')->exists($vehicle->image)) {
            Storage::disk('public')->delete($vehicle->image);
        }
        
        $vehicle->delete();

        return redirect()->back()->with('success', 'Vehicle deleted successfully.');
    }

    // ── Maintenance history ──────────────────────────────────────────

    /**
     * Everything that has been done to one vehicle, and what is booked in.
     *
     * Open work is listed above history rather than mixed into it: a booking
     * is something somebody still has to act on, and burying it in date order
     * among finished jobs is how it gets missed.
     */
    public function maintenance($id)
    {
        $vehicle = $this->findVehicle($id);

        $logs = $vehicle->maintenanceLogs()->with('creator')->get();

        return view('content.dispatcher.fleet.maintenance', [
            'vehicle'  => $vehicle,
            'open'     => $logs->filter->isOpen()->values(),
            'history'  => $logs->reject->isOpen()->values(),
            'totals'   => [
                'jobs'      => $logs->count(),
                'open'      => $logs->filter->isOpen()->count(),
                'overdue'   => $logs->filter->isOverdue()->count(),
                'spend'     => (float) $logs->where('status', 'completed')->sum('cost'),
                'last_done' => $logs->where('status', 'completed')->first()?->performed_on,
            ],
            'types'    => VehicleMaintenanceLog::TYPES,
            'statuses' => VehicleMaintenanceLog::STATUSES,
        ]);
    }

    public function storeMaintenance(Request $request, $id)
    {
        $vehicle = $this->findVehicle($id);

        $data = $request->validate($this->maintenanceRules());

        $vehicle->maintenanceLogs()->create($this->maintenancePayload($data) + [
            'dispatcher_id' => $vehicle->dispatcher_id,
            'created_by'    => auth()->id(),
        ]);

        $this->syncVehicleStatus($vehicle);

        return back()->with('success', 'Maintenance record added.');
    }

    public function updateMaintenance(Request $request, $id, $logId)
    {
        $vehicle = $this->findVehicle($id);
        $log     = $vehicle->maintenanceLogs()->findOrFail($logId);

        $log->update($this->maintenancePayload($request->validate($this->maintenanceRules())));

        $this->syncVehicleStatus($vehicle);

        return back()->with('success', 'Maintenance record updated.');
    }

    public function destroyMaintenance($id, $logId)
    {
        $vehicle = $this->findVehicle($id);

        $vehicle->maintenanceLogs()->findOrFail($logId)->delete();

        $this->syncVehicleStatus($vehicle);

        return back()->with('success', 'Maintenance record removed.');
    }

    // ── Inspections ──────────────────────────────────────────────────

    /**
     * The pre-trip checks drivers have filed against this vehicle.
     *
     * Read-only here: the record belongs to the driver who signed it, and a
     * check the office can edit afterwards is worth nothing in an audit.
     */
    public function inspections($id)
    {
        $vehicle = $this->findVehicle($id);

        $inspections = VehicleInspection::where('vehicle_id', $vehicle->id)
            ->where('dispatcher_id', $vehicle->dispatcher_id)
            ->with(['driver', 'responses.item'])
            ->orderByDesc('inspection_date')
            ->orderByDesc('id')
            ->paginate(20);

        // The company's checklist as it stands, loaded once rather than per
        // check. Each check is then shown in full — including the questions
        // nobody answered, which is the half that was missing.
        $items = \App\Models\InspectionItem::where('dispatcher_id', $vehicle->dispatcher_id)
            ->active()
            ->ordered()
            ->get();

        return view('content.dispatcher.fleet.inspections', [
            'items'   => $items,
            'vehicle'     => $vehicle,
            'inspections' => $inspections,
            'totals'      => [
                'total'    => VehicleInspection::where('vehicle_id', $vehicle->id)->count(),
                'defects'  => VehicleInspection::where('vehicle_id', $vehicle->id)->where('has_defects', true)->count(),
                'last'     => VehicleInspection::where('vehicle_id', $vehicle->id)->max('inspection_date'),
            ],
        ]);
    }

    // ── Shared pieces ────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function maintenanceRules(): array
    {
        return [
            'type'   => ['required', Rule::in(array_keys(VehicleMaintenanceLog::TYPES))],
            'status' => ['required', Rule::in(array_keys(VehicleMaintenanceLog::STATUSES))],
            'title'  => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'vendor'   => ['nullable', 'string', 'max:160'],
            'cost'     => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'odometer' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'performed_on' => ['nullable', 'date'],
            'due_on'       => ['nullable', 'date'],
        ];
    }

    /**
     * Keep the dates honest against the status.
     *
     * A completed job without a date is dated today rather than left blank —
     * history sorted by a null date is no history at all. A job that is not
     * finished has no performed_on and no completed_at, whatever was posted.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function maintenancePayload(array $data): array
    {
        if ($data['status'] === 'completed') {
            $data['performed_on'] = $data['performed_on'] ?: today()->toDateString();
            $data['completed_at'] = now();
        } else {
            $data['performed_on'] = null;
            $data['completed_at'] = null;
        }

        return $data;
    }

    /**
     * A vehicle with a job under way is off the road.
     *
     * Derived rather than typed, so the status cannot say "available" while
     * the garage still has it. Only these two states are touched: a vehicle
     * already in use on a trip is left alone.
     */
    private function syncVehicleStatus(Vehicle $vehicle): void
    {
        $hasOpen = $vehicle->openMaintenance()->exists();

        if ($hasOpen && $vehicle->status !== 'maintenance') {
            $vehicle->update(['status' => 'maintenance']);
        } elseif (! $hasOpen && $vehicle->status === 'maintenance') {
            $vehicle->update(['status' => 'available']);
        }
    }

    private function findVehicle($id): Vehicle
    {
        return Vehicle::where('dispatcher_id', auth()->user()->companyId())->findOrFail($id);
    }
}
