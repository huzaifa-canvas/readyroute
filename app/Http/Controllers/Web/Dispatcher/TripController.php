<?php

namespace App\Http\Controllers\Web\Dispatcher;

use App\Enums\TripStatus;
use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Models\Client;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class TripController extends Controller
{
    public function index(Request $request)
    {
        $dispatcher = auth()->user();

        $query = Trip::with(['client', 'driver', 'vehicle'])
            ->where('dispatcher_id', $dispatcher->companyId());

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('pickup_address', 'like', "%{$search}%")
                  ->orWhere('dropoff_address', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // "Today's Trips" is both ends set to today, so this one pair of fields
        // covers a single day, a range or an open-ended start/end.
        $dateFrom = $this->parseDate($request->input('date_from'));
        $dateTo = $this->parseDate($request->input('date_to'));

        if ($dateFrom) {
            $query->whereDate('pickup_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('pickup_date', '<=', $dateTo);
        }

        if ($request->input('driver_id') === 'unassigned') {
            $query->whereNull('driver_id');
        } elseif ($request->filled('driver_id')) {
            $query->where('driver_id', $request->integer('driver_id'));
        }

        $trips = $query->latest()->paginate(15)->withQueryString();

        $drivers = User::where('dispatcher_id', $dispatcher->companyId())
            ->where('role', 'driver')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('content.dispatcher.trip.list', compact('trips', 'drivers'));
    }

    /**
     * A filter date from the query string, or null when it is missing or not
     * a real date, so a hand-edited URL narrows nothing instead of erroring.
     */
    private function parseDate(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    public function create()
    {
        $dispatcherId = auth()->user()->companyId();

        $clients = Client::where('dispatcher_id', $dispatcherId)->get();
        $drivers = User::where('dispatcher_id', $dispatcherId)->where('role', 'driver')
            ->with(['assignedVehicle', 'metas'])->orderBy('name')->get();
        $vehicles = Vehicle::where('dispatcher_id', $dispatcherId)->get();

        return view('content.dispatcher.trip.create', compact('clients', 'drivers', 'vehicles'));
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'client_id' => 'nullable|exists:clients,id',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone_number' => 'nullable|string|max:20',
            'member_id' => 'nullable|string|max:100',
            
            'pickup_date' => 'required|date',
            'pickup_time' => 'required',
            'pickup_address' => 'required|string|max:255',
            'pickup_lat' => 'nullable|numeric',
            'pickup_lng' => 'nullable|numeric',
            'dropoff_address' => 'required|string|max:255',
            'dropoff_lat' => 'nullable|numeric',
            'dropoff_lng' => 'nullable|numeric',
            'distance' => 'nullable|numeric',
            'trip_type' => 'required|in:one_way,round_trip,recurring',
            'notes' => 'nullable|string',
            
            // Scoped to this company: 'exists:users,id' alone would have let a
            // dispatcher put another company's driver on a trip.
            'driver_id' => ['nullable', Rule::exists('users', 'id')
                ->where('role', 'driver')
                ->where('dispatcher_id', auth()->user()->companyId())],
            'billing_type' => 'nullable|string|max:100',
        ]);

        $validatedData['req_wheelchair'] = $request->has('req_wheelchair') ? 1 : 0;
        $validatedData['req_stretcher'] = $request->has('req_stretcher') ? 1 : 0;
        $validatedData['req_o2_tank'] = $request->has('req_o2_tank') ? 1 : 0;
        $validatedData['req_bariatric'] = $request->has('req_bariatric') ? 1 : 0;
        $validatedData['req_no_steps'] = $request->has('req_no_steps') ? 1 : 0;
        
        if (!empty($validatedData['pickup_time'])) {
            $validatedData['pickup_time'] = \Carbon\Carbon::parse($validatedData['pickup_time'])->format('H:i:s');
        }


        /*
         * The vehicle comes from whoever is driving, never from the form.
         * The form shows it read-only, and anything posted for it is ignored:
         * a trip can only ever run in the vehicle its driver actually has.
         */
        $validatedData['vehicle_id'] = $request->filled('driver_id')
            ? User::driversOf(auth()->user()->companyId())
                ->whereKey($request->driver_id)
                ->first()?->assignedVehicle?->id
            : null;


        /*
         * A driver cannot be put on a trip that falls on a day they do not
         * work. The form already greys those drivers out; this is the half
         * that actually enforces it, since a disabled option proves nothing
         * about what was posted.
         */
        if ($request->filled('driver_id')) {
            $chosen = User::driversOf(auth()->user()->companyId())
                ->with('metas')
                ->whereKey($request->driver_id)
                ->first();

            $pickupDate = $request->filled('pickup_date')
                ? Carbon::parse($request->input('pickup_date'))
                : null;

            if ($chosen && ! $chosen->isAvailableOn($pickupDate)) {
                return back()->withInput()->withErrors([
                    'driver_id' => $chosen->name . ' does not work on '
                        . $pickupDate->format('l') . '. They are available '
                        . $chosen->availabilityLabel() . '.',
                ]);
            }
        }

        $validatedData['dispatcher_id'] = auth()->user()->companyId();
        $validatedData['status'] = 'scheduled';

        Trip::create($validatedData);

        return redirect()->route('dispatcher.trip.create')->with('success', 'New Trip created successfully!');
    }

    /**
     * Trips per driver for one day.
     *
     * Answers "who is carrying what today" in one screen, and hands straight
     * off to the trip list with the same day and driver already filtered, so
     * the count and the list it came from can never disagree.
     */
    public function driverLoad(Request $request)
    {
        $companyId = auth()->user()->companyId();

        /*
         * One day or a span, handled as the same thing: a single day is just a
         * range whose ends match. That keeps the counts, the links and the
         * arrows from each needing their own version of the logic.
         *
         * Defaults to today, which is the question this page exists to answer.
         */
        $from = Carbon::parse(
            $this->parseDate($request->input('date_from'))
            ?: $this->parseDate($request->input('date'))
            ?: now()->toDateString()
        );

        $to = Carbon::parse($this->parseDate($request->input('date_to')) ?: $from->toDateString());

        // Picked back to front, which flatpickr allows; swapped rather than
        // returning nothing.
        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        $drivers = User::driversOf($companyId)
            ->with(['metas', 'assignedVehicle'])
            ->orderBy('name')
            ->get();

        /*
         * One grouped query rather than a count per driver: the page would
         * otherwise fire a query for every row, and the totals below would
         * need their own on top of that.
         */
        $counts = Trip::where('dispatcher_id', $companyId)
            ->whereBetween('pickup_date', [$from->toDateString(), $to->toDateString()])
            ->whereNotNull('driver_id')
            ->selectRaw('driver_id, count(*) as total')
            ->groupBy('driver_id')
            ->pluck('total', 'driver_id');

        // Trips nobody is on yet are the ones that need attention, so they are
        // surfaced rather than left out of a per-driver breakdown.
        $unassigned = Trip::where('dispatcher_id', $companyId)
            ->whereBetween('pickup_date', [$from->toDateString(), $to->toDateString()])
            ->whereNull('driver_id')
            ->count();

        return view('content.dispatcher.trip.driver-load', [
            'drivers'    => $drivers,
            'counts'     => $counts,
            'from'       => $from,
            'to'         => $to,
            // How far the arrows move: a single day steps a day, a week steps
            // a week, so paging never overlaps or skips.
            //
            // Cast, because diffInDays returns a float — without it a single
            // day comes back as 1.0 and every `=== 1` check in the view misses,
            // so one day would render as if it were a range.
            'span'       => (int) $from->diffInDays($to) + 1,
            'unassigned' => $unassigned,
            'assigned'   => $counts->sum(),
        ]);
    }

    public function calendar()
    {
        $dispatcherId = auth()->user()->companyId();
        $drivers = User::where('dispatcher_id', $dispatcherId)->where('role', 'driver')
            ->with(['assignedVehicle', 'metas'])->orderBy('name')->get();
        return view('content.dispatcher.trip.calendar', compact('drivers'));
    }

    public function events()
    {
        $dispatcher = auth()->user();

        $trips = Trip::with(['driver', 'vehicle', 'client'])
            ->where('dispatcher_id', $dispatcher->companyId())
            ->get();

        // Vuexy theme label color palette (soft badges)
        $vuexyColors = [
            ['name' => 'primary',   'hex' => '#696cff'],
            ['name' => 'success',   'hex' => '#71dd37'],
            ['name' => 'info',      'hex' => '#03c3ec'],
            ['name' => 'warning',   'hex' => '#ffab00'],
            ['name' => 'danger',    'hex' => '#ff3e1d'],
        ];

        // Build a driver-to-color map
        $driverColorMap = [];
        $colorIndex = 0;
        foreach ($trips as $trip) {
            if ($trip->driver_id && !isset($driverColorMap[$trip->driver_id])) {
                $driverColorMap[$trip->driver_id] = $vuexyColors[$colorIndex % count($vuexyColors)];
                $colorIndex++;
            }
        }

        $events = $trips->map(function ($trip) use ($driverColorMap) {
            // Combine pickup_date and pickup_time for start datetime
            $timeString = $trip->pickup_time ? $trip->pickup_time : '09:00:00';
            $startDateTime = $trip->pickup_date ? $trip->pickup_date->format('Y-m-d') . 'T' . $timeString : now()->format('Y-m-d') . 'T' . $timeString;

            // Color: per-driver if assigned, else secondary/unassigned
            $colorObj = ($trip->driver_id && isset($driverColorMap[$trip->driver_id]))
                ? $driverColorMap[$trip->driver_id]
                : ['name' => 'secondary', 'hex' => '#8a8d93'];

            $passengerName = trim($trip->first_name . ' ' . $trip->last_name);

            return [
                'id'            => $trip->id,
                'title'         => $passengerName,
                'start'         => $startDateTime,
                'extendedProps' => [
                    'calendar'   => $colorObj['name'],
                    'color'      => $colorObj['hex'],
                    'passenger'  => $passengerName,
                    'phone'      => $trip->phone_number ?: 'N/A',
                    'pickup'     => $trip->pickup_address,
                    'dropoff'    => $trip->dropoff_address,
                    'driver'     => $trip->driver ? $trip->driver->name : 'Unassigned',
                    'driver_id'  => $trip->driver_id ?: 'unassigned',
                    'vehicle'    => $trip->vehicle ? $trip->vehicle->name : 'Unassigned',
                    'status'     => $trip->statusEnum()?->value,
                    'type'       => str_replace('_', ' ', $trip->trip_type),
                    'distance'   => $trip->distance ? $trip->distance . ' Miles' : 'N/A',
                    'notes'      => $trip->notes ?: 'None',
                ]
            ];
        });

        return response()->json($events);
    }

    public function show($id)
    {
        $trip = Trip::with(['client', 'driver', 'vehicle'])
            ->where('dispatcher_id', auth()->user()->companyId())
            ->findOrFail($id);

        return view('content.dispatcher.trip.show', compact('trip'));
    }

    public function edit($id)
    {
        $dispatcherId = auth()->user()->companyId();
        $trip = Trip::where('dispatcher_id', $dispatcherId)->findOrFail($id);

        $clients = Client::where('dispatcher_id', $dispatcherId)->get();
        $drivers = User::where('dispatcher_id', $dispatcherId)->where('role', 'driver')
            ->with(['assignedVehicle', 'metas'])->orderBy('name')->get();
        $vehicles = Vehicle::where('dispatcher_id', $dispatcherId)->get();

        return view('content.dispatcher.trip.edit', compact('trip', 'clients', 'drivers', 'vehicles'));
    }

    public function update(Request $request, $id)
    {
        $dispatcherId = auth()->user()->companyId();
        $trip = Trip::where('dispatcher_id', $dispatcherId)->findOrFail($id);

        $validatedData = $request->validate([
            'client_id' => 'nullable|exists:clients,id',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone_number' => 'nullable|string|max:20',
            'member_id' => 'nullable|string|max:100',
            
            'pickup_date' => 'required|date',
            'pickup_time' => 'required',
            'pickup_address' => 'required|string|max:255',
            'pickup_lat' => 'nullable|numeric',
            'pickup_lng' => 'nullable|numeric',
            'dropoff_address' => 'required|string|max:255',
            'dropoff_lat' => 'nullable|numeric',
            'dropoff_lng' => 'nullable|numeric',
            'distance' => 'nullable|numeric',
            'trip_type' => 'required|in:one_way,round_trip,recurring',
            'notes' => 'nullable|string',
            'status' => 'required|in:' . implode(',', TripStatus::values()),
            
            // Scoped to this company: 'exists:users,id' alone would have let a
            // dispatcher put another company's driver on a trip.
            'driver_id' => ['nullable', Rule::exists('users', 'id')
                ->where('role', 'driver')
                ->where('dispatcher_id', auth()->user()->companyId())],
            'billing_type' => 'nullable|string|max:100',
        ]);

        $validatedData['req_wheelchair'] = $request->has('req_wheelchair') ? 1 : 0;
        $validatedData['req_stretcher'] = $request->has('req_stretcher') ? 1 : 0;
        $validatedData['req_o2_tank'] = $request->has('req_o2_tank') ? 1 : 0;
        $validatedData['req_bariatric'] = $request->has('req_bariatric') ? 1 : 0;
        $validatedData['req_no_steps'] = $request->has('req_no_steps') ? 1 : 0;
        
        if (!empty($validatedData['pickup_time'])) {
            $validatedData['pickup_time'] = \Carbon\Carbon::parse($validatedData['pickup_time'])->format('H:i:s');
        }


        /*
         * The vehicle comes from whoever is driving, never from the form.
         * The form shows it read-only, and anything posted for it is ignored:
         * a trip can only ever run in the vehicle its driver actually has.
         */
        $validatedData['vehicle_id'] = $request->filled('driver_id')
            ? User::driversOf(auth()->user()->companyId())
                ->whereKey($request->driver_id)
                ->first()?->assignedVehicle?->id
            : null;


        /*
         * A driver cannot be put on a trip that falls on a day they do not
         * work. The form already greys those drivers out; this is the half
         * that actually enforces it, since a disabled option proves nothing
         * about what was posted.
         */
        if ($request->filled('driver_id')) {
            $chosen = User::driversOf(auth()->user()->companyId())
                ->with('metas')
                ->whereKey($request->driver_id)
                ->first();

            $pickupDate = $request->filled('pickup_date')
                ? Carbon::parse($request->input('pickup_date'))
                : null;

            if ($chosen && ! $chosen->isAvailableOn($pickupDate)) {
                return back()->withInput()->withErrors([
                    'driver_id' => $chosen->name . ' does not work on '
                        . $pickupDate->format('l') . '. They are available '
                        . $chosen->availabilityLabel() . '.',
                ]);
            }
        }

        $trip->update($validatedData);

        return redirect()->route('dispatcher.trip.details', $trip->id)->with('success', 'Trip updated successfully!');
    }

    /**
     * Create the passenger tracking link before the driver sets off.
     *
     * The link is normally minted when a trip goes en route; this lets a
     * dispatcher send it in advance, which is what they need while there is
     * no SMS provider doing it for them.
     */
    public function trackingLink($id)
    {
        $trip = Trip::where('dispatcher_id', auth()->user()->companyId())->findOrFail($id);

        $trip->trackingToken();

        return back()->with('success', 'Tracking link created. Copy it and send it to the passenger.');
    }

    /**
     * Cancel a trip.
     *
     * This used to delete the row. A deleted trip takes its history with it:
     * the driver is left guessing why a run vanished from their list, and the
     * company loses the record of a journey it may still have to account for.
     * The trip is kept and marked cancelled instead, which also gives the
     * driver something to be notified about.
     */
    public function cancel($id)
    {
        $trip = Trip::where('dispatcher_id', auth()->user()->companyId())->findOrFail($id);

        $status = $trip->statusEnum();

        if ($status?->isTerminal()) {
            return back()->with(
                'error',
                'This trip is already ' . strtolower($status->label()) . ' and cannot be cancelled.'
            );
        }

        // The driver is told by the trip observer, which watches the status
        // rather than this one action.
        $trip->update(['status' => TripStatus::Cancelled->value]);

        $message = $trip->driver_id
            ? 'Trip cancelled. ' . ($trip->driver?->name ?: 'The driver') . ' has been notified.'
            : 'Trip cancelled.';

        return redirect()->route('dispatcher.trip.list')->with('success', $message);
    }

    /**
     * Remove a trip from the panel.
     *
     * Separate from cancelling: cancelling records that a booked run did not
     * happen, deleting says the trip should never have been on the board at
     * all. It is a soft delete, so the signature, the status log and any
     * invoice line keep the rows they point at — a real DELETE would cascade
     * the first two away and quietly detach the third.
     */
    public function destroy($id)
    {
        $trip = Trip::where('dispatcher_id', auth()->user()->companyId())->findOrFail($id);

        if ($trip->isBilled()) {
            return back()->with(
                'error',
                'This trip is on an invoice and cannot be deleted. Cancel it instead, or remove it from the invoice first.'
            );
        }

        // The driver is told by the trip observer if this was still their work.
        $trip->delete();

        return redirect()->route('dispatcher.trip.list')->with('success', 'Trip deleted.');
    }
}
