<?php

namespace App\Http\Controllers\Api\Dispatcher;

use App\Enums\TripStatus;
use App\Http\Resources\Dispatcher\TripListResource;
use App\Http\Resources\Dispatcher\TripResource;
use App\Models\Trip;
use App\Models\User;
use App\Models\VehicleInspection;
use App\Services\SignatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Trips, as the panel manages them.
 *
 * The rules here are the same ones the web panel enforces, and deliberately
 * restated rather than called into: a dispatcher must not be able to put
 * another company's driver on a trip, a driver must not be given work on a day
 * they do not work, and the vehicle always comes from whoever is driving.
 */
class TripController extends BaseDispatcherController
{
    public function index(Request $request): JsonResponse
    {
        $query = $this->trips()->with(['client', 'driver', 'vehicle']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('pickup_address', 'like', "%{$search}%")
                  ->orWhere('dropoff_address', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->whereIn('status', (array) $request->input('status'));
        }

        // One pair of dates covers a single day, a span, or an open end.
        if ($from = $this->parseDate($request->input('date_from'))) {
            $query->whereDate('pickup_date', '>=', $from);
        }

        if ($to = $this->parseDate($request->input('date_to'))) {
            $query->whereDate('pickup_date', '<=', $to);
        }

        if ($request->input('driver_id') === 'unassigned') {
            $query->whereNull('driver_id');
        } elseif ($request->filled('driver_id')) {
            $query->where('driver_id', $request->integer('driver_id'));
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->integer('client_id'));
        }

        return $this->paginated(
            TripListResource::collection($query->latest()->paginate($this->perPage()))
        );
    }

    /**
     * What the create and edit forms need to draw themselves.
     *
     * Drivers come with the vehicle they run and which days they work, because
     * the form has to show the vehicle read-only and grey out anyone who does
     * not work on the chosen day.
     */
    public function formOptions(): JsonResponse
    {
        return $this->ok([
            'clients' => $this->clients()->orderBy('full_name')->get()
                ->map(fn ($client) => [
                    'id'      => $client->id,
                    'name'    => $client->full_name,
                    'phone'   => $client->phone_number,
                    'address' => $client->home_address,
                    'funding' => $client->funding_type,
                ]),

            'drivers' => $this->drivers()->with(['assignedVehicle', 'metas'])->orderBy('name')->get()
                ->map(fn (User $driver) => [
                    'id'   => $driver->id,
                    'name' => $driver->name,
                    'code' => $driver->driver_code,
                    'availability' => [
                        'days'  => $driver->availability(),
                        'short' => $driver->availabilityShort(),
                    ],
                    // The form fills this in and locks it; a trip can only run
                    // in the vehicle its driver actually has.
                    'vehicle' => $driver->assignedVehicle ? [
                        'id'           => $driver->assignedVehicle->id,
                        'name'         => $driver->assignedVehicle->name,
                        'number_plate' => $driver->assignedVehicle->number_plate,
                    ] : null,
                ]),

            'trip_types' => [
                ['value' => 'one_way',    'label' => 'One way'],
                ['value' => 'round_trip', 'label' => 'Round trip'],
                ['value' => 'recurring',  'label' => 'Recurring'],
            ],

            'statuses' => collect(TripStatus::cases())->map(fn ($case) => [
                'value' => $case->value,
                'label' => $case->label(),
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());

        if ($error = $this->availabilityError($request)) {
            return $this->fail($error, 422, ['driver_id' => [$error]]);
        }

        $data = $this->withFlags($request, $data);
        $data['dispatcher_id'] = $this->companyId();
        $data['status']        = TripStatus::Scheduled->value;
        $data['vehicle_id']    = $this->vehicleForDriver($request->input('driver_id'));

        $trip = Trip::create($data);

        return $this->created(
            new TripResource($trip->load(['client', 'driver', 'vehicle', 'statusLogs'])),
            'Trip created.'
        );
    }

    public function show(int $id, SignatureService $signatures): JsonResponse
    {
        $trip = $this->trips()
            ->with(['client', 'driver', 'vehicle', 'statusLogs', 'signature'])
            ->find($id);

        if (! $trip) {
            return $this->notFound('Trip not found.');
        }

        /*
         * The driver's pre-trip check for the day this trip runs.
         *
         * One inspection a day, not one per trip, so it is found by driver and
         * date. It is here so the office can answer "was the vehicle checked
         * before this journey?" without leaving the trip.
         */
        $inspection = null;

        if ($trip->driver_id && $trip->pickup_date) {
            $record = VehicleInspection::with(['responses.item', 'vehicle'])
                ->where('dispatcher_id', $trip->dispatcher_id)
                ->where('driver_id', $trip->driver_id)
                ->whereDate('inspection_date', $trip->pickup_date)
                ->first();

            if ($record) {
                $inspection = [
                    'id'           => $record->id,
                    'date'         => optional($record->inspection_date)->toDateString(),
                    'vehicle'      => $record->vehicle?->name,
                    'status'       => $record->status,
                    'has_defects'  => $record->responses->contains(fn ($row) => $row->is_defect),
                    'signature_url' => $signatures->temporaryUrl($record->signature_path),
                    'items' => $record->responses->map(fn ($response) => [
                        'label'     => $response->item?->label,
                        'is_defect' => (bool) $response->is_defect,
                        'note'      => $response->note,
                    ])->values(),
                ];
            }
        }

        $signatureUrl = $trip->signature
            ? $signatures->temporaryUrl($trip->signature->signature_path)
            : null;

        return $this->ok(new TripResource($trip, $inspection, $signatureUrl));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $trip = $this->trips()->find($id);

        if (! $trip) {
            return $this->notFound('Trip not found.');
        }

        $rules = $this->rules();
        $rules['status'] = ['required', Rule::in(TripStatus::values())];

        $data = $request->validate($rules);

        if ($error = $this->availabilityError($request)) {
            return $this->fail($error, 422, ['driver_id' => [$error]]);
        }

        $data = $this->withFlags($request, $data);
        $data['vehicle_id'] = $this->vehicleForDriver($request->input('driver_id'));

        $trip->update($data);

        return $this->ok(
            new TripResource($trip->fresh()->load(['client', 'driver', 'vehicle', 'statusLogs'])),
            'Trip updated.'
        );
    }

    /**
     * Put a driver on a trip, or take one off, without touching anything else.
     *
     * The Trip Assignment screen is one picker and a button, so it has its own
     * endpoint rather than sending the whole trip back.
     */
    public function assign(Request $request, int $id): JsonResponse
    {
        $trip = $this->trips()->find($id);

        if (! $trip) {
            return $this->notFound('Trip not found.');
        }

        $request->validate([
            'driver_id' => ['nullable', $this->driverRule()],
        ]);

        if ($error = $this->availabilityError($request, $trip->pickup_date)) {
            return $this->fail($error, 422, ['driver_id' => [$error]]);
        }

        $trip->update([
            'driver_id'  => $request->input('driver_id'),
            'vehicle_id' => $this->vehicleForDriver($request->input('driver_id')),
        ]);

        // The driver is told by the trip observer, which watches the column
        // rather than this one action.
        return $this->ok(
            new TripResource($trip->fresh()->load(['client', 'driver', 'vehicle', 'statusLogs'])),
            $request->filled('driver_id')
                ? 'Driver assigned. They have been notified.'
                : 'Driver removed from this trip.'
        );
    }

    /**
     * Cancel a trip without removing it.
     *
     * The run stays on the board and in the driver's list, marked cancelled
     * and read-only, so nobody is left guessing why work disappeared.
     */
    public function cancel(int $id): JsonResponse
    {
        $trip = $this->trips()->find($id);

        if (! $trip) {
            return $this->notFound('Trip not found.');
        }

        $status = $trip->statusEnum();

        if ($status?->isTerminal()) {
            return $this->fail(
                'This trip is already ' . strtolower($status->label()) . ' and cannot be cancelled.',
                422
            );
        }

        $trip->update(['status' => TripStatus::Cancelled->value]);

        return $this->ok(
            new TripResource($trip->fresh()->load(['client', 'driver', 'vehicle', 'statusLogs'])),
            $trip->driver_id
                ? 'Trip cancelled. ' . ($trip->driver?->name ?: 'The driver') . ' has been notified.'
                : 'Trip cancelled.'
        );
    }

    /**
     * Take a trip off the board entirely.
     *
     * Separate from cancelling: cancelling records that a booked run did not
     * happen, deleting says it should never have been there. Soft, so the
     * signature, status log and any invoice line keep the rows they point at.
     */
    public function destroy(int $id): JsonResponse
    {
        $trip = $this->trips()->find($id);

        if (! $trip) {
            return $this->notFound('Trip not found.');
        }

        if ($trip->isBilled()) {
            return $this->fail(
                'This trip is on an invoice and cannot be deleted. Cancel it instead, or remove it from the invoice first.',
                422
            );
        }

        $trip->delete();

        return $this->ok(null, 'Trip deleted.');
    }

    /**
     * Mint the passenger tracking link before the driver sets off.
     */
    public function trackingLink(int $id): JsonResponse
    {
        $trip = $this->trips()->find($id);

        if (! $trip) {
            return $this->notFound('Trip not found.');
        }

        $trip->trackingToken();

        return $this->ok(['tracking_url' => $trip->trackingUrl()], 'Tracking link ready to send.');
    }

    // ── Shared pieces ────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'client_id'    => ['nullable', Rule::exists('clients', 'id')->where('dispatcher_id', $this->companyId())],
            'first_name'   => ['required', 'string', 'max:255'],
            'last_name'    => ['required', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'member_id'    => ['nullable', 'string', 'max:100'],

            'pickup_date'     => ['required', 'date'],
            'pickup_time'     => ['required'],
            'pickup_address'  => ['required', 'string', 'max:255'],
            'pickup_lat'      => ['nullable', 'numeric'],
            'pickup_lng'      => ['nullable', 'numeric'],
            'dropoff_address' => ['required', 'string', 'max:255'],
            'dropoff_lat'     => ['nullable', 'numeric'],
            'dropoff_lng'     => ['nullable', 'numeric'],
            'distance'        => ['nullable', 'numeric'],
            'trip_type'       => ['required', 'in:one_way,round_trip,recurring'],
            'notes'           => ['nullable', 'string'],
            'billing_type'    => ['nullable', 'string', 'max:100'],

            'driver_id' => ['nullable', $this->driverRule()],

            'req_wheelchair' => ['nullable', 'boolean'],
            'req_stretcher'  => ['nullable', 'boolean'],
            'req_o2_tank'    => ['nullable', 'boolean'],
            'req_bariatric'  => ['nullable', 'boolean'],
            'req_no_steps'   => ['nullable', 'boolean'],
        ];
    }

    /**
     * Scoped to this company. A bare exists rule would let a dispatcher put
     * another company's driver on a trip.
     */
    private function driverRule(): \Illuminate\Validation\Rules\Exists
    {
        return Rule::exists('users', 'id')
            ->where('role', 'driver')
            ->where('dispatcher_id', $this->companyId());
    }

    /**
     * The requirement switches, and the time in the shape the column wants.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withFlags(Request $request, array $data): array
    {
        foreach (['req_wheelchair', 'req_stretcher', 'req_o2_tank', 'req_bariatric', 'req_no_steps'] as $flag) {
            $data[$flag] = $request->boolean($flag) ? 1 : 0;
        }

        if (! empty($data['pickup_time'])) {
            $data['pickup_time'] = Carbon::parse($data['pickup_time'])->format('H:i:s');
        }

        return $data;
    }

    /**
     * The vehicle comes from whoever is driving, never from the request.
     */
    private function vehicleForDriver(mixed $driverId): ?int
    {
        if (blank($driverId)) {
            return null;
        }

        return $this->drivers()->whereKey($driverId)->first()?->assignedVehicle?->id;
    }

    /**
     * A driver cannot be put on a trip that falls on a day they do not work.
     * The form greys those drivers out; this is the half that enforces it,
     * since a disabled option proves nothing about what was sent.
     */
    private function availabilityError(Request $request, mixed $fallbackDate = null): ?string
    {
        if (! $request->filled('driver_id')) {
            return null;
        }

        $driver = $this->drivers()->with('metas')->whereKey($request->input('driver_id'))->first();

        $date = $request->filled('pickup_date')
            ? Carbon::parse($request->input('pickup_date'))
            : ($fallbackDate ? Carbon::parse($fallbackDate) : null);

        if (! $driver || ! $date || $driver->isAvailableOn($date)) {
            return null;
        }

        return $driver->name . ' does not work on ' . $date->format('l')
            . '. They are available ' . $driver->availabilityLabel() . '.';
    }

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
}
