<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Requests\Driver\StoreLocationRequest;
use App\Services\TripLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class LocationController extends BaseDriverController
{
    public function __construct(private readonly TripLifecycleService $lifecycle)
    {
    }

    /**
     * Record where the driver is. Fixes are attached to whichever trip is
     * currently running, so the completion screen can report the distance
     * actually driven rather than the distance planned.
     */
    public function store(StoreLocationRequest $request): JsonResponse
    {
        $driver = $this->driver();

        // The app may name a trip, but it must be one of this driver's own.
        $tripId = null;

        if ($request->filled('trip_id')) {
            $tripId = $this->findTrip($request->integer('trip_id'))?->id;
        }

        $tripId ??= $this->lifecycle->activeTripFor($driver)?->id;

        $points = $request->points();
        $stored = 0;

        foreach ($points as $point) {
            $this->lifecycle->recordPosition(
                driver: $driver,
                lat: (float) $point['lat'],
                lng: (float) $point['lng'],
                tripId: $tripId,
                recordedAt: isset($point['recorded_at']) && $point['recorded_at']
                    ? Carbon::parse($point['recorded_at'])
                    : now(),
                speed: isset($point['speed_mph']) ? (float) $point['speed_mph'] : null,
                heading: isset($point['heading']) ? (float) $point['heading'] : null,
                accuracy: isset($point['accuracy_m']) ? (float) $point['accuracy_m'] : null,
            );

            $stored++;
        }

        return $this->ok([
            'stored'         => $stored,
            'active_trip_id' => $tripId,
        ]);
    }
}
