<?php

namespace App\Http\Controllers\Api\Dispatcher;

use App\Enums\TripStatus;
use App\Http\Resources\Dispatcher\TripListResource;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/**
 * Hand the unassigned work out in one action.
 *
 * The web panel spreads trips round-robin across every driver. That is kept,
 * with one correction the panel does not make: a driver who does not work on
 * a trip's day is skipped, because assigning them would be rejected anywhere
 * else in the system and leaves a trip that looks covered but is not.
 */
class AutoDispatchController extends BaseDispatcherController
{
    public function index(): JsonResponse
    {
        $trips = $this->trips()
            ->with(['client', 'driver', 'vehicle'])
            ->whereIn('status', [TripStatus::Scheduled->value, TripStatus::InProgress->value])
            ->orderBy('pickup_date')
            ->orderBy('pickup_time')
            ->get();

        return $this->ok([
            'unassigned' => TripListResource::collection($trips->whereNull('driver_id')->values()),
            'assigned'   => TripListResource::collection($trips->whereNotNull('driver_id')->values()),
            'counts' => [
                'unassigned' => $trips->whereNull('driver_id')->count(),
                'assigned'   => $trips->whereNotNull('driver_id')->count(),
                'drivers'    => $this->drivers()->count(),
            ],
        ]);
    }

    public function optimize(): JsonResponse
    {
        $unassigned = $this->trips()
            ->whereNull('driver_id')
            ->whereIn('status', [TripStatus::Scheduled->value])
            ->orderBy('pickup_date')
            ->orderBy('pickup_time')
            ->get();

        $drivers = $this->drivers()->with(['metas', 'assignedVehicle'])->orderBy('name')->get();

        if ($unassigned->isEmpty()) {
            return $this->fail('There are no unassigned trips to work through.', 422);
        }

        if ($drivers->isEmpty()) {
            return $this->fail('There are no drivers to assign work to.', 422);
        }

        $assigned = 0;
        $skipped  = [];
        $cursor   = 0;
        $count    = $drivers->count();

        foreach ($unassigned as $trip) {
            $date = $trip->pickup_date ? Carbon::parse($trip->pickup_date) : null;

            // Walk the roster from wherever the last trip left off, so the
            // work still spreads evenly rather than piling onto whoever
            // happens to be first and available.
            $taker = null;

            for ($step = 0; $step < $count; $step++) {
                $candidate = $drivers[($cursor + $step) % $count];

                if ($candidate->isAvailableOn($date)) {
                    $taker  = $candidate;
                    $cursor = ($cursor + $step + 1) % $count;
                    break;
                }
            }

            if (! $taker) {
                $skipped[] = [
                    'trip_id'   => $trip->id,
                    'reference' => $trip->reference(),
                    'reason'    => 'Nobody works on ' . ($date?->format('l') ?? 'that day') . '.',
                ];

                continue;
            }

            $trip->update([
                'driver_id'  => $taker->id,
                'vehicle_id' => $taker->assignedVehicle?->id,
            ]);

            $assigned++;
        }

        return $this->ok([
            'assigned' => $assigned,
            'skipped'  => $skipped,
        ], $assigned > 0
            ? $assigned . ' trip(s) assigned.' . ($skipped ? ' ' . count($skipped) . ' could not be covered.' : '')
            : 'Nothing could be assigned — no driver works on those days.');
    }
}
