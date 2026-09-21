<?php

namespace App\Http\Resources\Driver;

use App\Services\Distance\DistanceEstimate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A trip as it appears on a dashboard or list card: enough to recognise the
 * run at a glance, without the patient detail that only the full trip screen
 * needs.
 */
class TripListResource extends JsonResource
{
    public function __construct(
        $resource,
        private readonly bool $isNext = false,
        private readonly ?DistanceEstimate $eta = null,
    ) {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $status = $this->statusEnum();

        return [
            'id'        => $this->id,
            'reference' => $this->reference(),

            'passenger_name' => $this->passengerName(),

            'status'       => $status?->value,
            'status_label' => $status?->label(),

            'confirmation_status' => $this->confirmation_status?->value,
            'confirmation_label'  => $this->confirmation_status?->label(),

            // Marks the one trip the driver should be working on now.
            'is_next' => $this->isNext,

            'pickup' => [
                'address' => $this->pickup_address,
                'date'    => optional($this->pickup_date)->toDateString(),
                'time'    => $this->pickup_time,
                'at'      => optional($this->scheduledPickupAt())->toIso8601String(),
            ],

            'dropoff' => [
                'address' => $this->dropoff_address,
            ],

            'requirements' => $this->requirementLabels(),

            // Only calculated for the next trip, which is the only card that
            // shows an estimate. Doing it for every row would mean a routing
            // lookup per list item once a real provider is connected.
            'eta' => $this->eta?->toArray(),
        ];
    }
}
