<?php

namespace App\Http\Controllers\Api\Driver;

use App\Enums\TripStatus;
use App\Http\Resources\Driver\TripListResource;
use App\Models\Message;
use App\Models\Trip;
use App\Services\Distance\DistanceProvider;
use Illuminate\Http\JsonResponse;

class DashboardController extends BaseDriverController
{
    public function __construct(private readonly DistanceProvider $distance)
    {
    }

    /**
     * Everything the home screen shows: today's counts, the active run, and the
     * badge counts for the notification bell and the messages tab.
     */
    public function index(): JsonResponse
    {
        $driver = $this->driver();
        $today  = now()->toDateString();

        $todaysTrips = $this->driverTrips()
            ->whereDate('pickup_date', $today)
            ->count();

        $completedToday = $this->driverTrips()
            ->whereDate('pickup_date', $today)
            ->where('status', TripStatus::Completed->value)
            ->count();

        $upcoming = $this->driverTrips()
            ->with(['vehicle'])
            ->active()
            ->orderBy('pickup_date')
            ->orderBy('pickup_time')
            ->limit(6)
            ->get();

        // Only the trip at the top of the queue carries an estimate, matching
        // the design and keeping this to one distance lookup per load.
        $next = $upcoming->first();
        $eta  = $next
            ? $this->distance->between(
                $driver->last_lat !== null ? (float) $driver->last_lat : null,
                $driver->last_lng !== null ? (float) $driver->last_lng : null,
                $next->pickup_lat !== null ? (float) $next->pickup_lat : null,
                $next->pickup_lng !== null ? (float) $next->pickup_lng : null,
            )
            : null;

        $trips = $upcoming->map(fn (Trip $trip) => new TripListResource(
            $trip,
            isNext: $next && $trip->id === $next->id,
            eta: $next && $trip->id === $next->id ? $eta : null,
        ));

        return $this->ok([
            'driver' => [
                'name'        => $driver->name,
                'first_name'  => explode(' ', trim($driver->name))[0],
                'avatar_url'  => $driver->avatar_url,
                'driver_code' => $driver->driver_code,
            ],

            'stats' => [
                'trips_today'     => $todaysTrips,
                'completed_today' => $completedToday,
                'pending_today'   => max(0, $todaysTrips - $completedToday),
            ],

            'badges' => [
                'unread_notifications' => $driver->unreadNotifications()->count(),
                'unread_messages'      => Message::unreadFor($driver->id)->count(),
            ],

            'upcoming_trips' => $trips,
        ]);
    }
}
