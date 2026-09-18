<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\Builder;

/**
 * Shared base for every driver endpoint. It exists mainly so that no controller
 * has to remember to scope its own queries: trips are always reached through
 * driverTrips(), which is already filtered to the signed-in driver.
 */
abstract class BaseDriverController extends Controller
{
    use ApiResponse;

    protected function driver(): User
    {
        return auth()->user();
    }

    /**
     * Every trip query in the driver API starts here.
     */
    protected function driverTrips(): Builder
    {
        return Trip::query()->forDriver($this->driver()->id);
    }

    /**
     * Load one of this driver's trips, or fail with a 404 rather than a 403 —
     * a driver should not be able to discover that a trip ID exists at all.
     */
    protected function findTrip(int|string $id): ?Trip
    {
        return $this->driverTrips()->find($id);
    }

    /**
     * The dispatcher company this driver belongs to.
     */
    protected function companyId(): ?int
    {
        return $this->driver()->dispatcher_id;
    }
}
