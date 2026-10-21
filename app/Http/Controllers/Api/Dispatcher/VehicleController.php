<?php

namespace App\Http\Controllers\Api\Dispatcher;

use App\Http\Resources\Dispatcher\VehicleResource;
use App\Models\Vehicle;
use App\Models\VehicleInspection;
use App\Models\VehicleMaintenanceLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * The fleet.
 *
 * A vehicle carries one driver at a time, and that link is made from the
 * driver's side — a driver is created with the vehicle they will run. Nothing
 * here reassigns one, so a vehicle cannot end up held by two people.
 */
class VehicleController extends BaseDispatcherController
{
    public function index(Request $request): JsonResponse
    {
        $query = $this->vehicles()
            ->with('assignedDriver')
            // The same counts the fleet cards show, so a list row can say
            // whether the vehicle is waiting on something.
            ->withCount(['openMaintenance', 'inspections']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('number_plate', 'like', "%{$search}%");
            });
        }

        $all = $this->vehicles()->get();

        return $this->ok([
            'vehicles' => VehicleResource::collection($query->latest()->get()),
            'summary'  => [
                'total'       => $all->count(),
                'available'   => $all->where('status', 'available')->count(),
                'in_use'      => $all->where('status', 'in_use')->count(),
                'maintenance' => $all->where('status', 'maintenance')->count(),
                'unassigned'  => $all->whereNull('assigned_driver_id')->count(),
            ],
            'statuses' => [
                ['value' => 'available',   'label' => 'Available'],
                ['value' => 'in_use',      'label' => 'In use'],
                ['value' => 'maintenance', 'label' => 'Maintenance'],
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $vehicle = $this->vehicles()->with('assignedDriver')->find($id);

        return $vehicle
            ? $this->ok(new VehicleResource($vehicle))
            : $this->notFound('Vehicle not found.');
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate($this->rules());

        $vehicle = Vehicle::create($this->payload($request) + [
            'dispatcher_id' => $this->companyId(),
            'image' => $request->hasFile('image')
                ? $request->file('image')->store('vehicles', 'public')
                : null,
        ]);

        return $this->created(new VehicleResource($vehicle), 'Vehicle added.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $vehicle = $this->vehicles()->find($id);

        if (! $vehicle) {
            return $this->notFound('Vehicle not found.');
        }

        $request->validate($this->rules());

        $image = $vehicle->image;

        if ($request->hasFile('image')) {
            if ($image && Storage::disk('public')->exists($image)) {
                Storage::disk('public')->delete($image);
            }

            $image = $request->file('image')->store('vehicles', 'public');
        }

        $vehicle->update($this->payload($request) + ['image' => $image]);

        return $this->ok(new VehicleResource($vehicle->fresh()->load('assignedDriver')), 'Vehicle updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        $vehicle = $this->vehicles()->find($id);

        if (! $vehicle) {
            return $this->notFound('Vehicle not found.');
        }

        // A vehicle someone is driving cannot simply vanish: their trips name
        // it, and they would be left unable to start a run or open a check.
        if ($vehicle->assigned_driver_id) {
            return $this->fail(
                'This vehicle is assigned to ' . ($vehicle->assignedDriver?->name ?: 'a driver')
                . '. Move them to another vehicle first.',
                422
            );
        }

        if ($vehicle->image && Storage::disk('public')->exists($vehicle->image)) {
            Storage::disk('public')->delete($vehicle->image);
        }

        $vehicle->delete();

        return $this->ok(null, 'Vehicle deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'name'             => ['required', 'string', 'max:255'],
            'make_model_year'  => ['nullable', 'string', 'max:255'],
            'number_plate'     => ['nullable', 'string', 'max:100'],
            'status'           => ['required', 'in:available,in_use,maintenance'],
            'year'             => ['nullable', 'string', 'max:4'],
            'color'            => ['nullable', 'string', 'max:50'],
            'seating_capacity' => ['nullable', 'integer', 'min:1'],
            'vin_number'       => ['nullable', 'string', 'max:100'],
            'wheelchair_ramp'  => ['nullable', 'boolean'],
            'image'            => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        return [
            'name'             => $request->input('name'),
            'make_model_year'  => $request->input('make_model_year'),
            'year'             => $request->input('year'),
            'color'            => $request->input('color'),
            'number_plate'     => $request->input('number_plate'),
            'status'           => $request->input('status'),
            'seating_capacity' => $request->input('seating_capacity'),
            'wheelchair_ramp'  => $request->boolean('wheelchair_ramp') ? 1 : 0,
            'vin_number'       => $request->input('vin_number'),
        ];
    }

    // ── Maintenance history ──────────────────────────────────────────

    /**
     * What has been done to one vehicle, and what is booked in.
     *
     * Open work comes back separately from history rather than mixed into it:
     * a booking is something somebody still has to act on, and in date order
     * among finished jobs it is exactly what gets missed.
     */
    public function maintenance(int $id): JsonResponse
    {
        $vehicle = $this->vehicles()->find($id);

        if (! $vehicle) {
            return $this->notFound('Vehicle not found.');
        }

        $logs = $vehicle->maintenanceLogs()->with('creator')->get();

        return $this->ok([
            'vehicle' => [
                'id'           => $vehicle->id,
                'name'         => $vehicle->name,
                'number_plate' => $vehicle->number_plate,
                'status'       => $vehicle->status,
            ],
            'open'    => $logs->filter->isOpen()->map(fn ($log) => $this->logRow($log))->values(),
            'history' => $logs->reject->isOpen()->map(fn ($log) => $this->logRow($log))->values(),
            'totals'  => [
                'jobs'      => $logs->count(),
                'open'      => $logs->filter->isOpen()->count(),
                'overdue'   => $logs->filter->isOverdue()->count(),
                'spend'     => round((float) $logs->where('status', 'completed')->sum('cost'), 2),
                'last_done' => optional($logs->where('status', 'completed')->first()?->performed_on)->toDateString(),
            ],
            'types'    => collect(VehicleMaintenanceLog::TYPES)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values(),
            'statuses' => collect(VehicleMaintenanceLog::STATUSES)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values(),
        ]);
    }

    public function storeMaintenance(Request $request, int $id): JsonResponse
    {
        $vehicle = $this->vehicles()->find($id);

        if (! $vehicle) {
            return $this->notFound('Vehicle not found.');
        }

        $data = $request->validate($this->maintenanceRules());

        $log = $vehicle->maintenanceLogs()->create($this->maintenancePayload($data) + [
            'dispatcher_id' => $this->companyId(),
            'created_by'    => $this->user()->id,
        ]);

        $this->syncVehicleStatus($vehicle);

        return $this->created($this->logRow($log->load('creator')), 'Maintenance record added.');
    }

    public function updateMaintenance(Request $request, int $id, int $logId): JsonResponse
    {
        $vehicle = $this->vehicles()->find($id);

        if (! $vehicle) {
            return $this->notFound('Vehicle not found.');
        }

        $log = $vehicle->maintenanceLogs()->find($logId);

        if (! $log) {
            return $this->notFound('Maintenance record not found.');
        }

        $log->update($this->maintenancePayload($request->validate($this->maintenanceRules())));

        $this->syncVehicleStatus($vehicle);

        return $this->ok($this->logRow($log->fresh()->load('creator')), 'Maintenance record updated.');
    }

    public function destroyMaintenance(int $id, int $logId): JsonResponse
    {
        $vehicle = $this->vehicles()->find($id);

        if (! $vehicle) {
            return $this->notFound('Vehicle not found.');
        }

        $log = $vehicle->maintenanceLogs()->find($logId);

        if (! $log) {
            return $this->notFound('Maintenance record not found.');
        }

        $log->delete();

        $this->syncVehicleStatus($vehicle);

        return $this->ok(null, 'Maintenance record removed.');
    }

    // ── Inspections ──────────────────────────────────────────────────

    /**
     * The pre-trip checks drivers have filed against this vehicle.
     *
     * Read-only: the record belongs to the driver who signed it, and a check
     * the office can edit afterwards is worth nothing in an audit. Drivers
     * file one a day rather than one per trip, which is why these are dated
     * rather than tied to a run.
     */
    public function inspections(int $id): JsonResponse
    {
        $vehicle = $this->vehicles()->find($id);

        if (! $vehicle) {
            return $this->notFound('Vehicle not found.');
        }

        $page = VehicleInspection::where('vehicle_id', $vehicle->id)
            ->where('dispatcher_id', $this->companyId())
            ->with(['driver', 'responses.item'])
            ->orderByDesc('inspection_date')
            ->orderByDesc('id')
            ->paginate($this->perPage(20));

        // The company's checklist as it stands, loaded once rather than per
        // check, so each one can be returned in full — including the questions
        // nobody answered.
        $items = \App\Models\InspectionItem::where('dispatcher_id', $this->companyId())
            ->active()
            ->ordered()
            ->get();

        return response()->json([
            'status'  => true,
            'message' => null,
            'data'    => [
                'vehicle' => [
                    'id'           => $vehicle->id,
                    'name'         => $vehicle->name,
                    'number_plate' => $vehicle->number_plate,
                ],
                'inspections' => collect($page->items())->map(function (VehicleInspection $inspection) use ($items) {
                    $checklist = $inspection->checklist($items);
                    $tally     = VehicleInspection::checklistTally($checklist);

                    return [
                        'id'     => $inspection->id,
                        'date'   => optional($inspection->inspection_date)->toDateString(),
                        'status' => $inspection->status?->value,
                        'is_submitted' => $inspection->isSubmitted(),
                        'has_defects'  => (bool) $inspection->has_defects,
                        'submitted_at' => optional($inspection->submitted_at)->toIso8601String(),
                        'driver' => $inspection->driver ? [
                            'id'   => $inspection->driver->id,
                            'name' => $inspection->driver->name,
                            'code' => $inspection->driver->driver_code,
                        ] : null,

                        // Counted from the checklist itself, so the summary and
                        // the rows under it can never disagree.
                        'summary' => $tally,
                        'defect_count' => $tally['defects'],

                        // Every checkpoint, answered or not. A check signed off
                        // with nothing filled in used to come back with an
                        // empty list, which said nothing about what was missed.
                        'checkpoints' => $checklist,
                    ];
                })->values(),
                'totals' => [
                    'total'   => VehicleInspection::where('vehicle_id', $vehicle->id)->count(),
                    'defects' => VehicleInspection::where('vehicle_id', $vehicle->id)->where('has_defects', true)->count(),
                    'last'    => VehicleInspection::where('vehicle_id', $vehicle->id)->max('inspection_date'),
                ],
            ],
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'has_more'     => $page->hasMorePages(),
            ],
            'errors' => null,
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
     * A completed job with no date is dated today rather than left blank —
     * history sorted by a null date is no history at all. A job that is not
     * finished carries no performed_on and no completed_at, whatever was sent.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function maintenancePayload(array $data): array
    {
        if ($data['status'] === 'completed') {
            $data['performed_on'] = ($data['performed_on'] ?? null) ?: today()->toDateString();
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

    /**
     * @return array<string, mixed>
     */
    private function logRow(VehicleMaintenanceLog $log): array
    {
        return [
            'id'     => $log->id,
            'title'  => $log->title,
            'description' => $log->description,
            'type'        => $log->type,
            'type_label'  => $log->typeLabel(),
            'status'       => $log->status,
            'status_label' => $log->statusLabel(),
            'is_open'      => $log->isOpen(),
            'is_overdue'   => $log->isOverdue(),
            'vendor'   => $log->vendor,
            'cost'     => $log->cost !== null ? (float) $log->cost : null,
            'odometer' => $log->odometer,
            'performed_on' => optional($log->performed_on)->toDateString(),
            'due_on'       => optional($log->due_on)->toDateString(),
            'completed_at' => optional($log->completed_at)->toIso8601String(),
            'on_date'      => optional($log->onDate())->toDateString(),
            // Set when the job came from a driver's inspection defect, so the
            // two sides of the same fault stay connected.
            'inspection_id' => $log->vehicle_inspection_id,
            'recorded_by'   => $log->creator?->name,
            'created_at'    => optional($log->created_at)->toIso8601String(),
        ];
    }
}
