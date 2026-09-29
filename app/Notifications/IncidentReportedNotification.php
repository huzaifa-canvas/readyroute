<?php

namespace App\Notifications;

use App\Models\TripIncident;

/**
 * Tells the office a driver has reported something.
 *
 * This is the one notification in the set aimed at dispatchers rather than
 * drivers. It still extends DriverNotification because the delivery rules are
 * the same — database now, push the moment Firebase is configured — and a
 * dispatcher with the app installed should get an SOS on their phone too.
 */
class IncidentReportedNotification extends DriverNotification
{
    public function __construct(private readonly TripIncident $incident)
    {
    }

    public function kind(): string
    {
        return $this->incident->isSos() ? 'sos' : 'incident';
    }

    public function title(): string
    {
        return $this->incident->isSos()
            ? 'SOS — ' . ($this->incident->driver?->name ?? 'A driver')
            : 'Incident reported';
    }

    public function body(): string
    {
        $driver = $this->incident->driver?->name ?? 'A driver';

        if ($this->incident->isSos()) {
            return $driver . ' has raised an emergency alert. Open it to see their position.';
        }

        $trip = $this->incident->trip
            ? ' on ' . $this->incident->trip->reference()
            : '';

        return $driver . ' reported ' . strtolower($this->incident->typeLabel()) . $trip . '.';
    }

    public function payload(): array
    {
        return [
            'incident_id' => $this->incident->id,
            'driver_id'   => $this->incident->driver_id,
            'trip_id'     => $this->incident->trip_id,
            'type'        => $this->incident->type,
            'severity'    => $this->incident->severity,
            'is_sos'      => $this->incident->isSos(),
            'lat'         => $this->incident->lat,
            'lng'         => $this->incident->lng,
        ];
    }
}
