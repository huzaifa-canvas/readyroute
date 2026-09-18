<?php

namespace App\Services;

use App\Enums\TripStatus;
use App\Models\DriverLocation;
use App\Models\Trip;
use App\Models\TripStatusLog;
use App\Models\User;
use App\Support\Geo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Moves a trip through its run and records what happened.
 *
 * All of the lifecycle rules live here rather than in the controller, because
 * the dispatcher panel and any future automation need to obey exactly the same
 * ones the driver app does.
 */
class TripLifecycleService
{
    /**
     * Which timestamp column each status stamps when it is reached.
     */
    private const TIMESTAMP_COLUMNS = [
        'en_route'        => 'en_route_at',
        'arrived_pickup'  => 'arrived_pickup_at',
        'in_progress'     => 'started_at',
        'arrived_dropoff' => 'arrived_dropoff_at',
        'completed'       => 'completed_at',
    ];

    /**
     * Advance a trip to the next status.
     *
     * @throws RuntimeException when the transition is not allowed
     */
    public function transition(
        Trip $trip,
        TripStatus $to,
        User $driver,
        ?float $lat = null,
        ?float $lng = null,
    ): Trip {
        $from = $trip->statusEnum();

        if (! $from) {
            throw new RuntimeException('This trip has an unrecognised status and cannot be updated.');
        }

        if ($from === $to) {
            throw new RuntimeException('This trip is already marked as ' . $to->label() . '.');
        }

        if (! $from->canTransitionTo($to)) {
            throw new RuntimeException(
                'A trip cannot go from ' . $from->label() . ' to ' . $to->label() . '.'
            );
        }

        if (! in_array($to, TripStatus::driverSettable(), true)) {
            throw new RuntimeException('Drivers cannot set a trip to ' . $to->label() . '.');
        }

        $now = now();

        return DB::transaction(function () use ($trip, $to, $driver, $lat, $lng, $now) {
            $trip->status = $to->value;

            if ($column = self::TIMESTAMP_COLUMNS[$to->value] ?? null) {
                // Keep the first stamp if a status is somehow reached twice.
                $trip->{$column} = $trip->{$column} ?: $now;
            }

            if ($to === TripStatus::Completed) {
                $this->finalise($trip);
            }

            $trip->save();

            TripStatusLog::create([
                'trip_id'   => $trip->id,
                'driver_id' => $driver->id,
                'status'    => $to->value,
                'lat'       => $lat,
                'lng'       => $lng,
                'logged_at' => $now,
            ]);

            if ($lat !== null && $lng !== null) {
                $this->recordPosition($driver, $lat, $lng, $trip->id, $now);
            }

            return $trip->fresh();
        });
    }

    /**
     * Fill in what the run actually cost, once it is over.
     */
    private function finalise(Trip $trip): void
    {
        $trip->actual_duration_min = $this->durationMinutes($trip);
        $trip->actual_distance     = $this->travelledMiles($trip);
        $trip->was_on_time         = $trip->resolveOnTime();
    }

    /**
     * How long the passenger was aboard: from the trip starting to arriving at
     * the drop-off, which is the figure the completion screen reports. Time
     * spent handing over at the destination is not part of the ride.
     */
    public function durationMinutes(Trip $trip): ?int
    {
        $start = $trip->started_at;
        $end   = $trip->arrived_dropoff_at ?: $trip->completed_at ?: now();

        if (! $start) {
            return null;
        }

        return max(0, (int) round($start->diffInSeconds($end) / 60));
    }

    /**
     * Distance actually driven, measured from the GPS trail. Falls back to the
     * straight-line distance between the two addresses when no trail was
     * recorded, and finally to whatever the dispatcher planned.
     */
    public function travelledMiles(Trip $trip): ?float
    {
        $points = DriverLocation::where('trip_id', $trip->id)
            ->orderBy('recorded_at')
            ->get(['lat', 'lng'])
            ->map(fn ($row) => ['lat' => $row->lat, 'lng' => $row->lng]);

        if ($points->count() >= 2) {
            $travelled = Geo::pathMiles($points);

            if ($travelled > 0) {
                return $travelled;
            }
        }

        if ($trip->pickup_lat !== null && $trip->dropoff_lat !== null) {
            return round(Geo::haversineMiles(
                (float) $trip->pickup_lat,
                (float) $trip->pickup_lng,
                (float) $trip->dropoff_lat,
                (float) $trip->dropoff_lng,
            ), 2);
        }

        return $trip->distance !== null ? (float) $trip->distance : null;
    }

    /**
     * Store a GPS fix and mirror it onto the driver so the live map can read a
     * current position without scanning the history table.
     */
    public function recordPosition(
        User $driver,
        float $lat,
        float $lng,
        ?int $tripId = null,
        ?Carbon $recordedAt = null,
        ?float $speed = null,
        ?float $heading = null,
        ?float $accuracy = null,
    ): DriverLocation {
        $recordedAt = $recordedAt ?: now();

        $location = DriverLocation::create([
            'user_id'     => $driver->id,
            'trip_id'     => $tripId,
            'lat'         => $lat,
            'lng'         => $lng,
            'speed_mph'   => $speed,
            'heading'     => $heading,
            'accuracy_m'  => $accuracy,
            'recorded_at' => $recordedAt,
        ]);

        // Never let an out-of-order fix overwrite a newer one.
        if (! $driver->last_location_at || $driver->last_location_at->lte($recordedAt)) {
            $driver->forceFill([
                'last_lat'         => $lat,
                'last_lng'         => $lng,
                'last_location_at' => $recordedAt,
                'is_online'        => true,
                'last_seen_at'     => now(),
            ])->saveQuietly();
        }

        return $location;
    }

    /**
     * The trip a driver is currently running, if any. Used to attach GPS fixes
     * to the right trip without the app having to tell us.
     */
    public function activeTripFor(User $driver): ?Trip
    {
        return Trip::forDriver($driver->id)
            ->whereIn('status', [
                TripStatus::EnRoute->value,
                TripStatus::ArrivedPickup->value,
                TripStatus::InProgress->value,
                TripStatus::ArrivedDropoff->value,
            ])
            ->orderBy('pickup_date')
            ->orderBy('pickup_time')
            ->first();
    }
}
