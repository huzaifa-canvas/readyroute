<?php

namespace App\Support;

class Geo
{
    private const EARTH_RADIUS_MILES = 3958.8;

    /**
     * Great-circle distance in miles between two points.
     *
     * This is the raw straight-line figure. Estimating how far a driver will
     * have to travel scales it up by a road factor, but measuring how far they
     * actually drove does not: GPS breadcrumbs already follow the road, so
     * scaling a sum of breadcrumbs would count the detour twice.
     */
    public static function haversineMiles(float $fromLat, float $fromLng, float $toLat, float $toLng): float
    {
        $latDelta = deg2rad($toLat - $fromLat);
        $lngDelta = deg2rad($toLng - $fromLng);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($fromLat)) * cos(deg2rad($toLat)) * sin($lngDelta / 2) ** 2;

        return self::EARTH_RADIUS_MILES * 2 * asin(min(1.0, sqrt($a)));
    }

    /**
     * Total distance along an ordered list of points, each ['lat' => , 'lng' => ].
     *
     * Consecutive points closer together than the jitter threshold are skipped:
     * a stationary phone reports slightly different fixes every few seconds,
     * and summing that noise would add miles to a parked vehicle.
     */
    public static function pathMiles(iterable $points, float $jitterMiles = 0.01): float
    {
        $total = 0.0;
        $previous = null;

        foreach ($points as $point) {
            $lat = isset($point['lat']) ? (float) $point['lat'] : null;
            $lng = isset($point['lng']) ? (float) $point['lng'] : null;

            if ($lat === null || $lng === null) {
                continue;
            }

            if ($previous !== null) {
                $leg = self::haversineMiles($previous[0], $previous[1], $lat, $lng);

                if ($leg >= $jitterMiles) {
                    $total += $leg;
                    $previous = [$lat, $lng];
                }

                continue;
            }

            $previous = [$lat, $lng];
        }

        return round($total, 2);
    }
}
