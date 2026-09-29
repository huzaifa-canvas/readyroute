<?php

namespace App\Http\Controllers\Web\Dispatcher;

use App\Enums\TripStatus;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\Request;

/**
 * Advanced Search.
 *
 * One box across trips, clients, drivers and vehicles, plus the trip filters
 * that are too specific for the trip list's own toolbar. Everything is scoped
 * to the signed-in user's company before a term is ever applied.
 */
class SearchController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'q'      => ['nullable', 'string', 'max:120'],
            'from'   => ['nullable', 'date'],
            'to'     => ['nullable', 'date', 'after_or_equal:from'],
            'status' => ['nullable', 'string'],
            'driver' => ['nullable', 'integer'],
            'scope'  => ['nullable', 'in:all,trips,clients,drivers,vehicles'],
        ]);

        $companyId = auth()->user()->companyId();
        $term      = trim((string) $request->input('q'));
        $scope     = $request->input('scope', 'all');
        $hasFilters = $request->hasAny(['from', 'to', 'status', 'driver']);

        $results = [
            'trips'    => collect(),
            'clients'  => collect(),
            'drivers'  => collect(),
            'vehicles' => collect(),
        ];

        // Nothing typed and nothing filtered means an empty board rather than
        // the whole database.
        if ($term !== '' || $hasFilters) {
            if ($scope === 'all' || $scope === 'trips') {
                $results['trips'] = $this->trips($companyId, $term, $request);
            }

            if (($scope === 'all' || $scope === 'clients') && $term !== '') {
                $results['clients'] = $this->clients($companyId, $term);
            }

            if (($scope === 'all' || $scope === 'drivers') && $term !== '') {
                $results['drivers'] = $this->drivers($companyId, $term);
            }

            if (($scope === 'all' || $scope === 'vehicles') && $term !== '') {
                $results['vehicles'] = $this->vehicles($companyId, $term);
            }
        }

        $total = collect($results)->sum(fn ($set) => $set->count());

        $drivers  = User::driversOf($companyId)->orderBy('name')->get();
        $statuses = TripStatus::cases();

        $searched = $term !== '' || $hasFilters;

        return view('content.dispatcher.search.index', compact(
            'results', 'total', 'drivers', 'statuses', 'term', 'scope', 'searched'
        ));
    }

    private function trips(int $companyId, string $term, Request $request)
    {
        $query = Trip::where('dispatcher_id', $companyId)->with(['client', 'driver', 'vehicle']);

        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                $q->where('first_name', 'like', "%{$term}%")
                  ->orWhere('last_name', 'like', "%{$term}%")
                  ->orWhere('phone_number', 'like', "%{$term}%")
                  ->orWhere('member_id', 'like', "%{$term}%")
                  ->orWhere('pickup_address', 'like', "%{$term}%")
                  ->orWhere('dropoff_address', 'like', "%{$term}%")
                  ->orWhere('notes', 'like', "%{$term}%");

                // A reference like "RR-000123" should find trip 123.
                if (preg_match('/(\d+)/', $term, $m)) {
                    $q->orWhere('id', (int) $m[1]);
                }
            });
        }

        if ($request->filled('from')) {
            $query->whereDate('pickup_date', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('pickup_date', '<=', $request->input('to'));
        }

        if ($request->filled('status') && in_array($request->input('status'), TripStatus::values(), true)) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('driver')) {
            $query->where('driver_id', (int) $request->input('driver'));
        }

        return $query->latest('pickup_date')->limit(50)->get();
    }

    private function clients(int $companyId, string $term)
    {
        return Client::where('dispatcher_id', $companyId)
            ->where(function ($q) use ($term) {
                $q->where('full_name', 'like', "%{$term}%")
                  ->orWhere('phone_number', 'like', "%{$term}%")
                  ->orWhere('email', 'like', "%{$term}%")
                  ->orWhere('insurance_id', 'like', "%{$term}%")
                  ->orWhere('home_address', 'like', "%{$term}%")
                  ->orWhere('city', 'like', "%{$term}%");
            })
            ->limit(25)
            ->get();
    }

    private function drivers(int $companyId, string $term)
    {
        return User::driversOf($companyId)
            ->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('email', 'like', "%{$term}%")
                  ->orWhere('phone_number', 'like', "%{$term}%")
                  ->orWhere('driver_code', 'like', "%{$term}%");
            })
            ->limit(25)
            ->get();
    }

    private function vehicles(int $companyId, string $term)
    {
        return Vehicle::where('dispatcher_id', $companyId)
            ->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('number_plate', 'like', "%{$term}%")
                  ->orWhere('make_model_year', 'like', "%{$term}%")
                  ->orWhere('vin_number', 'like', "%{$term}%");
            })
            ->limit(25)
            ->get();
    }
}
