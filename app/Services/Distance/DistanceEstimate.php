<?php

namespace App\Services\Distance;

/**
 * The result of a distance lookup. "is_estimate" tells the caller whether this
 * came from a real routing service or from a straight-line approximation, so
 * the app can present it honestly.
 */
class DistanceEstimate
{
    public function __construct(
        public readonly ?float $miles,
        public readonly ?int $minutes,
        public readonly bool $isEstimate = true,
    ) {}

    public static function unknown(): self
    {
        return new self(null, null, true);
    }

    public function toArray(): array
    {
        return [
            'miles'       => $this->miles,
            'minutes'     => $this->minutes,
            'is_estimate' => $this->isEstimate,
        ];
    }
}
