<?php

namespace App\Http\Resources\Driver;

use App\Enums\TripStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One line of the Status Log on the trip completion screen.
 */
class TripStatusLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof TripStatus
            ? $this->status
            : TripStatus::tryFrom((string) $this->status);

        return [
            'status' => $status?->value,
            'label'  => $status?->label(),
            'at'     => optional($this->logged_at)->toIso8601String(),
            'time'   => optional($this->logged_at)->format('g:i A'),
            'lat'    => $this->lat !== null ? (float) $this->lat : null,
            'lng'    => $this->lng !== null ? (float) $this->lng : null,
        ];
    }
}
