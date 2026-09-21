<?php

namespace App\Http\Controllers\Api\Driver;

use App\Enums\TripStatus;
use App\Http\Resources\Driver\TripListResource;
use App\Http\Resources\Driver\TripResource;
use App\Http\Resources\Driver\TripStatusLogResource;
use App\Models\Trip;
use App\Services\Distance\DistanceProvider;
use App\Services\TripLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TripController extends BaseDriverController
{
    public function __construct(
        private readonly DistanceProvider $distance,
        private readonly TripLifecycleService $lifecycle,
    ) {
    }

    /**
     * The driver's trips, behind the "View All" link. Defaults to the active
     * run rather than every trip they have ever driven.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'filter' => ['sometimes', 'string', 'in:today,upcoming,past,all'],
            'status' => ['sometimes', 'string', 'in:' . implode(',', TripStatus::values())],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $query = $this->driverTrips()->with(['vehicle']);

        match ($request->input('filter', 'upcoming')) {
            'today'    => $query->whereDate('pickup_date', now()->toDateString()),
            'past'     => $query->whereIn('status', [
                TripStatus::Completed->value,
                TripStatus::Cancelled->value,
            ]),
            'all'      => null,
            default    => $query->active(),
        };

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Past trips read newest first; anything upcoming reads soonest first.
        $descending = $request->input('filter') === 'past';

        $trips = $query
            ->orderBy('pickup_date', $descending ? 'desc' : 'asc')
            ->orderBy('pickup_time', $descending ? 'desc' : 'asc')
            ->paginate($request->integer('per_page') ?: 15);

        $trips->getCollection()->transform(fn (Trip $trip) => new TripListResource($trip));

        return $this->paginated($trips);
    }

    /**
     * One trip in full. The estimate is measured from the driver's last known
     * position to whichever end of the trip they are heading for.
     */
    public function show(string $id): JsonResponse
    {
        $trip = $this->findTrip($id);

        if (! $trip) {
            return $this->notFound('Trip not found.');
        }

        $trip->load(['vehicle', 'client']);

        $driver = $this->driver();

        // Before the passenger is aboard the driver is heading to the pickup;
        // afterwards, to the drop-off.
        $headingToPickup = in_array($trip->statusEnum(), [
            TripStatus::Scheduled,
            TripStatus::EnRoute,
        ], true);

        $eta = $this->distance->between(
            $driver->last_lat !== null ? (float) $driver->last_lat : null,
            $driver->last_lng !== null ? (float) $driver->last_lng : null,
            $headingToPickup
                ? ($trip->pickup_lat !== null ? (float) $trip->pickup_lat : null)
                : ($trip->dropoff_lat !== null ? (float) $trip->dropoff_lat : null),
            $headingToPickup
                ? ($trip->pickup_lng !== null ? (float) $trip->pickup_lng : null)
                : ($trip->dropoff_lng !== null ? (float) $trip->dropoff_lng : null),
        );

        return $this->ok(new TripResource($trip, $eta));
    }

    /**
     * The completion screen: what the run cost, the stamped status log, and
     * whether a sign-off signature is still outstanding.
     */
    public function summary(string $id): JsonResponse
    {
        $trip = $this->findTrip($id);

        if (! $trip) {
            return $this->notFound('Trip not found.');
        }

        $trip->load(['statusLogs', 'signature', 'vehicle']);

        // While a trip is still running these are live figures rather than the
        // finalised ones written at completion.
        $duration = $trip->actual_duration_min ?? $this->lifecycle->durationMinutes($trip);
        $distance = $trip->actual_distance !== null
            ? (float) $trip->actual_distance
            : $this->lifecycle->travelledMiles($trip);

        $onTime = $trip->was_on_time ?? $trip->resolveOnTime();

        return $this->ok([
            'trip' => [
                'id'        => $trip->id,
                'reference' => $trip->reference(),
                'status'    => $trip->statusEnum()?->value,
                'date'      => optional($trip->pickup_date)->toDateString(),
            ],

            'passenger' => [
                'full_name'    => $trip->passengerName(),
                'phone_number' => $trip->phone_number,
            ],

            'pickup'  => ['address' => $trip->pickup_address],
            'dropoff' => ['address' => $trip->dropoff_address],

            'totals' => [
                'duration_minutes' => $duration,
                'distance_miles'   => $distance,
                'on_time'          => $onTime,
                'on_time_label'    => $onTime === null
                    ? null
                    : ($onTime ? 'On Time' : 'Late'),
            ],

            'status_log' => TripStatusLogResource::collection($trip->statusLogs),

            'signature' => [
                'required'  => $trip->isStatus(TripStatus::Completed),
                'collected' => $trip->signature !== null,
                'signed_at' => optional($trip->signature?->signed_at)->toIso8601String(),
            ],
        ]);
    }
}
