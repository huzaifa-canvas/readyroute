<?php

namespace App\Http\Controllers\Api\Dispatcher;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Two views of the same work: the calendar, and who is carrying what.
 *
 * Both hand off to the trip list with the same filters already applied, so a
 * count and the list it came from can never disagree.
 */
class ScheduleController extends BaseDispatcherController
{
    /**
     * Trips for a month, as calendar entries.
     */
    public function calendar(Request $request): JsonResponse
    {
        $from = $this->date($request->input('from')) ?? now()->startOfMonth();
        $to   = $this->date($request->input('to')) ?? (clone $from)->endOfMonth();

        $trips = $this->trips()
            ->with(['driver', 'vehicle'])
            ->whereBetween('pickup_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('pickup_date')
            ->orderBy('pickup_time')
            ->get();

        return $this->ok([
            'from'   => $from->toDateString(),
            'to'     => $to->toDateString(),

            'events' => $trips->map(fn ($trip) => [
                'id'        => $trip->id,
                'reference' => $trip->reference(),
                'title'     => $trip->passengerName(),
                'date'      => optional($trip->pickup_date)->toDateString(),
                'time'      => $trip->pickup_time,
                'start'     => optional($trip->scheduledPickupAt())->toIso8601String(),
                'status'       => $trip->statusEnum()?->value,
                'status_label' => $trip->statusEnum()?->label(),
                'driver'    => $trip->driver?->name,
                'driver_id' => $trip->driver_id,
                'vehicle'   => $trip->vehicle?->name,
                'pickup'    => $trip->pickup_address,
                'dropoff'   => $trip->dropoff_address,
            ])->values(),

            // A dot per day, so the month grid can be drawn without walking
            // the whole event list on the phone.
            'counts_by_date' => $trips
                ->groupBy(fn ($trip) => optional($trip->pickup_date)->toDateString())
                ->map->count(),
        ]);
    }

    /**
     * Trips per driver over a day or a span.
     *
     * A single day is just a range whose ends match, which keeps the counts,
     * the links and the arrows from each needing their own version.
     */
    public function driverLoad(Request $request): JsonResponse
    {
        $from = $this->date($request->input('from')) ?? today();
        $to   = $this->date($request->input('to')) ?? (clone $from);

        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        $span = (int) $from->diffInDays($to) + 1;

        $trips = $this->trips()
            ->whereBetween('pickup_date', [$from->toDateString(), $to->toDateString()])
            ->get();

        $byDriver = $trips->groupBy('driver_id');

        $rows = $this->drivers()->with('assignedVehicle')->orderBy('name')->get()
            ->map(function (User $driver) use ($byDriver, $from, $to) {
                $theirs = $byDriver->get($driver->id, collect());

                return [
                    'driver' => [
                        'id'         => $driver->id,
                        'name'       => $driver->name,
                        'code'       => $driver->driver_code,
                        'avatar_url' => $driver->avatar_url,
                        'vehicle'    => $driver->assignedVehicle?->name,
                    ],
                    'total'     => $theirs->count(),
                    'completed' => $theirs->filter(fn ($trip) => $trip->isStatus(\App\Enums\TripStatus::Completed))->count(),
                    'cancelled' => $theirs->filter(fn ($trip) => $trip->isCancelled())->count(),

                    // What "View list" should ask the trip endpoint for, so
                    // the list it opens is exactly the one that was counted.
                    'list_filter' => [
                        'driver_id' => $driver->id,
                        'date_from' => $from->toDateString(),
                        'date_to'   => $to->toDateString(),
                    ],
                ];
            })
            ->sortByDesc('total')
            ->values();

        return $this->ok([
            'from' => $from->toDateString(),
            'to'   => $to->toDateString(),
            'span_days' => $span,
            'is_single_day' => $span === 1,

            'drivers' => $rows,

            'unassigned' => [
                'total' => $trips->whereNull('driver_id')->count(),
                'list_filter' => [
                    'driver_id' => 'unassigned',
                    'date_from' => $from->toDateString(),
                    'date_to'   => $to->toDateString(),
                ],
            ],

            'summary' => [
                'trips'   => $trips->count(),
                'drivers' => $rows->count(),
            ],
        ]);
    }

    private function date(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
