<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Requests\Driver\DeviceTokenRequest;
use App\Http\Resources\Driver\DriverResource;
use Illuminate\Http\JsonResponse;

class ProfileController extends BaseDriverController
{
    /**
     * The signed-in driver, their company and the vehicle they are assigned.
     * The app calls this on launch to restore session state.
     */
    public function show(): JsonResponse
    {
        $driver = $this->driver()->load(['assignedVehicle', 'dispatcher', 'metas']);

        // Any authenticated call is evidence the driver is present.
        $driver->forceFill([
            'is_online'    => true,
            'last_seen_at' => now(),
        ])->saveQuietly();

        return $this->ok(new DriverResource($driver));
    }

    /**
     * Store the Firebase token. Nothing reads it until push is connected, but
     * collecting it now means push needs no further app release.
     */
    public function storeDeviceToken(DeviceTokenRequest $request): JsonResponse
    {
        $this->driver()->forceFill([
            'device_token' => $request->device_token,
        ])->save();

        return $this->ok(null, $request->device_token
            ? 'Device registered for notifications.'
            : 'Device unregistered from notifications.');
    }
}
