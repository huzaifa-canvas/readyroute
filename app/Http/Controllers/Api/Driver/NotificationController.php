<?php

namespace App\Http\Controllers\Api\Driver;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends BaseDriverController
{
    /**
     * The notification list, newest first, with the unread count for the bell.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'unread_only' => ['sometimes', 'boolean'],
            'per_page'    => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $driver = $this->driver();

        $query = $request->boolean('unread_only')
            ? $driver->unreadNotifications()
            : $driver->notifications();

        $notifications = $query->paginate($request->integer('per_page') ?: 20);

        $notifications->getCollection()->transform(
            fn (DatabaseNotification $n) => $this->present($n)
        );

        return response()->json([
            'status'  => true,
            'message' => null,
            'data'    => $notifications->items(),
            'meta'    => [
                'current_page' => $notifications->currentPage(),
                'last_page'    => $notifications->lastPage(),
                'per_page'     => $notifications->perPage(),
                'total'        => $notifications->total(),
                'has_more'     => $notifications->hasMorePages(),
                'unread_count' => $driver->unreadNotifications()->count(),
            ],
            'errors'  => null,
        ]);
    }

    public function markRead(string $id): JsonResponse
    {
        $notification = $this->driver()->notifications()->find($id);

        if (! $notification) {
            return $this->notFound('Notification not found.');
        }

        $notification->markAsRead();

        return $this->ok([
            'unread_count' => $this->driver()->unreadNotifications()->count(),
        ], 'Marked as read.');
    }

    public function markAllRead(): JsonResponse
    {
        $this->driver()->unreadNotifications->markAsRead();

        return $this->ok(['unread_count' => 0], 'All notifications marked as read.');
    }

    /**
     * Both the exact timestamp and a relative label, so the app can render
     * "5m ago" without doing timezone arithmetic of its own.
     */
    private function present(DatabaseNotification $notification): array
    {
        $data = $notification->data ?? [];

        return [
            'id'      => $notification->id,
            'kind'    => $data['kind'] ?? 'general',
            'title'   => $data['title'] ?? null,
            'body'    => $data['body'] ?? null,
            'data'    => $data['data'] ?? [],
            'is_read' => $notification->read_at !== null,
            'read_at' => optional($notification->read_at)->toIso8601String(),
            'created_at' => $notification->created_at->toIso8601String(),
            'time_ago'   => $notification->created_at->diffForHumans(short: true),
        ];
    }
}
