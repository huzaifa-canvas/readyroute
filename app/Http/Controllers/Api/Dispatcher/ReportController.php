<?php

namespace App\Http\Controllers\Api\Dispatcher;

use App\Enums\TripStatus;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Operational reporting.
 *
 * Deliberately about operations rather than money: trips, punctuality, driver
 * and vehicle workload. Billing is its own screen and its own data.
 */
class ReportController extends BaseDispatcherController
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'period' => ['nullable', 'in:today,week,month,custom'],
            'from'   => ['nullable', 'date'],
            'to'     => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        [$from, $to] = $this->window($request);

        $trips = $this->trips()
            ->whereBetween('pickup_date', [$from->toDateString(), $to->toDateString()])
            ->with(['driver', 'vehicle', 'client'])
            ->get();

        $completed = $trips->filter(fn (Trip $t) => $t->isStatus(TripStatus::Completed));
        $cancelled = $trips->filter(fn (Trip $t) => $t->isStatus(TripStatus::Cancelled));

        // Punctuality is only meaningful for trips that were actually scored.
        $scored = $completed->whereNotNull('was_on_time');
        $onTime = $scored->where('was_on_time', true);

        return $this->ok([
            'period' => [
                'name' => $request->input('period', 'month'),
                'from' => $from->toDateString(),
                'to'   => $to->toDateString(),
            ],

            // The four tiles at the top of the screen.
            'summary' => [
                'total'       => $trips->count(),
                'completed'   => $completed->count(),
                'cancelled'   => $cancelled->count(),
                'in_progress' => $trips->filter(fn (Trip $t) => ! ($t->statusEnum()?->isTerminal() ?? true))->count(),
                'unassigned'  => $trips->whereNull('driver_id')->count(),
                'completion_rate' => $trips->count() > 0
                    ? round($completed->count() / $trips->count() * 100)
                    : 0,
                // Null, not zero: nothing was scored, which is not the same
                // as everything being late.
                'on_time_rate' => $scored->count() > 0
                    ? round($onTime->count() / $scored->count() * 100)
                    : null,
                'scored'       => $scored->count(),
                'miles'        => round((float) $completed->sum('actual_distance'), 1),
                'avg_duration' => $completed->whereNotNull('actual_duration_min')->avg('actual_duration_min'),
            ],

            // The "Breakdown" rows, with the empty statuses left out.
            'by_status' => collect(TripStatus::cases())
                ->map(fn (TripStatus $status) => [
                    'value' => $status->value,
                    'label' => $status->label(),
                    'count' => $trips->filter(fn (Trip $t) => $t->isStatus($status))->count(),
                ])
                ->filter(fn ($row) => $row['count'] > 0)
                ->values(),

            // Daily volume, with empty days filled in so the shape is honest.
            'by_day' => $this->days($from, $to, $trips),

            'drivers' => $this->drivers()->orderBy('name')->get()
                ->map(function (User $driver) use ($trips) {
                    $theirs   = $trips->where('driver_id', $driver->id);
                    $done     = $theirs->filter(fn (Trip $t) => $t->isStatus(TripStatus::Completed));
                    $punctual = $done->whereNotNull('was_on_time');

                    return [
                        'id'        => $driver->id,
                        'name'      => $driver->name,
                        'assigned'  => $theirs->count(),
                        'completed' => $done->count(),
                        'miles'     => round((float) $done->sum('actual_distance'), 1),
                        'on_time_rate' => $punctual->count() > 0
                            ? round($punctual->where('was_on_time', true)->count() / $punctual->count() * 100)
                            : null,
                    ];
                })
                ->sortByDesc('completed')
                ->values(),

            'vehicles' => $this->vehicles()->orderBy('name')->get()
                ->map(fn (Vehicle $vehicle) => [
                    'id'    => $vehicle->id,
                    'name'  => $vehicle->name,
                    'trips' => $trips->where('vehicle_id', $vehicle->id)->count(),
                    'miles' => round((float) $trips->where('vehicle_id', $vehicle->id)->sum('actual_distance'), 1),
                ])
                ->sortByDesc('trips')
                ->values(),

            'top_clients' => $trips->groupBy('client_id')
                ->map(fn ($set) => [
                    'client_id' => $set->first()->client_id,
                    'name'      => $set->first()->passengerName(),
                    'count'     => $set->count(),
                ])
                ->sortByDesc('count')
                ->take(10)
                ->values(),
        ]);
    }

    /**
     * The same window as a CSV, streamed so a long range never has to be held
     * in memory first.
     */
    public function export(Request $request): StreamedResponse
    {
        [$from, $to] = $this->window($request);

        $companyId = $this->companyId();
        $filename  = 'trips-' . $from->toDateString() . '-to-' . $to->toDateString() . '.csv';

        return response()->streamDownload(function () use ($companyId, $from, $to) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Reference', 'Pickup date', 'Pickup time', 'Passenger', 'Phone',
                'Pickup address', 'Drop-off address', 'Driver', 'Vehicle',
                'Status', 'On time', 'Actual miles', 'Duration (min)', 'Completed at',
            ]);

            Trip::where('dispatcher_id', $companyId)
                ->whereBetween('pickup_date', [$from->toDateString(), $to->toDateString()])
                ->with(['driver', 'vehicle'])
                ->orderBy('pickup_date')
                ->chunk(200, function ($chunk) use ($handle) {
                    foreach ($chunk as $trip) {
                        fputcsv($handle, [
                            $trip->reference(),
                            optional($trip->pickup_date)->toDateString(),
                            $trip->pickup_time,
                            $trip->passengerName(),
                            $trip->phone_number,
                            $trip->pickup_address,
                            $trip->dropoff_address,
                            $trip->driver?->name,
                            $trip->vehicle?->name,
                            $trip->statusEnum()?->label(),
                            $trip->was_on_time === null ? '' : ($trip->was_on_time ? 'Yes' : 'No'),
                            $trip->actual_distance,
                            $trip->actual_duration_min,
                            optional($trip->completed_at)->format('Y-m-d H:i'),
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    // ── Shared pieces ────────────────────────────────────────────────

    /**
     * The window the screen's chips ask for, or an explicit pair of dates.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function window(Request $request): array
    {
        return match ($request->input('period')) {
            'today' => [now()->startOfDay(), now()->endOfDay()],
            'week'  => [now()->startOfWeek(), now()->endOfWeek()],
            'month' => [now()->startOfMonth(), now()->endOfMonth()],
            default => [
                $request->filled('from')
                    ? Carbon::parse($request->input('from'))->startOfDay()
                    : now()->startOfMonth(),
                $request->filled('to')
                    ? Carbon::parse($request->input('to'))->endOfDay()
                    : now()->endOfMonth(),
            ],
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function days(Carbon $from, Carbon $to, $trips): array
    {
        $rows = [];

        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $key = $day->toDateString();

            $rows[] = [
                'date'  => $key,
                'count' => $trips->filter(
                    fn (Trip $trip) => optional($trip->pickup_date)->toDateString() === $key
                )->count(),
            ];
        }

        return $rows;
    }
}
