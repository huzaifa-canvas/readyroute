<?php

namespace App\Support;

/**
 * "Tom Bradley" -> "Tom B."
 *
 * The phone screens print the driver this way on every trip card, where the
 * full name would wrap onto a second line beside the time.
 */
class ShortName
{
    public static function of(?string $name): ?string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        $parts = preg_split('/\s+/', $name);

        if (count($parts) === 1) {
            return $parts[0];
        }

        return $parts[0] . ' ' . mb_strtoupper(mb_substr(end($parts), 0, 1)) . '.';
    }
}
