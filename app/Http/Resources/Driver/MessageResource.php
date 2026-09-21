<?php

namespace App\Http\Resources\Driver;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewerId = $request->user()?->id;

        return [
            'id'      => $this->id,
            'body'    => $this->body,

            // Which side of the conversation the bubble sits on.
            'is_mine' => $viewerId !== null && $this->sender_id === $viewerId,

            'sender' => [
                'id'         => $this->sender_id,
                'name'       => $this->whenLoaded('sender', fn () => $this->sender?->name),
                'avatar_url' => $this->whenLoaded('sender', fn () => $this->sender?->avatar_url),
            ],

            // Drives the double tick.
            'is_read' => $this->isRead(),
            'read_at' => optional($this->read_at)->toIso8601String(),

            'created_at' => $this->created_at->toIso8601String(),
            'time'       => $this->created_at->format('g:i A'),
        ];
    }
}
