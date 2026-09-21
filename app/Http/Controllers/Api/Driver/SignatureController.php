<?php

namespace App\Http\Controllers\Api\Driver;

use App\Enums\TripStatus;
use App\Http\Requests\Driver\StoreSignatureRequest;
use App\Http\Resources\Driver\SignatureResource;
use App\Services\SignatureService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class SignatureController extends BaseDriverController
{
    public function __construct(private readonly SignatureService $signatures)
    {
    }

    /**
     * Save the driver's sign-off for a finished trip.
     */
    public function store(StoreSignatureRequest $request, string $id): JsonResponse
    {
        $trip = $this->findTrip($id);

        if (! $trip) {
            return $this->notFound('Trip not found.');
        }

        // Sign-off closes out a completed run, so there is nothing to attest to
        // before the trip is finished.
        if (! $trip->isStatus(TripStatus::Completed)) {
            return $this->fail(
                'This trip can be signed off once it has been completed.',
                422,
                ['signature' => ['This trip can be signed off once it has been completed.']]
            );
        }

        try {
            $signature = $this->signatures->store(
                trip: $trip,
                driver: $this->driver(),
                payload: $request->input('signature'),
                ip: $request->ip(),
            );
        } catch (RuntimeException $e) {
            return $this->fail($e->getMessage(), 422, [
                'signature' => [$e->getMessage()],
            ]);
        }

        $signature->load('driver');

        return $this->created(
            new SignatureResource($signature),
            'Signature saved to trip ' . $trip->reference() . '.'
        );
    }

    /**
     * The stored sign-off, with a freshly issued link to the image.
     */
    public function show(string $id): JsonResponse
    {
        $trip = $this->findTrip($id);

        if (! $trip) {
            return $this->notFound('Trip not found.');
        }

        $trip->load(['signature.driver']);

        if (! $trip->signature) {
            return $this->notFound('No signature has been recorded for this trip.');
        }

        return $this->ok(new SignatureResource($trip->signature));
    }
}
