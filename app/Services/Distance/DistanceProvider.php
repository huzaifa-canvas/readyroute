<?php

namespace App\Services\Distance;

interface DistanceProvider
{
    /**
     * Distance and travel time between two points. Implementations return
     * DistanceEstimate::unknown() rather than throwing when either coordinate
     * is missing, so a trip without geocoded addresses still renders.
     */
    public function between(?float $fromLat, ?float $fromLng, ?float $toLat, ?float $toLng): DistanceEstimate;
}
