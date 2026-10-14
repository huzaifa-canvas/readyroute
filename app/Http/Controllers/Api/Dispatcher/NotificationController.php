<?php

namespace App\Http\Controllers\Api\Dispatcher;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The notification centre.
 *
 * Reads Laravel's own notifications table, which the driver API already writes
 * to, so incidents, trip events and reminders all land here without a second
 * delivery path. Chat is the one thing deliberately missing: messages are
 * counted from the message rows, never stored as notifications.
 */
class NotificationController extends BaseDispatcherController
{
    /**
     * The kinds worth filtering by. Anything unrecognised still appears in the
     * unfiltered list rather than disappearing.
     */
    private const KINDS = [
        'sos'               => 'Emergency',
        'incident'          => 'Incidents',
        'trip_added'        => 'Trips',
        'trip_cancelled'    => 'Cancellations',
        'route_change'      => 'Route changes',
        'reminder'          => 'Reminders',
        'inspection_defect' => 'Inspection defects',
    ];

    public function index(Request $request): JsonResponse
    {
        $user = $this->user();

        $query = $request->input('filter') === 'unread'
            ? $user->unreadNotifications()
            : $user->notifications();

        // The kind lives inside the JSON payload, so it is matched there
        // rather than on a column.
        if ($request->filled('kind') && array_key_exists($request->input('kind'), self::KINDS)) {
            $query->where('data', 'like', '%"kind":"' . $request->input('kind') . '"%');
        }

        $page = $query->latest()->paginate($this->perPage(20));

        return response()->json([
            'status'  => true,
            'message' => null,
            'data'    => [
                'notifications' => collect($page->items())->map(fn ($note) => $this->row($note))->values(),
                'counts' => [
                    'all'    => $user->notifications()->count(),
                    'unread' => $user->unreadNotifications()->count(),
                ],
                'kinds' => collect(self::KINDS)
                    ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                    ->values(),
            ],
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'has_more'     => $page->hasMorePages(),
            ],
            'errors' => null,
        ]);
    }

    public function markRead(string $id): JsonResponse
    {
        $notification = $this->user()->notifications()->find($id);

        if (! $notification) {
            return $this->notFound('Notification not found.');
        }

        $notification->markAsRead();

        return $this->ok([
            'unread' => $this->user()->unreadNotifications()->count(),
        ], 'Marked as read.');
    }

    public function markAllRead(): JsonResponse
    {
        $count = $this->user()->unreadNotifications()->count();

        $this->user()->unreadNotifications->markAsRead();

        return $this->ok(['unread' => 0], $count . ' notification(s) marked as read.');
    }

    public function destroy(string $id): JsonResponse
    {
        $notification = $this->user()->notifications()->find($id);

        if (! $notification) {
            return $this->notFound('Notification not found.');
        }

        $notification->delete();

        return $this->ok(null, 'Notification removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function row(mixed $note): array
    {
        $data = $note->data ?? [];

        return [
            'id'      => $note->id,
            'kind'    => $data['kind'] ?? 'general',
            'title'   => $data['title'] ?? 'Notification',
            'body'    => $data['body'] ?? '',
            'is_read' => $note->read_at !== null,
            'created_at' => $note->created_at->toIso8601String(),
            'ago'     => $note->created_at->diffForHumans(),

            // Where tapping it should land, named rather than given as a web
            // URL so the app can route it to its own screen.
            'target' => $this->targetFor($data['data'] ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function targetFor(array $data): array
    {
        return match (true) {
            ! empty($data['incident_id'])   => ['screen' => 'incident', 'id' => $data['incident_id']],
            ! empty($data['inspection_id']) => ['screen' => 'driver',   'id' => $data['driver_id'] ?? null],
            ! empty($data['trip_id'])       => ['screen' => 'trip',     'id' => $data['trip_id']],
            ! empty($data['driver_id'])     => ['screen' => 'chat',     'id' => $data['driver_id']],
            default                         => ['screen' => 'notifications', 'id' => null],
        };
    }
}
