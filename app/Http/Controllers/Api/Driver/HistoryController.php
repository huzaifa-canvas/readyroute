<?php

namespace App\Http\Controllers\Api\Driver;

use App\Enums\TripStatus;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class HistoryController extends BaseDriverController
{
    /**
     * Past trips and the performance figures above them.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'range'    => ['sometimes', 'string', 'in:today,week,month'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $range = $request->input('range', 'week');
        [$from, $to] = $this->window($range);

        $finished = $this->driverTrips()
            ->whereBetween('pickup_date', [$from->toDateString(), $to->toDateString()])
            ->whereIn('status', [
                TripStatus::Completed->value,
                TripStatus::Cancelled->value,
            ]);

        $completed = (clone $finished)->where('status', TripStatus::Completed->value)->count();
        $cancelled = (clone $finished)->where('status', TripStatus::Cancelled->value)->count();

        return $this->ok([
            'range' => [
                'key'  => $range,
                'from' => $from->toDateString(),
                'to'   => $to->toDateString(),
            ],

            'stats' => [
                'total_trips'  => $completed + $cancelled,
                'completed'    => $completed,
                'cancelled'    => $cancelled,
                'on_time_rate' => $this->onTimeRate($from, $to),
            ],

            'trips' => $this->pastTrips($request, $from, $to),
        ]);
    }

    /**
     * Share of completed trips that reached the pickup within the grace period.
     *
     * Only trips that actually reached a pickup count, so a cancelled run
     * neither helps nor hurts the driver, and a trip still in progress is not
     * judged before it is finished.
     */
    private function onTimeRate(Carbon $from, Carbon $to): ?int
    {
        $rated = $this->driverTrips()
            ->whereBetween('pickup_date', [$from->toDateString(), $to->toDateString()])
            ->where('status', TripStatus::Completed->value)
            ->whereNotNull('was_on_time');

        $total = (clone $rated)->count();

        if ($total === 0) {
            return null;
        }

        $onTime = (clone $rated)->where('was_on_time', true)->count();

        return (int) round(($onTime / $total) * 100);
    }

    private function pastTrips(Request $request, Carbon $from, Carbon $to): array
    {
        $trips = $this->driverTrips()
            ->whereBetween('pickup_date', [$from->toDateString(), $to->toDateString()])
            ->whereIn('status', [
                TripStatus::Completed->value,
                TripStatus::Cancelled->value,
            ])
            ->orderBy('pickup_date', 'desc')
            ->orderBy('pickup_time', 'desc')
            ->paginate($request->integer('per_page') ?: 20);

        return [
            'data' => $trips->getCollection()->map(fn (Trip $trip) => [
                'id'          => $trip->id,
                'reference'   => $trip->reference(),
                'title'       => 'Trip ' . $trip->reference() . ' - ' . $trip->passengerShortName(),
                'passenger'   => $trip->passengerShortName(),
                'status'      => $trip->statusEnum()?->value,
                'status_label'=> $trip->statusEnum()?->label(),
                'date'        => optional($trip->pickup_date)->toDateString(),
                'when'        => $this->whenLabel($trip),
                'duration_minutes' => $trip->actual_duration_min,
                'distance_miles'   => $trip->actual_distance !== null ? (float) $trip->actual_distance : null,
                'on_time'     => $trip->was_on_time,
            ])->values(),

            'meta' => [
                'current_page' => $trips->currentPage(),
                'last_page'    => $trips->lastPage(),
                'per_page'     => $trips->perPage(),
                'total'        => $trips->total(),
                'has_more'     => $trips->hasMorePages(),
            ],
        ];
    }

    /**
     * Today's trips show a time; anything older shows how long ago it was,
     * which is what the history list reads like.
     */
    private function whenLabel(Trip $trip): string
    {
        $date = $trip->pickup_date;

        if (! $date) {
            return '';
        }

        if ($date->isToday()) {
            return Carbon::parse($trip->pickup_time ?: '00:00')->format('g:i A');
        }

        if ($date->isYesterday()) {
            return 'Yesterday';
        }

        return $date->format('M j');
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function window(string $range): array
    {
        return match ($range) {
            'today' => [now()->startOfDay(), now()->endOfDay()],
            'month' => [now()->startOfMonth(), now()->endOfMonth()],
            default => [now()->startOfWeek(), now()->endOfWeek()],
        };
    }
}
