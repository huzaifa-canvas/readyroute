<?php

namespace App\Http\Controllers\Api\Dispatcher;

use App\Enums\TripStatus;
use App\Http\Resources\Dispatcher\ClientResource;
use App\Http\Resources\Dispatcher\DriverResource;
use App\Http\Resources\Dispatcher\TripListResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * One search box across trips, clients and drivers.
 *
 * The scope is chosen by the tabs on the screen, so only the asked-for kind is
 * queried: searching all three on every keystroke would run three queries to
 * fill one list.
 */
class SearchController extends BaseDispatcherController
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'scope' => ['nullable', 'in:trips,clients,drivers'],
            'q'     => ['nullable', 'string', 'max:160'],
            'from'  => ['nullable', 'date'],
            'to'    => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $scope = $request->input('scope', 'trips');
        $term  = trim((string) $request->input('q'));

        return $this->ok([
            'scope'   => $scope,
            'query'   => $term,
            'results' => match ($scope) {
                'clients' => $this->clientResults($term),
                'drivers' => $this->driverResults($term),
                default   => $this->tripResults($request, $term),
            },
            // The filter row above the results, filled with what this company
            // actually has rather than a fixed list.
            'filters' => [
                'statuses' => collect(TripStatus::cases())
                    ->map(fn ($c) => ['value' => $c->value, 'label' => $c->label()])
                    ->values(),
                'drivers' => $this->drivers()->orderBy('name')->get(['id', 'name'])
                    ->map(fn ($d) => ['value' => $d->id, 'label' => $d->name]),
                'funding_sources' => $this->clients()
                    ->whereNotNull('funding_type')
                    ->distinct()
                    ->pluck('funding_type')
                    ->map(fn ($f) => ['value' => $f, 'label' => ucfirst($f)])
                    ->values(),
            ],
        ]);
    }

    private function tripResults(Request $request, string $term)
    {
        $query = $this->trips()->with(['client', 'driver', 'vehicle']);

        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                $q->where('first_name', 'like', "%{$term}%")
                  ->orWhere('last_name', 'like', "%{$term}%")
                  ->orWhere('phone_number', 'like', "%{$term}%")
                  ->orWhere('pickup_address', 'like', "%{$term}%")
                  ->orWhere('dropoff_address', 'like', "%{$term}%")
                  // Reference is "#1042" on screen but an id underneath, so a
                  // typed reference has to match that way too.
                  ->orWhere('id', ltrim($term, '#'));
            });
        }

        if ($request->filled('status')) {
            $query->whereIn('status', (array) $request->input('status'));
        }

        if ($request->filled('driver_id')) {
            $query->where('driver_id', $request->integer('driver_id'));
        }

        if ($request->filled('from')) {
            $query->whereDate('pickup_date', '>=', Carbon::parse($request->input('from'))->toDateString());
        }

        if ($request->filled('to')) {
            $query->whereDate('pickup_date', '<=', Carbon::parse($request->input('to'))->toDateString());
        }

        if ($request->filled('funding_type')) {
            $query->whereHas('client', fn ($q) => $q->where('funding_type', $request->input('funding_type')));
        }

        $found = $query->latest()->limit(50)->get();

        return [
            'count' => $found->count(),
            'items' => TripListResource::collection($found),
        ];
    }

    private function clientResults(string $term)
    {
        $query = $this->clients()->withCount('trips');

        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                $q->where('full_name', 'like', "%{$term}%")
                  ->orWhere('phone_number', 'like', "%{$term}%")
                  ->orWhere('email', 'like', "%{$term}%")
                  ->orWhere('insurance_id', 'like', "%{$term}%");
            });
        }

        $found = $query->orderBy('full_name')->limit(50)->get();

        return [
            'count' => $found->count(),
            'items' => ClientResource::collection($found),
        ];
    }

    private function driverResults(string $term)
    {
        $query = $this->drivers()->withCount('activeTrips')->with(['metas', 'assignedVehicle']);

        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('email', 'like', "%{$term}%")
                  ->orWhere('phone_number', 'like', "%{$term}%")
                  ->orWhere('driver_code', 'like', "%{$term}%");
            });
        }

        $found = $query->orderBy('name')->limit(50)->get();

        return [
            'count' => $found->count(),
            'items' => DriverResource::collection($found),
        ];
    }
}
