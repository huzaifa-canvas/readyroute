<?php

namespace App\Http\Resources\Driver;

use App\Services\SignatureService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One incident as the driver app sees it.
 *
 * Photos are returned as short-lived signed URLs rather than paths, the same
 * way signatures are, so nothing on the private disk is ever addressable
 * without a fresh, expiring link.
 */
class IncidentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'       => $this->id,
            'type'     => $this->type,
            'type_label' => $this->typeLabel(),
            'is_sos'   => $this->isSos(),

            'severity'       => $this->severity,
            'severity_label' => $this->severityLabel(),

            'status'       => $this->status,
            'status_label' => $this->statusLabel(),
            'is_resolved'  => $this->isResolved(),

            'title'       => $this->title,
            'headline'    => $this->headline(),
            'description' => $this->description,

            'location' => [
                'lat'     => $this->lat,
                'lng'     => $this->lng,
                'address' => $this->address,
            ],

            'photos' => $this->photoUrls(),

            'trip' => $this->whenLoaded('trip', fn () => $this->trip ? [
                'id'        => $this->trip->id,
                'reference' => $this->trip->reference(),
                'passenger' => $this->trip->passengerName(),
            ] : null),

            'vehicle' => $this->whenLoaded('vehicle', fn () => $this->vehicle ? [
                'id'   => $this->vehicle->id,
                'name' => $this->vehicle->name,
            ] : null),

            // What the office has done about it, so the driver is not left
            // wondering whether anyone saw the alert.
            'acknowledged_at' => optional($this->acknowledged_at)->toIso8601String(),
            'acknowledged_by' => $this->whenLoaded('acknowledgedBy', fn () => $this->acknowledgedBy?->name),
            'resolved_at'     => optional($this->resolved_at)->toIso8601String(),
            'resolution_note' => $this->resolution_note,

            'created_at' => $this->created_at->toIso8601String(),
            'reported'   => $this->created_at->diffForHumans(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function photoUrls(): array
    {
        $paths = $this->attachments ?? [];

        if ($paths === []) {
            return [];
        }

        $service = app(SignatureService::class);

        return collect($paths)
            ->map(fn (string $path) => $service->temporaryUrl($path))
            ->filter()
            ->values()
            ->all();
    }
}
