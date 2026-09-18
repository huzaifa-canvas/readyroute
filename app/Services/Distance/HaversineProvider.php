<?php

namespace App\Services\Distance;

/**
 * Straight-line distance, no API key required. Real driving distance is always
 * longer than the great-circle line, so the result is scaled by a road factor
 * and turned into a rough ETA using an average speed. Both are configurable in
 * config/readyroute.php.
 *
 * This is deliberately an approximation. Swapping the provider to Google in
 * config replaces it with real road distance without changing any caller.
 */
class HaversineProvider implements DistanceProvider
{
    private const EARTH_RADIUS_MILES = 3958.8;

    public function __construct(
        private readonly float $roadFactor,
        private readonly float $averageSpeedMph,
    ) {}

    public function between(?float $fromLat, ?float $fromLng, ?float $toLat, ?float $toLng): DistanceEstimate
    {
        if ($fromLat === null || $fromLng === null || $toLat === null || $toLng === null) {
            return DistanceEstimate::unknown();
        }

        $latDelta = deg2rad($toLat - $fromLat);
        $lngDelta = deg2rad($toLng - $fromLng);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($fromLat)) * cos(deg2rad($toLat)) * sin($lngDelta / 2) ** 2;

        $straightLine = self::EARTH_RADIUS_MILES * 2 * asin(min(1.0, sqrt($a)));
        $miles = round($straightLine * $this->roadFactor, 1);

        $minutes = $this->averageSpeedMph > 0
            ? (int) max(1, round(($miles / $this->averageSpeedMph) * 60))
            : null;

        return new DistanceEstimate($miles, $minutes, true);
    }
}
