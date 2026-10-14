<?php

namespace App\Http\Controllers\Api\Dispatcher;

use App\Http\Resources\Dispatcher\VehicleResource;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
        $query = $this->vehicles()->with('assignedDriver');

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
}
