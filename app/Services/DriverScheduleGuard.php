<?php

namespace App\Services;

use App\Enums\TripStatus;
use App\Models\Trip;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Guards against double-booking a driver.
 *
 * A driver should not quietly be put on two trips stacked on top of each other:
 * there has to be room to finish one run and reach the next. This finds the
 * trips a proposed assignment would clash with and phrases the clash for the
 * warning the dispatcher sees.
 *
 * It never refuses on its own. The dispatcher can force an assignment through,
 * so this only ever reports — the caller decides whether a clash stops the
 * save. That is why every path that sets a driver consults it, and every path
 * also accepts a force flag that skips it.
 *
 * "Clash" is deliberately narrow: only trips still on the driver's plate count.
 * A completed or cancelled trip is history and never stands in the way of new
 * work, which is why the window is tested against active statuses alone.
 */
class DriverScheduleGuard
{
    /**
     * Other active trips for this driver whose pickup falls inside the warning
     * window around the proposed pickup, soonest first. Empty when the slot is
     * clear, or when there is not enough to judge (no driver, or no pickup
     * time yet).
     *
     * @return Collection<int, Trip>
     */
    public function conflicts(
        int $companyId,
        ?int $driverId,
        ?Carbon $pickupAt,
        ?int $excludeTripId = null
    ): Collection {
        if (! $driverId || ! $pickupAt) {
            return collect();
        }

        [$start, $end] = $this->window($pickupAt);

        return Trip::query()
            ->where('dispatcher_id', $companyId)
            ->where('driver_id', $driverId)
            ->whereIn('status', TripStatus::activeValues())
            ->when($excludeTripId, fn ($q) => $q->whereKeyNot($excludeTripId))
            ->whereNotNull('pickup_time')
            // The date column is filtered in SQL to keep the set tiny; the exact
            // window — which can straddle midnight — is applied below against
            // the combined pickup datetime, so it stays correct on MySQL and
            // SQLite alike without concatenating date and time in SQL.
            ->whereBetween('pickup_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->filter(function (Trip $trip) use ($start, $end) {
                $at = $trip->scheduledPickupAt();

                return $at && $at->between($start, $end);
            })
            ->sortBy(fn (Trip $trip) => $trip->scheduledPickupAt()?->timestamp)
            ->values();
    }

    /**
     * A single sentence naming the first clash — for the warning dialog, and
     * for the plain error returned when a clash is not forced through.
     */
    public function message(Collection $conflicts, Carbon $pickupAt): string
    {
        if ($conflicts->isEmpty()) {
            return '';
        }

        /** @var Trip $first */
        $first = $conflicts->first();
        $name  = $first->driver?->name ?: 'This driver';
        $when  = $first->scheduledPickupAt();

        $line = $name . ' already has ' . $first->reference()
            . ' (' . $first->passengerName() . ') at '
            . ($when ? $when->format('M j, g:i A') : 'another time')
            . ' — ' . $this->relative($when, $pickupAt) . '.';

        if ($conflicts->count() > 1) {
            $line .= ' And ' . ($conflicts->count() - 1) . ' more nearby.';
        }

        return $line;
    }

    /**
     * The clash in the shape the panel's dialog and the mobile app both render:
     * the sentence, whether it can be forced, and the trips themselves so each
     * front end can list them.
     *
     * @param  Collection<int, Trip>  $conflicts
     * @return array{conflict: bool, can_force: bool, message: string, conflicts: array<int, array<string, mixed>>}
     */
    public function payload(Collection $conflicts, Carbon $pickupAt): array
    {
        return [
            'conflict'  => $conflicts->isNotEmpty(),
            'can_force' => true,
            'message'   => $this->message($conflicts, $pickupAt),
            'conflicts' => $conflicts->map(fn (Trip $trip) => [
                'id'           => $trip->id,
                'reference'    => $trip->reference(),
                'passenger'    => $trip->passengerName(),
                'pickup_at'    => $trip->scheduledPickupAt()?->toIso8601String(),
                'pickup_label' => $trip->scheduledPickupAt()?->format('M j, g:i A'),
                'relation'     => $this->relative($trip->scheduledPickupAt(), $pickupAt),
                'status'       => $trip->statusEnum()?->value,
            ])->all(),
        ];
    }

    /**
     * Combine the separate pickup date and time a form posts into one instant,
     * or null when either is missing or unparseable. The time arrives in
     * whatever shape the picker used (e.g. "02:30 PM"), so it is normalised
     * here rather than trusted as-is.
     */
    public function pickupAt(mixed $date, mixed $time): ?Carbon
    {
        if (blank($date) || blank($time)) {
            return null;
        }

        try {
            return Carbon::parse(
                Carbon::parse($date)->toDateString() . ' ' . Carbon::parse($time)->format('H:i:s')
            );
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function window(Carbon $pickupAt): array
    {
        $before = (int) config('readyroute.assignment.gap_before_minutes', 60);
        $after  = (int) config('readyroute.assignment.gap_after_minutes', 120);

        return [
            $pickupAt->copy()->subMinutes($before),
            $pickupAt->copy()->addMinutes($after),
        ];
    }

    /**
     * How the clashing trip sits against the one being booked, in words:
     * "45 minutes before this pickup", "2 hours after this pickup", or "at the
     * same time as this pickup".
     */
    private function relative(?Carbon $other, Carbon $pickupAt): string
    {
        if (! $other) {
            return 'around this time';
        }

        $minutes = (int) round(abs($other->diffInMinutes($pickupAt)));

        if ($minutes === 0) {
            return 'at the same time as this pickup';
        }

        return $this->humanGap($minutes)
            . ($other->lessThan($pickupAt) ? ' before this pickup' : ' after this pickup');
    }

    /**
     * A plain-words gap, e.g. "1 hour 30 minutes" or "45 minutes".
     */
    private function humanGap(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes . ' ' . Str::plural('minute', $minutes);
        }

        $hours     = intdiv($minutes, 60);
        $remainder = $minutes % 60;

        $label = $hours . ' ' . Str::plural('hour', $hours);

        if ($remainder > 0) {
            $label .= ' ' . $remainder . ' ' . Str::plural('minute', $remainder);
        }

        return $label;
    }
}
