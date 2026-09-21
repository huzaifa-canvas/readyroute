<?php

namespace App\Notifications;

use App\Models\Message;
use Illuminate\Support\Str;

/**
 * Raised when a dispatch message arrives while the app is not in the
 * conversation. The chat screen itself updates over the socket.
 */
class NewMessageNotification extends DriverNotification
{
    public function __construct(private readonly Message $message)
    {
    }

    public function kind(): string
    {
        return 'new_message';
    }

    public function title(): string
    {
        return 'Dispatch';
    }

    public function body(): string
    {
        return Str::limit($this->message->body, 120);
    }

    public function payload(): array
    {
        return [
            'message_id' => $this->message->id,
            'screen'     => 'messages',
        ];
    }
}
