<?php

namespace App\Enums;

/**
 * The per-item result on a pre-trip inspection, matching the three icons on
 * the DVIR screen: a green check, an amber warning, and a pending clock.
 */
enum InspectionItemStatus: string
{
    case Pending = 'pending';
    case Pass    = 'pass';
    case Fail    = 'fail';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Not Checked',
            self::Pass    => 'Pass',
            self::Fail    => 'Defect Found',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
