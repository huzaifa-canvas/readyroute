<?php

namespace App\Http\Controllers\Web\Dispatcher;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Notification Center.
 *
 * Reads Laravel's own notifications table, which the driver API already writes
 * to — so incidents, messages and trip events all land here without a second
 * delivery path.
 */
class NotificationController extends Controller
{
    /**
     * The kinds worth filtering by. Anything unrecognised still shows in the
     * unfiltered list rather than disappearing.
     */
    private const KINDS = [
        'sos'            => 'Emergency',
        'incident'       => 'Incidents',
        'trip_added'     => 'Trips',
        'trip_cancelled' => 'Cancellations',
        'route_change'   => 'Route changes',
        'reminder'       => 'Reminders',
    ];

    public function index(Request $request)
    {
        $user = auth()->user();

        $query = $user->notifications();

        if ($request->input('filter') === 'unread') {
            $query = $user->unreadNotifications();
        }

        // The kind lives inside the JSON payload, so it is matched there
        // rather than on a column.
        if ($request->filled('kind') && array_key_exists($request->kind, self::KINDS)) {
            $query->where('data', 'like', '%"kind":"' . $request->kind . '"%');
        }

        $notifications = $query->latest()->paginate(20)->withQueryString();

        $counts = [
            'all'    => $user->notifications()->count(),
            'unread' => $user->unreadNotifications()->count(),
        ];

        $kinds = self::KINDS;

        return view('content.dispatcher.notifications.index', compact('notifications', 'counts', 'kinds'));
    }

    public function markRead($id)
    {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return back();
    }

    public function markAllRead()
    {
        $count = auth()->user()->unreadNotifications()->count();

        auth()->user()->unreadNotifications->markAsRead();

        return back()->with('success', $count . ' notification(s) marked as read.');
    }

    public function destroy($id)
    {
        auth()->user()->notifications()->findOrFail($id)->delete();

        return back()->with('success', 'Notification removed.');
    }

    /**
     * Unread count for the navbar bell.
     */
    public function summary(): JsonResponse
    {
        $user = auth()->user();

        return response()->json([
            'status' => true,
            'data'   => [
                'unread' => $user->unreadNotifications()->count(),
                'recent' => $user->notifications()->latest()->limit(5)->get()->map(fn ($notification) => [
                    'id'      => $notification->id,
                    'kind'    => $notification->data['kind'] ?? 'general',
                    'title'   => $notification->data['title'] ?? 'Notification',
                    'body'    => $notification->data['body'] ?? '',
                    'is_read' => $notification->read_at !== null,
                    'ago'     => $notification->created_at->diffForHumans(),
                ])->values(),
            ],
        ]);
    }
}
