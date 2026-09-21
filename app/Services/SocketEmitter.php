<?php

namespace App\Services;

use App\Jobs\EmitSocketEvent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pushes an event to the socket server for delivery to connected clients.
 *
 * Nothing here is authoritative. Messages, notifications and statuses are
 * already committed to the database before anything is emitted, so a socket
 * server that is down or slow costs realtime delivery and nothing else.
 */
class SocketEmitter
{
    /**
     * Queue an event for delivery, which is how application code should emit.
     * Doing the HTTP call on the queue keeps it off the request path entirely.
     */
    public function queue(string $room, string $event, array $payload = []): void
    {
        if (! config('socket.enabled')) {
            return;
        }

        EmitSocketEvent::dispatch($room, $event, $payload);
    }

    /**
     * Send immediately. Failures are logged and swallowed: a chat message that
     * is safely in the database must not produce a failed API response just
     * because the socket process is unreachable.
     */
    public function emit(string $room, string $event, array $payload = []): bool
    {
        if (! config('socket.enabled')) {
            return false;
        }

        try {
            $response = Http::timeout((int) config('socket.timeout', 3))
                ->withHeaders(['X-Socket-Secret' => (string) config('socket.secret')])
                ->post(rtrim((string) config('socket.url'), '/') . '/emit', [
                    'room'    => $room,
                    'event'   => $event,
                    'payload' => $payload,
                ]);

            if ($response->failed()) {
                Log::warning('Socket emit rejected', [
                    'room'   => $room,
                    'event'  => $event,
                    'status' => $response->status(),
                ]);

                return false;
            }

            return true;
        } catch (Throwable $e) {
            Log::warning('Socket emit failed', [
                'room'  => $room,
                'event' => $event,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    // ── Room names ────────────────────────────────────────────────
    // Kept here so Laravel and the socket server cannot drift apart.

    public static function userRoom(int $userId): string
    {
        return 'user.' . $userId;
    }

    public static function threadRoom(int $dispatcherId, int $driverId): string
    {
        return 'thread.' . $dispatcherId . '.' . $driverId;
    }

    public static function dispatcherRoom(int $dispatcherId): string
    {
        return 'dispatcher.' . $dispatcherId;
    }
}
