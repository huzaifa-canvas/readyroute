<?php

namespace App\Notifications;

use App\Models\Trip;

/**
 * Shown in the app as "Route Change".
 */
class RouteChangeNotification extends DriverNotification
{
    public function __construct(
        private readonly Trip $trip,
        private readonly string $reason = '',
    ) {
    }

    public function kind(): string
    {
        return 'route_change';
    }

    public function title(): string
    {
        return 'Route Change';
    }

    public function body(): string
    {
        return $this->reason !== ''
            ? $this->reason
            : sprintf('The route for %s has been updated.', $this->trip->passengerName());
    }

    public function payload(): array
    {
        return [
            'trip_id'   => $this->trip->id,
            'reference' => $this->trip->reference(),
        ];
    }
}
