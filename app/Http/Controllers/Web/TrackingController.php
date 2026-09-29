<?php

namespace App\Http\Controllers\Web;

use App\Enums\TripStatus;
use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Services\Distance\DistanceProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The passenger's live tracking page.
 *
 * Reached from an SMS link and nothing else: there is no account, so the token
 * in the URL is the only credential. That shapes everything here — the token
 * is long and random rather than the trip id, the page exposes only what
 * someone waiting at the kerb needs, and a finished trip stops reporting a
 * position.
 */
class TrackingController extends Controller
{
    public function __construct(private readonly DistanceProvider $distance)
    {
    }

    public function show(string $token)
    {
        $trip = $this->findTrip($token);

        $trip->load(['driver', 'vehicle', 'dispatcher']);

        return view('content.public.tracking', [
            'trip'    => $trip,
            'state'   => $this->state($trip),
            'company' => $trip->dispatcher,
        ]);
    }

    /**
     * Polled by the page every few seconds. Kept separate from show() so the
     * page itself is cacheable and the moving parts are one small payload.
     */
    public function position(string $token): JsonResponse
    {
        $trip = $this->findTrip($token);
        $trip->load('driver');

        return response()->json([
            'status' => true,
            'data'   => $this->state($trip),
        ]);
    }

    /**
     * Everything the page renders, in one shape used by both the initial
     * render and the poll.
     *
     * @return array<string, mixed>
     */
    private function state(Trip $trip): array
    {
        $status   = $trip->statusEnum();
        $finished = $status?->isTerminal() ?? false;
        $driver   = $trip->driver;

        // A driver's position is only shared while the trip is running. Before
        // and after, there is no operational reason for a passenger to see
        // where the vehicle is.
        $showPosition = ! $finished
            && $driver
            && $driver->last_lat !== null
            && $driver->last_lng !== null
            && in_array($status, [TripStatus::EnRoute, TripStatus::ArrivedPickup, TripStatus::InProgress], true);

        return [
            'reference'    => $trip->reference(),
            'status'       => $status?->value,
            'status_label' => $status?->label(),
            'step_label'   => $status?->stepLabel(),
            'is_finished'  => $finished,
            'headline'     => $this->headline($trip, $status),

            'driver' => $driver ? [
                'name'    => $driver->name,
                // First name only after the trip: enough to recognise the
                // person who drove, without keeping a full name on a link
                // that may be forwarded.
                'initial' => mb_substr($driver->name, 0, 1),
            ] : null,

            'vehicle' => $trip->vehicle ? [
                'name'  => $trip->vehicle->name,
                'model' => $trip->vehicle->make_model_year,
                'plate' => $trip->vehicle->number_plate,
            ] : null,

            'position' => $showPosition ? [
                'lat' => (float) $driver->last_lat,
                'lng' => (float) $driver->last_lng,
                'at'  => optional($driver->last_location_at)->toIso8601String(),
            ] : null,

            // The map is drawn from whatever points exist. A pickup pin is
            // shown from the moment the trip is booked; the vehicle only
            // joins it once the trip is actually running, which is why the
            // two are separate.
            'map' => [
                'pickup' => $trip->pickup_lat !== null && $trip->pickup_lng !== null
                    ? ['lat' => (float) $trip->pickup_lat, 'lng' => (float) $trip->pickup_lng]
                    : null,
                'dropoff' => $trip->dropoff_lat !== null && $trip->dropoff_lng !== null
                    ? ['lat' => (float) $trip->dropoff_lat, 'lng' => (float) $trip->dropoff_lng]
                    : null,
                'has_points' => $showPosition
                    || ($trip->pickup_lat !== null && $trip->pickup_lng !== null)
                    || ($trip->dropoff_lat !== null && $trip->dropoff_lng !== null),
            ],

            'eta_minutes' => $showPosition ? $this->eta($trip) : null,

            'pickup' => [
                'address' => $trip->pickup_address,
                'lat'     => $trip->pickup_lat !== null ? (float) $trip->pickup_lat : null,
                'lng'     => $trip->pickup_lng !== null ? (float) $trip->pickup_lng : null,
                'at'      => optional($trip->scheduledPickupAt())->toIso8601String(),
                'time'    => $trip->pickup_time,
            ],

            // The dispatch number, never the driver's personal one.
            'contact' => $trip->dispatcher?->phone_number,

            'updated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Minutes until the driver reaches whichever end of the trip they are
     * heading for.
     */
    private function eta(Trip $trip): ?int
    {
        $driver = $trip->driver;

        $heading = $trip->isStatus(TripStatus::InProgress)
            ? [$trip->dropoff_lat, $trip->dropoff_lng]
            : [$trip->pickup_lat, $trip->pickup_lng];

        if ($heading[0] === null || $heading[1] === null) {
            return null;
        }

        return $this->distance->between(
            (float) $driver->last_lat,
            (float) $driver->last_lng,
            (float) $heading[0],
            (float) $heading[1],
        )->minutes;
    }

    private function headline(Trip $trip, ?TripStatus $status): string
    {
        $vehicle = $trip->vehicle?->name ?: 'Your ride';

        return match ($status) {
            TripStatus::Scheduled     => 'Your trip is booked',
            TripStatus::EnRoute       => $vehicle . ' is on the way',
            TripStatus::ArrivedPickup => $vehicle . ' has arrived',
            TripStatus::InProgress    => 'On the way to your destination',
            TripStatus::ArrivedDropoff => 'You have arrived',
            TripStatus::Completed     => 'Trip completed',
            TripStatus::Cancelled     => 'This trip was cancelled',
            default                   => 'Trip status',
        };
    }

    /**
     * A bad or stale token is a 404, never an explanation — the page must not
     * confirm that some other token would have worked.
     */
    private function findTrip(string $token): Trip
    {
        abort_if(strlen($token) < 20, 404);

        return Trip::where('public_token', $token)->firstOrFail();
    }
}
