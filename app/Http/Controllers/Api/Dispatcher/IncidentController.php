<?php

namespace App\Http\Controllers\Api\Dispatcher;

use App\Models\TripIncident;
use App\Services\SignatureService;
use App\Services\SocketEmitter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Incidents the drivers raise, including the SOS panic button.
 *
 * Acknowledging one is sent straight back to the driver over the socket,
 * because the whole point of an SOS is knowing somebody is looking at it.
 */
class IncidentController extends BaseDispatcherController
{
    public function __construct(private readonly SocketEmitter $socket)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $companyId = $this->companyId();

        $query = TripIncident::forDispatcher($companyId)
            ->with(['driver', 'trip', 'vehicle', 'acknowledgedBy']);

        if ($request->filled('status') && array_key_exists($request->input('status'), TripIncident::STATUSES)) {
            $query->where('status', $request->input('status'));
        } elseif (! $request->filled('status')) {
            // The default view is work still to do.
            $query->open();
        }

        if ($request->filled('type') && array_key_exists($request->input('type'), TripIncident::TYPES)) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('driver_id')) {
            $query->where('driver_id', $request->integer('driver_id'));
        }

        $page = $query
            // SOS first, then by severity, then newest.
            ->orderByRaw("CASE WHEN type = 'sos' THEN 0 ELSE 1 END")
            ->orderByRaw("FIELD(severity, 'critical', 'high', 'medium', 'low')")
            ->latest()
            ->paginate($this->perPage());

        return response()->json([
            'status'  => true,
            'message' => null,
            'data'    => [
                'incidents' => collect($page->items())->map(fn ($i) => $this->row($i))->values(),
                'counts' => [
                    'open'         => TripIncident::forDispatcher($companyId)->where('status', 'open')->count(),
                    'acknowledged' => TripIncident::forDispatcher($companyId)->where('status', 'acknowledged')->count(),
                    'resolved'     => TripIncident::forDispatcher($companyId)->where('status', 'resolved')->count(),
                    'sos_open'     => TripIncident::forDispatcher($companyId)->open()->where('type', 'sos')->count(),
                ],
                'types'      => collect(TripIncident::TYPES)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values(),
                'severities' => collect(TripIncident::SEVERITIES)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values(),
            ],
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'has_more'     => $page->hasMorePages(),
            ],
            'errors' => null,
        ]);
    }

    public function show(int $id, SignatureService $signatures): JsonResponse
    {
        $incident = $this->find($id);

        if (! $incident) {
            return $this->notFound('Incident not found.');
        }

        $incident->load(['driver', 'trip.client', 'vehicle', 'acknowledgedBy']);

        return $this->ok($this->row($incident) + [
            'description' => $incident->description,
            'location'    => $incident->lat !== null && $incident->lng !== null ? [
                'lat' => (float) $incident->lat,
                'lng' => (float) $incident->lng,
            ] : null,
            'resolution_note' => $incident->resolution_note,
            // The files live on a private disk, so these are temporary links.
            'photos' => collect($incident->attachments ?? [])
                ->map(fn (string $path) => $signatures->temporaryUrl($path))
                ->filter()
                ->values(),
        ]);
    }

    /**
     * "We have seen it."
     */
    public function acknowledge(int $id): JsonResponse
    {
        $incident = $this->find($id);

        if (! $incident) {
            return $this->notFound('Incident not found.');
        }

        if ($incident->isResolved()) {
            return $this->fail('That incident is already resolved.', 422);
        }

        $incident->forceFill([
            'status'          => 'acknowledged',
            'acknowledged_by' => $this->user()->id,
            'acknowledged_at' => now(),
        ])->save();

        $this->notifyDriver($incident, 'incident:acknowledged');

        return $this->ok($this->row($incident->fresh()->load(['driver', 'acknowledgedBy'])),
            'Incident acknowledged. The driver has been told.');
    }

    public function resolve(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'resolution_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $incident = $this->find($id);

        if (! $incident) {
            return $this->notFound('Incident not found.');
        }

        $incident->forceFill([
            'status'          => 'resolved',
            'resolved_at'     => now(),
            'resolution_note' => $request->input('resolution_note'),
            // An incident resolved without being acknowledged still needs an
            // owner on the record.
            'acknowledged_by' => $incident->acknowledged_by ?: $this->user()->id,
            'acknowledged_at' => $incident->acknowledged_at ?: now(),
        ])->save();

        $this->notifyDriver($incident, 'incident:resolved');

        return $this->ok($this->row($incident->fresh()->load(['driver', 'acknowledgedBy'])), 'Incident resolved.');
    }

    public function updateSeverity(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'severity' => ['required', Rule::in(array_keys(TripIncident::SEVERITIES))],
        ]);

        $incident = $this->find($id);

        if (! $incident) {
            return $this->notFound('Incident not found.');
        }

        $incident->update(['severity' => $request->input('severity')]);

        return $this->ok($this->row($incident->fresh()->load('driver')), 'Severity updated.');
    }

    // ── Shared pieces ────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function row(TripIncident $incident): array
    {
        return [
            'id'       => $incident->id,
            'headline' => $incident->headline(),
            'type'     => $incident->type,
            'type_label' => TripIncident::TYPES[$incident->type] ?? $incident->type,
            'severity' => $incident->severity,
            'severity_label' => TripIncident::SEVERITIES[$incident->severity] ?? $incident->severity,
            'status'   => $incident->status,
            'status_label' => TripIncident::STATUSES[$incident->status] ?? $incident->status,
            'is_sos'   => $incident->isSos(),
            'is_open'  => ! $incident->isResolved(),

            'driver' => $incident->driver ? [
                'id'   => $incident->driver->id,
                'name' => $incident->driver->name,
                'phone' => $incident->driver->phone_number,
            ] : null,

            'trip' => $incident->trip ? [
                'id'        => $incident->trip->id,
                'reference' => $incident->trip->reference(),
            ] : null,

            'vehicle' => $incident->vehicle?->name,

            'acknowledged_by' => $incident->acknowledgedBy?->name,
            'acknowledged_at' => optional($incident->acknowledged_at)->toIso8601String(),
            'resolved_at'     => optional($incident->resolved_at)->toIso8601String(),

            'created_at' => optional($incident->created_at)->toIso8601String(),
            'ago'        => optional($incident->created_at)->diffForHumans(),
        ];
    }

    private function notifyDriver(TripIncident $incident, string $event): void
    {
        $this->socket->queue(
            SocketEmitter::userRoom($incident->driver_id),
            $event,
            [
                'id'     => $incident->id,
                'status' => $incident->status,
                'by'     => $this->user()->name,
                'note'   => $incident->resolution_note,
            ]
        );
    }

    private function find(int $id): ?TripIncident
    {
        return TripIncident::forDispatcher($this->companyId())->find($id);
    }
}
