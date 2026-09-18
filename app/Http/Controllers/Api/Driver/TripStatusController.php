<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Requests\Driver\UpdateTripStatusRequest;
use App\Http\Resources\Driver\TripResource;
use App\Services\TripLifecycleService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class TripStatusController extends BaseDriverController
{
    public function __construct(private readonly TripLifecycleService $lifecycle)
    {
    }

    /**
     * Advance a trip one step: en route, arrived, started, arrived at drop-off,
     * completed. One endpoint rather than five, because the rule that matters
     * is the order, and that is checked in one place.
     */
    public function update(UpdateTripStatusRequest $request, string $id): JsonResponse
    {
        $trip = $this->findTrip($id);

        if (! $trip) {
            return $this->notFound('Trip not found.');
        }

        try {
            $trip = $this->lifecycle->transition(
                trip: $trip,
                to: $request->status(),
                driver: $this->driver(),
                lat: $request->filled('lat') ? (float) $request->input('lat') : null,
                lng: $request->filled('lng') ? (float) $request->input('lng') : null,
            );
        } catch (RuntimeException $e) {
            // A rejected transition is the app being out of step with the
            // server, so the current trip goes back with the error.
            return $this->fail($e->getMessage(), 422, [
                'status' => [$e->getMessage()],
            ]);
        }

        $trip->load(['vehicle']);

        return $this->ok(
            new TripResource($trip),
            $trip->statusEnum()?->label() . ' recorded.'
        );
    }
}
