<?php

namespace App\Notifications;

use App\Models\Trip;

/**
 * Shown in the app as "Trip Added".
 */
class TripAssignedNotification extends DriverNotification
{
    public function __construct(private readonly Trip $trip)
    {
    }

    public function kind(): string
    {
        return 'trip_added';
    }

    public function title(): string
    {
        return 'Trip Added';
    }

    public function body(): string
    {
        $time = $this->trip->scheduledPickupAt();

        return sprintf(
            'New trip assigned: %s%s.',
            $this->trip->passengerName(),
            $time ? ' at ' . $time->format('g:i A') : ''
        );
    }

    public function payload(): array
    {
        return [
            'trip_id'   => $this->trip->id,
            'reference' => $this->trip->reference(),
        ];
    }
}
