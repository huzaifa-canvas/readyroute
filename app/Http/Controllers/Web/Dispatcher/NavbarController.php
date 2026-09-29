<?php

namespace App\Http\Controllers\Web\Dispatcher;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\TripIncident;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * One poll for everything the navbar counts.
 *
 * Messages, notifications and open incidents each have their own badge, but
 * they refresh together every 30 seconds — three separate requests on a timer
 * would triple the load for no benefit, and would let the badges disagree
 * with each other between ticks.
 */
class NavbarController extends Controller
{
    public function summary(): JsonResponse
    {
        $user      = auth()->user();
        $companyId = $user->companyId();

        // Messages the drivers sent that nobody in the office has opened.
        $unreadByDriver = Message::where('dispatcher_id', $companyId)
            ->where('receiver_id', $companyId)
            ->whereNull('read_at')
            ->selectRaw('driver_id, COUNT(*) as total')
            ->groupBy('driver_id')
            ->pluck('total', 'driver_id');

        $messages = [
            'unread'     => (int) $unreadByDriver->sum(),
            // Keyed by driver so the chat sidebar can update each row without
            // reloading the page.
            'per_driver' => $unreadByDriver->map(fn ($count) => (int) $count),
            'senders'    => $unreadByDriver->count(),
        ];

        $notifications = [
            'unread' => $user->unreadNotifications()->count(),
            'recent' => $user->notifications()->latest()->limit(7)->get()->map(fn ($note) => [
                'id'      => $note->id,
                'kind'    => $note->data['kind'] ?? 'general',
                'title'   => $note->data['title'] ?? 'Notification',
                'body'    => \Illuminate\Support\Str::limit($note->data['body'] ?? '', 64),
                'is_read' => $note->read_at !== null,
                'ago'     => $note->created_at->diffForHumans(),
                'url'     => $this->targetFor($note->data['data'] ?? []),
            ])->values(),
        ];

        return response()->json([
            'status' => true,
            'data'   => [
                'messages'      => $messages,
                'notifications' => $notifications,
                'incidents'     => [
                    'open' => TripIncident::forDispatcher($companyId)->open()->count(),
                    'sos'  => TripIncident::forDispatcher($companyId)->open()->where('type', 'sos')->count(),
                ],
                'checked_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Where tapping a notification should land, mirroring the dropdown.
     *
     * @param  array<string, mixed>  $data
     */
    private function targetFor(array $data): string
    {
        return match (true) {
            ! empty($data['incident_id']) => route('dispatcher.incidents.show', $data['incident_id']),
            ! empty($data['trip_id'])     => route('dispatcher.trip.details', $data['trip_id']),
            ! empty($data['driver_id'])   => route('dispatcher.messages.index', ['driver' => $data['driver_id']]),
            default                       => route('dispatcher.notifications.index'),
        };
    }
}
