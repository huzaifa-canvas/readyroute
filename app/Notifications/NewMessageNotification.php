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

    /**
     * Chat stays out of the notification list.
     *
     * A message is already a row in the messages table, already shown on the
     * chat screen, and already counted by the unread badge on the Messages
     * icon. Writing a notification row as well put every message in the bell a
     * second time, so a busy conversation buried the things the list exists
     * for — incidents, SOS alerts and trip changes. Delivery is unaffected:
     * the socket still updates an open chat, and push still reaches a phone
     * that is not looking at it.
     */
    public function storesInDatabase(): bool
    {
        return false;
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
