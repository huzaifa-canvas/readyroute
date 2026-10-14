<?php

namespace App\Http\Controllers\Api\Dispatcher;

use App\Http\Resources\Dispatcher\TripListResource;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * The live map, and one driver's position in detail.
 *
 * Positions are real fixes reported by the driver app. A driver who has never
 * sent one is left off the map rather than dropped at (0, 0) in the Atlantic,
 * and still appears in the list below it so the office knows they exist.
 */
class LiveMapController extends BaseDispatcherController
{
    public function index(): JsonResponse
    {
        $drivers = $this->drivers()
            ->withCount('activeTrips')
            ->with(['assignedVehicle'])
            ->orderBy('name')
            ->get();

        // One query for every driver's current run rather than one per row.
        $activeTrips = $this->trips()
            ->active()
            ->with('client')
            ->whereNotNull('driver_id')
            ->get()
            ->keyBy('driver_id');

        $rows = $drivers->map(function (User $driver) use ($activeTrips) {
            $trip = $activeTrips->get($driver->id);

            return [
                'id'         => $driver->id,
                'name'       => $driver->name,
                'avatar_url' => $driver->avatar_url,
                'duty'       => $driver->dutyState(),
                'duty_label' => $driver->dutyLabel(),

                // The line under the name: "Trip #1042 · En Route", or what
                // they are doing instead.
                'subtitle' => $trip
                    ? 'Trip ' . $trip->reference() . ' · ' . ($trip->statusEnum()?->label() ?? '')
                    : 'No active trip',

                'trip' => $trip ? [
                    'id'        => $trip->id,
                    'reference' => $trip->reference(),
                    'status'    => $trip->statusEnum()?->value,
                    'status_label' => $trip->statusEnum()?->label(),
                ] : null,

                'position' => $driver->last_lat !== null && $driver->last_lng !== null ? [
                    'lat' => (float) $driver->last_lat,
                    'lng' => (float) $driver->last_lng,
                    'at'  => optional($driver->last_location_at)->toIso8601String(),
                    'ago' => optional($driver->last_location_at)->diffForHumans(),
                ] : null,

                'vehicle' => $driver->assignedVehicle?->name,
            ];
        });

        // The three chips above the list, each with its own count.
        $enRoute   = $rows->where('duty', 'on_trip')->values();
        $available = $rows->where('duty', 'active')->values();
        $offDuty   = $rows->whereIn('duty', ['off_duty', 'inactive'])->values();

        return $this->ok([
            'drivers' => $rows->values(),

            'filters' => [
                ['value' => 'on_trip',   'label' => 'En Route',  'count' => $enRoute->count()],
                ['value' => 'active',    'label' => 'Available', 'count' => $available->count()],
                ['value' => 'off_duty',  'label' => 'Off Duty',  'count' => $offDuty->count()],
            ],

            // Pickup and drop-off pins for every run still under way.
            'trip_points' => $this->trips()->active()->get()->flatMap(function (Trip $trip) {
                $points = [];

                if ($trip->pickup_lat !== null && $trip->pickup_lng !== null) {
                    $points[] = [
                        'kind'  => 'pickup',
                        'trip_id' => $trip->id,
                        'lat'   => (float) $trip->pickup_lat,
                        'lng'   => (float) $trip->pickup_lng,
                        'title' => 'Pickup: ' . $trip->passengerName(),
                        'note'  => $trip->pickup_address,
                    ];
                }

                if ($trip->dropoff_lat !== null && $trip->dropoff_lng !== null) {
                    $points[] = [
                        'kind'  => 'dropoff',
                        'trip_id' => $trip->id,
                        'lat'   => (float) $trip->dropoff_lat,
                        'lng'   => (float) $trip->dropoff_lng,
                        'title' => 'Drop-off: ' . $trip->passengerName(),
                        'note'  => $trip->dropoff_address,
                    ];
                }

                return $points;
            })->values(),

            'summary' => [
                'drivers_online' => $drivers->filter->isCurrentlyOnline()->count(),
                'active_trips'   => $this->trips()->active()->count(),
            ],

            'as_of' => now()->toIso8601String(),
        ]);
    }

    /**
     * One driver's position, their trail and what they are carrying.
     */
    public function driver(int $id): JsonResponse
    {
        $driver = $this->drivers()->withCount('activeTrips')->with('assignedVehicle')->find($id);

        if (! $driver) {
            return $this->notFound('Driver not found.');
        }

        $trip = Trip::forDriver($driver->id)->active()->with(['client', 'driver', 'vehicle'])->first();

        return $this->ok([
            'driver' => [
                'id'         => $driver->id,
                'name'       => $driver->name,
                'code'       => $driver->driver_code,
                'phone'      => $driver->phone_number,
                'avatar_url' => $driver->avatar_url,
                'duty'       => $driver->dutyState(),
                'duty_label' => $driver->dutyLabel(),
                'is_online'  => $driver->isCurrentlyOnline(),
                'vehicle'    => $driver->assignedVehicle?->name,
            ],

            'position' => $driver->last_lat !== null && $driver->last_lng !== null ? [
                'lat' => (float) $driver->last_lat,
                'lng' => (float) $driver->last_lng,
                'at'  => optional($driver->last_location_at)->toIso8601String(),
                'ago' => optional($driver->last_location_at)->diffForHumans(),
            ] : null,

            // Newest first, so the app can draw the line it just travelled.
            'trail' => $driver->locations()->latest()->limit(50)->get()->map(fn ($point) => [
                'lat' => (float) $point->lat,
                'lng' => (float) $point->lng,
                'at'  => optional($point->created_at)->toIso8601String(),
            ]),

            'current_trip' => $trip ? new TripListResource($trip) : null,
        ]);
    }
}
