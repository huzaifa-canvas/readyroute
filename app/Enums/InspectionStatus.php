<?php

namespace App\Enums;

/**
 * An inspection stays a draft while the driver works through the checklist and
 * is locked once submitted with a signature.
 */
enum InspectionStatus: string
{
    case Draft     = 'draft';
    case Submitted = 'submitted';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
