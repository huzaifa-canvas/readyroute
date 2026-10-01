<?php

namespace App\Http\Resources\Driver;

use App\Services\Distance\DistanceEstimate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The full trip record a driver works from. next_action tells the app which
 * button to render, so the lifecycle rules stay on the server rather than
 * being reimplemented in the app.
 */
class TripResource extends JsonResource
{
    public function __construct(
        $resource,
        private readonly ?DistanceEstimate $eta = null,
    ) {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $status = $this->statusEnum();
        $next   = $this->nextDriverStatus();

        return [
            'id'        => $this->id,
            'reference' => $this->reference(),

            'passenger' => [
                'client_id'    => $this->client_id,
                'first_name'   => $this->first_name,
                'last_name'    => $this->last_name,
                'full_name'    => $this->passengerName(),
                'phone_number' => $this->phone_number,
                'member_id'    => $this->member_id,

                // Standing information from the client profile: the address
                // and requirements the dispatcher recorded once, rather than
                // per trip.
                'home_address' => $this->client?->home_address,
                'funding_type' => $this->client?->funding_type,
                'standing_notes' => $this->client?->special_notes,
                'emergency_contact' => $this->client?->emergency_contact_name
                    ? [
                        'name'  => $this->client->emergency_contact_name,
                        'phone' => $this->client->emergency_contact_phone,
                    ]
                    : null,
            ],

            // Dated notes the dispatcher keeps on the client. Read-only here:
            // a driver can never add or change one, and only notes flagged
            // visible are sent.
            'client_notes' => $this->client
                ? $this->client->driverVisibleNotes->map(fn ($note) => [
                    'id'         => $note->id,
                    'body'       => $note->body,
                    'author'     => $note->authorName(),
                    'created_at' => $note->created_at->toIso8601String(),
                ])->values()
                : [],

            'status'       => $status?->value,
            'status_label' => $status?->label(),
            'step_label'   => $status?->stepLabel(),
            'is_finished'  => (bool) $status?->isTerminal(),

            // Cancelled by the dispatcher. The trip is still readable, but
            // next_action below is null and every write is refused, so the app
            // should render it as a record rather than a job.
            'is_cancelled' => $this->isCancelled(),
            'cancelled_at' => optional($this->cancelled_at)->toIso8601String(),

            'confirmation_status' => $this->confirmation_status?->value,
            'confirmation_label'  => $this->confirmation_status?->label(),
            'confirmed_at'        => optional($this->confirmed_at)->toIso8601String(),

            'pickup' => [
                'address' => $this->pickup_address,
                'lat'     => $this->pickup_lat !== null ? (float) $this->pickup_lat : null,
                'lng'     => $this->pickup_lng !== null ? (float) $this->pickup_lng : null,
                'date'    => optional($this->pickup_date)->toDateString(),
                'time'    => $this->pickup_time,
                'at'      => optional($this->scheduledPickupAt())->toIso8601String(),
            ],

            'dropoff' => [
                'address' => $this->dropoff_address,
                'lat'     => $this->dropoff_lat !== null ? (float) $this->dropoff_lat : null,
                'lng'     => $this->dropoff_lng !== null ? (float) $this->dropoff_lng : null,
            ],

            'trip_type'    => $this->trip_type,
            'billing_type' => $this->billing_type,
            'distance'     => $this->distance !== null ? (float) $this->distance : null,
            'notes'        => $this->notes,
            'requirements' => $this->requirementLabels(),

            'vehicle' => $this->whenLoaded(
                'vehicle',
                fn () => $this->vehicle ? new VehicleResource($this->vehicle) : null
            ),

            'timeline' => [
                'en_route_at'        => optional($this->en_route_at)->toIso8601String(),
                'arrived_pickup_at'  => optional($this->arrived_pickup_at)->toIso8601String(),
                'started_at'         => optional($this->started_at)->toIso8601String(),
                'arrived_dropoff_at' => optional($this->arrived_dropoff_at)->toIso8601String(),
                'completed_at'       => optional($this->completed_at)->toIso8601String(),
            ],

            // What the driver can do next, and what the button should say.
            'next_action' => $next ? [
                'status' => $next->value,
                'label'  => $next->actionLabel(),
            ] : null,

            'eta' => $this->eta?->toArray(),
        ];
    }
}
