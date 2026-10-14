<?php

namespace App\Http\Resources\Dispatcher;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'   => $this->id,
            'body' => $this->body,

            // A note the driver can see is highlighted on the profile screen,
            // because it is the one that will travel with the trip.
            'visible_to_driver' => (bool) $this->visible_to_driver,

            'author' => [
                'id'   => $this->author_id,
                'name' => $this->whenLoaded('author', fn () => $this->author?->name),
            ],

            'created_at' => optional($this->created_at)->toIso8601String(),
            // "Sarah Mitchell · Apr 28, 2026 · 9:15 AM" on the screen.
            'created_label' => optional($this->created_at)->format('M j, Y · g:i A'),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
