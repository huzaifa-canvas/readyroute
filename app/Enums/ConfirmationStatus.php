<?php

namespace App\Enums;

/**
 * Whether the patient has confirmed they will be ready for the trip. This is
 * the badge shown on the driver's dashboard; the driver never sets it. It is
 * entered by the dispatcher today and will be set by an IVR call later.
 */
enum ConfirmationStatus: string
{
    case Unconfirmed = 'unconfirmed';
    case Confirmed   = 'confirmed';
    case Declined    = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::Unconfirmed => 'Unconfirmed',
            self::Confirmed   => 'Confirmed',
            self::Declined    => 'Declined',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
