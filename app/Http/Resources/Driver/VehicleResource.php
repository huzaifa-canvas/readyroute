<?php

namespace App\Http\Resources\Driver;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The vehicle as a driver sees it — enough to confirm they are inspecting and
 * driving the right van, not the full fleet record.
 */
class VehicleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'code'             => $this->vehicle_code,
            'name'             => $this->name,
            'label'            => $this->displayLabel(),
            'make_model_year'  => $this->make_model_year,
            'color'            => $this->color,
            'number_plate'     => $this->number_plate,
            'seating_capacity' => $this->seating_capacity,
            'wheelchair_ramp'  => (bool) $this->wheelchair_ramp,
            'status'           => $this->status,
            'image_url'        => $this->image_url,
        ];
    }
}
