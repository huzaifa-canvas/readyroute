<?php

namespace App\Notifications;

use App\Models\Trip;

class TripCancelledNotification extends DriverNotification
{
    public function __construct(private readonly Trip $trip)
    {
    }

    public function kind(): string
    {
        return 'trip_cancelled';
    }

    public function title(): string
    {
        return 'Trip Cancelled';
    }

    public function body(): string
    {
        return sprintf('%s has been cancelled.', $this->trip->passengerName());
    }

    public function payload(): array
    {
        return [
            'trip_id'   => $this->trip->id,
            'reference' => $this->trip->reference(),
        ];
    }
}
