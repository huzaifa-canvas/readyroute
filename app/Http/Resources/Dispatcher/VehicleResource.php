<?php

namespace App\Http\Resources\Dispatcher;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehicleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'name'             => $this->name,
            'make_model_year'  => $this->make_model_year,
            'year'             => $this->year,
            'color'            => $this->color,
            'number_plate'     => $this->number_plate,
            'vin_number'       => $this->vin_number,
            'status'           => $this->status,
            'status_label'     => ucfirst(str_replace('_', ' ', (string) $this->status)),
            'seating_capacity' => $this->seating_capacity,
            'wheelchair_ramp'  => (bool) $this->wheelchair_ramp,
            'image_url'        => $this->image ? asset('storage/' . $this->image) : null,

            // A vehicle carries one driver, so the list can say who has it
            // without a second request.
            'assigned_driver' => $this->whenLoaded('assignedDriver', fn () => $this->assignedDriver ? [
                'id'   => $this->assignedDriver->id,
                'name' => $this->assignedDriver->name,
            ] : null),
            'assigned_driver_id' => $this->assigned_driver_id,
            'is_assigned'        => $this->assigned_driver_id !== null,

            // Only present when the query counted them, which the list does.
            // A card uses these to say whether the vehicle is waiting on
            // something, without a request per row.
            'open_maintenance_count' => $this->whenCounted('openMaintenance'),
            'inspections_count'      => $this->whenCounted('inspections'),

            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
