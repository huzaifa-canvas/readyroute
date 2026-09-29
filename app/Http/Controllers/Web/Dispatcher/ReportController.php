<?php

namespace App\Http\Controllers\Web\Dispatcher;

use App\Enums\TripStatus;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Operational reporting.
 *
 * Deliberately about operations rather than money: trips, punctuality, driver
 * and vehicle workload. Billing is its own screen and its own data.
 */
class ReportController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to'   => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $companyId = auth()->user()->companyId();

        // Default window is the current month, which is what someone opening
        // this screen almost always wants.
        $from = $request->filled('from')
            ? Carbon::parse($request->input('from'))->startOfDay()
            : now()->startOfMonth();

        $to = $request->filled('to')
            ? Carbon::parse($request->input('to'))->endOfDay()
            : now()->endOfMonth();

        $trips = Trip::where('dispatcher_id', $companyId)
            ->whereBetween('pickup_date', [$from->toDateString(), $to->toDateString()])
            ->with(['driver', 'vehicle', 'client'])
            ->get();

        $completed = $trips->filter(fn (Trip $trip) => $trip->isStatus(TripStatus::Completed));
        $cancelled = $trips->filter(fn (Trip $trip) => $trip->isStatus(TripStatus::Cancelled));

        // Punctuality is only meaningful for trips that were actually scored.
        $scored  = $completed->whereNotNull('was_on_time');
        $onTime  = $scored->where('was_on_time', true);

        $summary = [
            'total'        => $trips->count(),
            'completed'    => $completed->count(),
            'cancelled'    => $cancelled->count(),
            'in_progress'  => $trips->filter(fn (Trip $t) => ! ($t->statusEnum()?->isTerminal() ?? true))->count(),
            'completion_rate' => $trips->count() > 0
                ? round($completed->count() / $trips->count() * 100)
                : 0,
            'on_time_rate' => $scored->count() > 0
                ? round($onTime->count() / $scored->count() * 100)
                : null,
            'scored'       => $scored->count(),
            'miles'        => round((float) $completed->sum('actual_distance'), 1),
            'avg_duration' => $completed->whereNotNull('actual_duration_min')->avg('actual_duration_min'),
        ];

        // Status mix for the bar chart.
        $byStatus = collect(TripStatus::cases())
            ->map(fn (TripStatus $status) => [
                'label' => $status->label(),
                'class' => $status->badgeClass(),
                'count' => $trips->filter(fn (Trip $trip) => $trip->isStatus($status))->count(),
            ])
            ->filter(fn ($row) => $row['count'] > 0)
            ->values();

        // Daily volume, with empty days filled in so the shape is honest.
        $byDay = collect();
        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $key = $day->toDateString();
            $byDay->push([
                'date'  => $day->copy(),
                'count' => $trips->filter(fn (Trip $trip) => optional($trip->pickup_date)->toDateString() === $key)->count(),
            ]);
        }

        $driverRows = User::driversOf($companyId)->orderBy('name')->get()->map(function (User $driver) use ($trips) {
            $theirs    = $trips->where('driver_id', $driver->id);
            $done      = $theirs->filter(fn (Trip $t) => $t->isStatus(TripStatus::Completed));
            $punctual  = $done->whereNotNull('was_on_time');

            return (object) [
                'driver'       => $driver,
                'assigned'     => $theirs->count(),
                'completed'    => $done->count(),
                'miles'        => round((float) $done->sum('actual_distance'), 1),
                'on_time_rate' => $punctual->count() > 0
                    ? round($punctual->where('was_on_time', true)->count() / $punctual->count() * 100)
                    : null,
            ];
        })
        ->sortByDesc('completed')
        ->values();

        $vehicleRows = Vehicle::where('dispatcher_id', $companyId)->orderBy('name')->get()
            ->map(fn (Vehicle $vehicle) => (object) [
                'vehicle' => $vehicle,
                'trips'   => $trips->where('vehicle_id', $vehicle->id)->count(),
                'miles'   => round((float) $trips->where('vehicle_id', $vehicle->id)->sum('actual_distance'), 1),
            ])
            ->sortByDesc('trips')
            ->values();

        $topClients = $trips->groupBy('client_id')
            ->map(fn ($set, $clientId) => (object) [
                'client' => $set->first()->client,
                'name'   => $set->first()->passengerName(),
                'count'  => $set->count(),
            ])
            ->sortByDesc('count')
            ->take(10)
            ->values();

        $unassigned = $trips->whereNull('driver_id')->count();

        return view('content.dispatcher.reports.index', compact(
            'summary', 'byStatus', 'byDay', 'driverRows', 'vehicleRows', 'topClients',
            'from', 'to', 'unassigned'
        ));
    }

    /**
     * The same window as a CSV, streamed so a long date range does not have to
     * be held in memory first.
     */
    public function export(Request $request): StreamedResponse
    {
        $companyId = auth()->user()->companyId();

        $from = $request->filled('from')
            ? Carbon::parse($request->input('from'))->startOfDay()
            : now()->startOfMonth();

        $to = $request->filled('to')
            ? Carbon::parse($request->input('to'))->endOfDay()
            : now()->endOfMonth();

        $filename = 'trips-' . $from->toDateString() . '-to-' . $to->toDateString() . '.csv';

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
}
