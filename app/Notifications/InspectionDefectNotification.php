<?php

namespace App\Notifications;

use App\Enums\InspectionItemStatus;
use App\Models\VehicleInspection;

/**
 * Tells the office a driver signed off an inspection with defects on it.
 *
 * Aimed at dispatchers, like IncidentReportedNotification, and extends the
 * driver base for the same reason: the delivery rules are identical.
 */
class InspectionDefectNotification extends DriverNotification
{
    public function __construct(private readonly VehicleInspection $inspection)
    {
    }

    public function kind(): string
    {
        return 'inspection_defect';
    }

    public function title(): string
    {
        return 'Inspection defects reported';
    }

    public function body(): string
    {
        $driver  = $this->inspection->driver?->name ?? 'A driver';
        $vehicle = $this->inspection->vehicle?->name
            ?? $this->inspection->vehicle?->number_plate
            ?? 'their vehicle';

        $defects = $this->defectLabels();

        return $driver . ' found ' . count($defects) . ' '
            . (count($defects) === 1 ? 'defect' : 'defects')
            . ' on ' . $vehicle . ': ' . implode(', ', $defects) . '.';
    }

    public function payload(): array
    {
        return [
            'inspection_id' => $this->inspection->id,
            'driver_id'     => $this->inspection->driver_id,
            'vehicle_id'    => $this->inspection->vehicle_id,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function defectLabels(): array
    {
        return $this->inspection->responses
            ->filter(fn ($r) => $r->status === InspectionItemStatus::Fail)
            ->map(fn ($r) => $r->item?->label ?? 'Unknown item')
            ->values()
            ->all();
    }
}
