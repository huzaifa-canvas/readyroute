<?php

namespace App\Http\Controllers\Api\Dispatcher;

use App\Enums\TripStatus;
use App\Http\Resources\Dispatcher\TripListResource;
use App\Models\TripIncident;
use Illuminate\Http\JsonResponse;

/**
 * The home screen, and the counts the bell and badges need.
 */
class DashboardController extends BaseDispatcherController
{
    public function index(): JsonResponse
    {
        $companyId = $this->companyId();
        $today     = today()->toDateString();

        // One pass over today's work rather than a count query per tile: the
        // four tiles are four slices of the same day.
        $todays = $this->trips()->whereDate('pickup_date', $today)->get();

        $drivers = $this->drivers()->get();

        $active = $this->trips()
            ->with(['driver', 'vehicle'])
            ->whereIn('status', [
                TripStatus::Scheduled->value,
                TripStatus::EnRoute->value,
                TripStatus::ArrivedPickup->value,
                TripStatus::InProgress->value,
                TripStatus::ArrivedDropoff->value,
            ])
            ->orderBy('pickup_date')
            ->orderBy('pickup_time')
            ->limit(10)
            ->get();

        return $this->ok([
            'greeting' => $this->greeting(),
            'user'     => [
                'id'         => $this->user()->id,
                'name'       => $this->user()->name,
                'avatar_url' => $this->user()->avatar_url,
            ],

            // The four tiles, in the order the screen draws them.
            'stats' => [
                'trips_today'    => $todays->count(),
                'active_drivers' => $drivers->filter->isCurrentlyOnline()->count(),
                'pending'        => $todays->filter(
                    fn ($trip) => $trip->isStatus(TripStatus::Scheduled)
                )->count(),
                'completed'      => $todays->filter(
                    fn ($trip) => $trip->isStatus(TripStatus::Completed)
                )->count(),
            ],

            // Shown on the bell and the tab bar, so the home call does not
            // need a second request to draw its own badges.
            'badges' => [
                'unread_notifications' => $this->user()->unreadNotifications()->count(),
                'unread_messages'      => \App\Models\Message::where('dispatcher_id', $companyId)
                    ->where('receiver_id', $companyId)
                    ->whereNull('read_at')
                    ->count(),
                'open_incidents' => TripIncident::forDispatcher($companyId)->open()->count(),
            ],

            'active_trips' => TripListResource::collection($active),

            'totals' => [
                'drivers'   => $drivers->count(),
                'unassigned_trips' => $this->trips()->whereNull('driver_id')->count(),
            ],
        ]);
    }

    /**
     * "Good Morning" and the rest, in the company's own working hours rather
     * than the server's.
     */
    private function greeting(): string
    {
        return match (true) {
            now()->hour < 12 => 'Good Morning',
            now()->hour < 17 => 'Good Afternoon',
            default          => 'Good Evening',
        };
    }

    /**
     * Everything the badges count, on its own, for the poll the app runs while
     * it sits on a screen that is not the home screen.
     */
    public function summary(): JsonResponse
    {
        $companyId = $this->companyId();

        $unreadByDriver = \App\Models\Message::where('dispatcher_id', $companyId)
            ->where('receiver_id', $companyId)
            ->whereNull('read_at')
            ->selectRaw('driver_id, COUNT(*) as total')
            ->groupBy('driver_id')
            ->pluck('total', 'driver_id');

        return $this->ok([
            'messages' => [
                'unread'     => (int) $unreadByDriver->sum(),
                'per_driver' => $unreadByDriver->map(fn ($count) => (int) $count),
            ],
            'notifications' => [
                'unread' => $this->user()->unreadNotifications()->count(),
            ],
            'incidents' => [
                'open' => TripIncident::forDispatcher($companyId)->open()->count(),
                'sos'  => TripIncident::forDispatcher($companyId)->open()->where('type', 'sos')->count(),
            ],
            'checked_at' => now()->toIso8601String(),
        ]);
    }
}
