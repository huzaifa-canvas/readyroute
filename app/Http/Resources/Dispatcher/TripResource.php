<?php

namespace App\Http\Resources\Dispatcher;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One trip in full, for the detail screen: the route, who is on it, the
 * status timeline and the sign-off.
 */
class TripResource extends JsonResource
{
    public function __construct(
        $resource,
        private readonly mixed $inspection = null,
        private readonly ?string $signatureUrl = null,
    ) {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $status = $this->statusEnum();

        return [
            'id'        => $this->id,
            'reference' => $this->reference(),

            'status'       => $status?->value,
            'status_label' => $status?->label(),
            'status_class' => $status?->badgeClass(),
            'is_cancelled' => $this->isCancelled(),
            'is_finished'  => (bool) $status?->isTerminal(),
            'cancelled_at' => optional($this->cancelled_at)->toIso8601String(),
            'updated_at'   => optional($this->updated_at)->toIso8601String(),

            // A cancelled or finished trip is read-only; the app greys the
            // buttons rather than letting a tap fail at the server.
            'can_edit'     => ! $this->isCancelled() && ! ($status?->isTerminal() ?? false),
            'can_cancel'   => ! ($status?->isTerminal() ?? false),
            'can_delete'   => ! $this->isBilled(),
            'is_billed'    => $this->isBilled(),

            'route' => [
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
                'distance'        => $this->distance !== null ? (float) $this->distance : null,
                'actual_distance' => $this->actual_distance !== null ? (float) $this->actual_distance : null,
            ],

            'client' => [
                'id'      => $this->client_id,
                'name'    => $this->passengerName(),
                'phone'   => $this->phone_number,
                'member_id' => $this->member_id,
                'funding' => $this->whenLoaded('client', fn () => $this->client?->funding_type),
            ],

            'driver' => $this->whenLoaded('driver', fn () => $this->driver ? [
                'id'         => $this->driver->id,
                'name'       => $this->driver->name,
                'code'       => $this->driver->driver_code,
                'phone'      => $this->driver->phone_number,
                'avatar_url' => $this->driver->avatar_url,
                'is_online'  => $this->driver->isCurrentlyOnline(),
            ] : null),

            'vehicle' => $this->whenLoaded('vehicle', fn () => $this->vehicle ? [
                'id'           => $this->vehicle->id,
                'name'         => $this->vehicle->name,
                'number_plate' => $this->vehicle->number_plate,
            ] : null),

            'trip_type'    => $this->trip_type,
            'billing_type' => $this->billing_type,
            'notes'        => $this->notes,
            'requirements' => $this->requirementLabels(),

            'was_on_time'         => $this->was_on_time,
            'actual_duration_min' => $this->actual_duration_min,
            'completed_at'        => optional($this->completed_at)->toIso8601String(),

            // The screen draws this as a vertical timeline with a tick, a
            // live dot or an em dash per row, so every step is listed even
            // when it has not happened.
            'timeline' => $this->whenLoaded('statusLogs', fn () => $this->timelineRows()),

            'tracking_url' => $this->tracking_token ? $this->trackingUrl() : null,

            'signature' => $this->whenLoaded('signature', fn () => $this->signature ? [
                'driver_name' => $this->driver?->name,
                'driver_code' => $this->driver?->driver_code,
                'signed_at'   => optional($this->signature->created_at)->toIso8601String(),
                'time'        => optional($this->signature->created_at)->format('g:i A'),
                'image_url'   => $this->signatureUrl,
            ] : null),

            // The driver's pre-trip check for the day this trip runs — one a
            // day, not one per trip, which is why it is looked up by date.
            'inspection' => $this->inspection,
        ];
    }

    /**
     * Every step of the run, in order, with the time it happened or null.
     *
     * @return array<int, array<string, mixed>>
     */
    private function timelineRows(): array
    {
        $logged = $this->statusLogs->keyBy(fn ($log) => $log->status instanceof \BackedEnum
            ? $log->status->value
            : $log->status);

        return collect(\App\Enums\TripStatus::cases())
            // Cancelling is not a step on the way to anywhere, so it is not
            // a row on the timeline; the header already says so.
            ->reject(fn ($case) => $case === \App\Enums\TripStatus::Cancelled)
            ->map(function ($case) use ($logged) {
                $log = $logged->get($case->value);

                return [
                    'status' => $case->value,
                    'label'  => $case->label(),
                    'at'     => optional($log?->created_at)->toIso8601String(),
                    'time'   => optional($log?->created_at)->format('g:i A'),
                    'done'   => $log !== null,
                    'is_current' => $this->status === $case->value,
                ];
            })
            ->values()
            ->all();
    }
}
