<?php

namespace App\Enums;

/**
 * Why a trip was cancelled.
 *
 * A cancelled trip stays on the record, so the reason stays with it: it is what
 * a later question — a dispute, a no-show pattern, a billing query — is actually
 * asking about. The dispatcher must pick one when they cancel; "Other" carries a
 * free-text note alongside it for anything these do not cover.
 */
enum CancellationReason: string
{
    case ClientNoShow      = 'client_no_show';
    case ClientCancelled   = 'client_cancelled';
    case DriverUnavailable = 'driver_unavailable';
    case VehicleIssue      = 'vehicle_issue';
    case WeatherEmergency  = 'weather_emergency';
    case DuplicateTrip     = 'duplicate_trip';
    case Other             = 'other';

    /**
     * Human label as shown in the cancel dialog and on the trip record.
     */
    public function label(): string
    {
        return match ($this) {
            self::ClientNoShow      => 'Client No-Show',
            self::ClientCancelled   => 'Client Cancelled',
            self::DriverUnavailable => 'Driver Unavailable',
            self::VehicleIssue      => 'Vehicle Issue',
            self::WeatherEmergency  => 'Weather / Emergency',
            self::DuplicateTrip     => 'Duplicate Trip',
            self::Other             => 'Other',
        };
    }

    /**
     * Whether this reason expects the free-text note to be filled in.
     */
    public function needsNote(): bool
    {
        return $this === self::Other;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
