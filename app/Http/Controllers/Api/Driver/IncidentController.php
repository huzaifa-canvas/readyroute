<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Resources\Driver\IncidentResource;
use App\Models\TripIncident;
use App\Notifications\IncidentReportedNotification;
use App\Services\SignatureService;
use App\Services\SocketEmitter;
use App\Services\TripLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Incidents a driver reports from the road, and the SOS panic button.
 *
 * SOS is deliberately a separate, near-empty endpoint: it takes nothing but a
 * position, because a driver pressing it has no time to fill in a form. The
 * detail can be added afterwards through update().
 */
class IncidentController extends BaseDriverController
{
    public function __construct(
        private readonly SocketEmitter $socket,
        private readonly TripLifecycleService $lifecycle,
        private readonly SignatureService $files,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status'   => ['sometimes', 'nullable', Rule::in(array_keys(TripIncident::STATUSES))],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $query = TripIncident::where('driver_id', $this->driver()->id)
            ->with(['trip', 'vehicle'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $incidents = $query->paginate($request->integer('per_page') ?: 20);

        // Same shape as the trip list: transform in place so paginated() sees
        // resources rather than models.
        $incidents->getCollection()->transform(fn (TripIncident $incident) => new IncidentResource($incident));

        return $this->paginated($incidents);
    }

    public function show($id): JsonResponse
    {
        $incident = TripIncident::where('driver_id', $this->driver()->id)
            ->with(['trip', 'vehicle', 'acknowledgedBy'])
            ->find($id);

        if (! $incident) {
            return $this->notFound('Incident not found.');
        }

        return $this->ok(new IncidentResource($incident));
    }

    /**
     * A reported incident, with optional photos.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'type'        => ['required', Rule::in(array_keys(TripIncident::TYPES))],
            'severity'    => ['sometimes', Rule::in(array_keys(TripIncident::SEVERITIES))],
            'title'       => ['nullable', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:4000'],
            'trip_id'     => ['nullable', 'integer'],
            'lat'         => ['nullable', 'numeric', 'between:-90,90'],
            'lng'         => ['nullable', 'numeric', 'between:-180,180'],
            'address'     => ['nullable', 'string', 'max:255'],
            'photos'      => ['nullable', 'array', 'max:5'],
            'photos.*'    => ['string'],
        ]);

        $driver = $this->driver();

        // A trip id is only accepted if it is actually this driver's.
        $tripId = $request->filled('trip_id')
            ? $this->findTrip($request->integer('trip_id'))?->id
            : null;

        $incident = TripIncident::create([
            'dispatcher_id' => $this->companyId(),
            'driver_id'     => $driver->id,
            'trip_id'       => $tripId,
            'vehicle_id'    => $driver->assignedVehicle?->id,
            'type'          => $request->input('type'),
            'severity'      => $request->input('severity', 'medium'),
            'title'         => $request->input('title'),
            'description'   => $request->input('description'),
            'lat'           => $request->input('lat'),
            'lng'           => $request->input('lng'),
            'address'       => $request->input('address'),
            'attachments'   => $this->storePhotos($request->input('photos', [])),
            'status'        => 'open',
        ]);

        $this->announce($incident);

        return $this->created(new IncidentResource($incident), 'Incident reported.');
    }

    /**
     * The SOS button.
     *
     * One tap, no form. Everything that can be inferred is inferred, so the
     * dispatcher gets a position and a driver name within a second of the
     * press. The driver can add detail afterwards.
     */
    public function sos(Request $request): JsonResponse
    {
        $request->validate([
            'lat'     => ['nullable', 'numeric', 'between:-90,90'],
            'lng'     => ['nullable', 'numeric', 'between:-180,180'],
            'note'    => ['nullable', 'string', 'max:500'],
        ]);

        $driver = $this->driver();

        // Fall back to the last known GPS fix if the phone could not get one
        // at the moment of the press.
        $lat = $request->input('lat', $driver->last_lat);
        $lng = $request->input('lng', $driver->last_lng);

        $incident = TripIncident::create([
            'dispatcher_id' => $this->companyId(),
            'driver_id'     => $driver->id,
            'trip_id'       => $this->lifecycle->activeTripFor($driver)?->id,
            'vehicle_id'    => $driver->assignedVehicle?->id,
            'type'          => 'sos',
            'severity'      => 'critical',
            'title'         => 'Emergency alert',
            'description'   => $request->input('note'),
            'lat'           => $lat,
            'lng'           => $lng,
            'status'        => 'open',
        ]);

        // Also recorded on the GPS trail so the dispatcher's map shows the
        // driver exactly where the alert came from.
        if ($lat !== null && $lng !== null) {
            $this->lifecycle->recordPosition($driver, (float) $lat, (float) $lng, $incident->trip_id);
        }

        $this->announce($incident);

        Log::warning('SOS raised', [
            'driver_id'  => $driver->id,
            'incident_id' => $incident->id,
            'trip_id'    => $incident->trip_id,
        ]);

        return $this->created(new IncidentResource($incident), 'Emergency alert sent. Dispatch has been notified.');
    }

    /**
     * Add detail to an incident the driver already raised — typically the
     * description after an SOS.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $incident = TripIncident::where('driver_id', $this->driver()->id)->find($id);

        if (! $incident) {
            return $this->notFound('Incident not found.');
        }

        if ($incident->isResolved()) {
            return $this->fail('This incident has been resolved and can no longer be changed.', 422);
        }

        $request->validate([
            'title'       => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:4000'],
            'photos'      => ['nullable', 'array', 'max:5'],
            'photos.*'    => ['string'],
        ]);

        $incident->fill(array_filter([
            'title'       => $request->input('title'),
            'description' => $request->input('description'),
        ], fn ($value) => $value !== null));

        if ($request->filled('photos')) {
            $incident->attachments = array_merge(
                $incident->attachments ?? [],
                $this->storePhotos($request->input('photos', [])) ?? []
            );
        }

        $incident->save();

        $this->socket->queue(
            SocketEmitter::dispatcherRoom($this->companyId()),
            'incident:updated',
            ['id' => $incident->id, 'driver_id' => $incident->driver_id]
        );

        return $this->ok(new IncidentResource($incident), 'Incident updated.');
    }

    /**
     * Tell the office, both ways: a socket event for anyone with the panel
     * open, and a notification for everyone else.
     */
    private function announce(TripIncident $incident): void
    {
        $companyId = $this->companyId();

        if (! $companyId) {
            return;
        }

        $payload = [
            'id'        => $incident->id,
            'type'      => $incident->type,
            'severity'  => $incident->severity,
            'headline'  => $incident->headline(),
            'driver_id' => $incident->driver_id,
            'driver'    => $this->driver()->name,
            'trip_id'   => $incident->trip_id,
            'lat'       => $incident->lat,
            'lng'       => $incident->lng,
            'is_sos'    => $incident->isSos(),
            'created_at' => $incident->created_at->toIso8601String(),
        ];

        $this->socket->queue(SocketEmitter::dispatcherRoom($companyId), 'incident:new', $payload);
        $this->socket->queue(SocketEmitter::userRoom($companyId), 'incident:new', $payload);

        // Notify the company account and every panel user under it.
        $recipients = \App\Models\User::query()
            ->where('id', $companyId)
            ->orWhere(fn ($q) => $q->where('dispatcher_id', $companyId)->where('role', 'dispatcher'))
            ->get();

        foreach ($recipients as $recipient) {
            $recipient->notify(new IncidentReportedNotification($incident));
        }
    }

    /**
     * Photos arrive as base64 like signatures do, and go to the same private
     * disk. A photo that fails validation is skipped rather than failing the
     * whole report — losing an attachment is better than losing the incident.
     *
     * @param  array<int, string>  $photos
     * @return array<int, string>|null
     */
    private function storePhotos(array $photos): ?array
    {
        $stored = [];

        foreach ($photos as $photo) {
            if (! is_string($photo) || $photo === '') {
                continue;
            }

            try {
                $stored[] = $this->files->storeImage($photo, 'incidents');
            } catch (\Throwable $e) {
                Log::warning('Incident photo rejected', ['error' => $e->getMessage()]);
            }
        }

        return $stored === [] ? null : $stored;
    }
}
