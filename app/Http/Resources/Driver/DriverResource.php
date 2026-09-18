<?php

namespace App\Http\Resources\Driver;

use App\Support\DriverPreferences;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in driver's own profile. The settings screen describes this as a
 * read-only profile: the driver can change app preferences but not their name,
 * email or assigned vehicle, which the dispatcher owns.
 */
class DriverResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'email'        => $this->email,
            'phone_number' => $this->phone_number,
            'driver_code'  => $this->driver_code,
            'avatar_url'   => $this->avatar_url,
            'role'         => $this->role,

            'presence' => [
                'is_online'    => $this->isCurrentlyOnline(),
                'last_seen_at' => optional($this->last_seen_at)->toIso8601String(),
            ],

            'company' => $this->whenLoaded('dispatcher', fn () => [
                'id'   => $this->dispatcher->id,
                'name' => $this->dispatcher->name,
            ]),

            'vehicle' => $this->whenLoaded(
                'assignedVehicle',
                fn () => $this->assignedVehicle
                    ? new VehicleResource($this->assignedVehicle)
                    : null
            ),

            'preferences' => $this->when(
                $this->relationLoaded('metas'),
                fn () => DriverPreferences::forUser($this->resource)
            ),
        ];
    }
}
