<?php

namespace App\Services\Distance;

use App\Support\Geo;

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
    public function __construct(
        private readonly float $roadFactor,
        private readonly float $averageSpeedMph,
    ) {}

    public function between(?float $fromLat, ?float $fromLng, ?float $toLat, ?float $toLng): DistanceEstimate
    {
        if ($fromLat === null || $fromLng === null || $toLat === null || $toLng === null) {
            return DistanceEstimate::unknown();
        }

        $straightLine = Geo::haversineMiles($fromLat, $fromLng, $toLat, $toLng);
        $miles = round($straightLine * $this->roadFactor, 1);

        $minutes = $this->averageSpeedMph > 0
            ? (int) max(1, round(($miles / $this->averageSpeedMph) * 60))
            : null;

        return new DistanceEstimate($miles, $minutes, true);
    }
}
