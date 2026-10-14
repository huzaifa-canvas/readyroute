<?php

namespace App\Http\Resources\Dispatcher;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A trip as the board shows it: the card on the dashboard, the trip list and
 * the search results are all this shape, because on the phone they are the
 * same card.
 */
class TripListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->statusEnum();

        return [
            'id'        => $this->id,
            'reference' => $this->reference(),

            'passenger' => [
                'name'  => $this->passengerName(),
                'short' => $this->passengerShortName(),
                'phone' => $this->phone_number,
            ],
            'client_id' => $this->client_id,

            'status'       => $status?->value,
            'status_label' => $status?->label(),
            'status_class' => $status?->badgeClass(),
            'is_cancelled' => $this->isCancelled(),
            'is_finished'  => (bool) $status?->isTerminal(),

            'pickup' => [
                'address' => $this->pickup_address,
                'date'    => optional($this->pickup_date)->toDateString(),
                'time'    => $this->pickup_time,
                'time_label' => $this->pickup_time
                    ? \Illuminate\Support\Carbon::parse($this->pickup_time)->format('g:i A')
                    : null,
                'at'      => optional($this->scheduledPickupAt())->toIso8601String(),
            ],

            'dropoff' => [
                'address' => $this->dropoff_address,
            ],

            // The card shows the driver's short name beside a vehicle icon,
            // so both travel together even though only one is printed.
            'driver' => $this->whenLoaded('driver', fn () => $this->driver ? [
                'id'    => $this->driver->id,
                'name'  => $this->driver->name,
                'short' => \App\Support\ShortName::of($this->driver->name),
            ] : null),

            'vehicle' => $this->whenLoaded('vehicle', fn () => $this->vehicle ? [
                'id'   => $this->vehicle->id,
                'name' => $this->vehicle->name,
            ] : null),

            'is_assigned' => $this->driver_id !== null,
            'trip_type'   => $this->trip_type,
            'requirements' => $this->requirementLabels(),
        ];
    }
}
