<?php

namespace App\Http\Resources\Driver;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A stored sign-off, as the confirmation screen shows it. The image is served
 * through a temporary signed URL rather than a permanent path, so the link in
 * this payload expires shortly after it is issued.
 */
class SignatureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'trip_id'     => $this->trip_id,
            'signer_name' => $this->signer_name,
            'signer_role' => $this->signer_role,
            'driver_code' => $this->whenLoaded('driver', fn () => $this->driver?->driver_code),

            'signed_at'   => optional($this->signed_at)->toIso8601String(),
            'signed_date' => optional($this->signed_at)->format('M j, Y'),
            'signed_time' => optional($this->signed_at)->format('g:i A'),

            // Present once stored, which is what the screen shows as "Verified".
            'is_verified' => $this->signature_path !== null,

            'image_url'         => $this->temporaryUrl(),
            'image_url_expires' => now()
                ->addMinutes((int) config('readyroute.signatures.url_ttl_minutes', 15))
                ->toIso8601String(),
        ];
    }
}
