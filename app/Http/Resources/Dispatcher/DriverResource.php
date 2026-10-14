<?php

namespace App\Http\Resources\Dispatcher;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A driver as the office sees them.
 *
 * The list card prints the name, the vehicle they run and one status word, so
 * those three travel together even though the vehicle lives on another table.
 */
class DriverResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'short_name' => \App\Support\ShortName::of($this->name),
            'email'      => $this->email,
            'phone'      => $this->phone_number,
            'code'       => $this->driver_code,
            'avatar_url' => $this->avatar_url,
            'status'     => $this->status,

            // What the badge on the card says. Presence is reported by the
            // socket server and goes stale on its own, so an app that dies
            // without disconnecting does not stay "on trip" forever.
            'duty'       => $this->dutyState(),
            'duty_label' => $this->dutyLabel(),
            'is_online'  => $this->isCurrentlyOnline(),
            'last_seen_at' => optional($this->last_seen_at)->toIso8601String(),

            'vehicle' => $this->whenLoaded('assignedVehicle', fn () => $this->assignedVehicle ? [
                'id'           => $this->assignedVehicle->id,
                'name'         => $this->assignedVehicle->name,
                'number_plate' => $this->assignedVehicle->number_plate,
                'status'       => $this->assignedVehicle->status,
            ] : null),

            // Which days they work, both as flags the form can tick and as the
            // one short line the list prints.
            'availability' => $this->whenLoaded('metas', fn () => [
                'days'  => $this->availability(),
                'label' => $this->availabilityLabel(),
                'short' => $this->availabilityShort(),
            ]),

            'licence' => $this->whenLoaded('metas', fn () => [
                'number'    => $this->getMeta('driver_license_number'),
                'state'     => $this->getMeta('license_state'),
                'expires_on' => $this->getMeta('license_expiry_date'),
                'cdl_class' => $this->getMeta('cdl_class'),
            ]),

            'internal_notes' => $this->whenLoaded('metas', fn () => $this->getMeta('internal_notes')),

            'location' => $this->last_lat !== null && $this->last_lng !== null ? [
                'lat'  => (float) $this->last_lat,
                'lng'  => (float) $this->last_lng,
                'at'   => optional($this->last_location_at)->toIso8601String(),
                'ago'  => optional($this->last_location_at)->diffForHumans(),
            ] : null,

            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
