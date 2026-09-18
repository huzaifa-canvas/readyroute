<?php

namespace App\Enums;

/**
 * How a trip confirmation was obtained. Only "manual" is produced today; the
 * "ivr" case exists so the automated confirmation call can be added later
 * without a migration.
 */
enum ConfirmationSource: string
{
    case Manual = 'manual';
    case Ivr    = 'ivr';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
