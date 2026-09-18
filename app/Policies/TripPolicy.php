<?php

namespace App\Policies;

use App\Models\Trip;
use App\Models\User;

/**
 * Trips carry patient names, phone numbers and medical requirements, so access
 * is checked per record rather than relying on the route prefix alone.
 */
class TripPolicy
{
    public function view(User $user, Trip $trip): bool
    {
        if ($user->isDriver()) {
            return $trip->driver_id === $user->id;
        }

        if ($user->isDispatcher()) {
            return $trip->dispatcher_id === $user->id;
        }

        return $user->isAdmin();
    }

    /**
     * Only the assigned driver may move a trip through its lifecycle, and only
     * while it is still theirs.
     */
    public function updateStatus(User $user, Trip $trip): bool
    {
        return $user->isDriver() && $trip->driver_id === $user->id;
    }

    public function sign(User $user, Trip $trip): bool
    {
        return $this->updateStatus($user, $trip);
    }

    public function update(User $user, Trip $trip): bool
    {
        if ($user->isDispatcher()) {
            return $trip->dispatcher_id === $user->id;
        }

        return $user->isAdmin();
    }

    public function delete(User $user, Trip $trip): bool
    {
        return $this->update($user, $trip);
    }
}
