<?php

namespace App\Http\Controllers\Web\Dispatcher;

use App\Http\Controllers\Controller;
use App\Models\TripIncident;
use App\Models\User;
use App\Services\SignatureService;
use App\Services\SocketEmitter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The office's incident queue, including SOS alerts.
 *
 * Open incidents lead, sorted so anything critical sits at the top — this is a
 * screen someone glances at while doing something else, so the ordering has to
 * carry the urgency without being read closely.
 */
class IncidentController extends Controller
{
    public function __construct(private readonly SocketEmitter $socket)
    {
    }

    public function index(Request $request)
    {
        $companyId = auth()->user()->companyId();

        $query = TripIncident::forDispatcher($companyId)
            ->with(['driver', 'trip', 'vehicle', 'acknowledgedBy']);

        if ($request->filled('status') && array_key_exists($request->status, TripIncident::STATUSES)) {
            $query->where('status', $request->status);
        } elseif (! $request->filled('status')) {
            // The default view is work still to do.
            $query->open();
        }

        if ($request->filled('type') && array_key_exists($request->type, TripIncident::TYPES)) {
            $query->where('type', $request->type);
        }

        if ($request->filled('driver')) {
            $query->where('driver_id', (int) $request->input('driver'));
        }

        $incidents = $query
            // SOS first, then by severity, then newest.
            ->orderByRaw("CASE WHEN type = 'sos' THEN 0 ELSE 1 END")
            ->orderByRaw("FIELD(severity, 'critical', 'high', 'medium', 'low')")
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $counts = [
            'open'         => TripIncident::forDispatcher($companyId)->where('status', 'open')->count(),
            'acknowledged' => TripIncident::forDispatcher($companyId)->where('status', 'acknowledged')->count(),
            'resolved'     => TripIncident::forDispatcher($companyId)->where('status', 'resolved')->count(),
            'sos_open'     => TripIncident::forDispatcher($companyId)->open()->where('type', 'sos')->count(),
        ];

        $drivers = User::driversOf($companyId)->orderBy('name')->get();

        return view('content.dispatcher.incidents.list', compact('incidents', 'counts', 'drivers'));
    }

    public function show($id)
    {
        $incident = $this->find($id);
        $incident->load(['driver', 'trip.client', 'vehicle', 'acknowledgedBy']);

        $photos = collect($incident->attachments ?? [])
            ->map(fn (string $path) => app(SignatureService::class)->temporaryUrl($path))
            ->filter()
            ->values();

        return view('content.dispatcher.incidents.show', compact('incident', 'photos'));
    }

    /**
     * "We have seen it." Sent straight back to the driver, because the whole
     * point of an SOS is knowing somebody is looking.
     */
    public function acknowledge($id)
    {
        $incident = $this->find($id);

        if ($incident->isResolved()) {
            return back()->with('error', 'That incident is already resolved.');
        }

        $incident->forceFill([
            'status'          => 'acknowledged',
            'acknowledged_by' => auth()->id(),
            'acknowledged_at' => now(),
        ])->save();

        $this->notifyDriver($incident, 'incident:acknowledged');

        return back()->with('success', 'Incident acknowledged. The driver has been told.');
    }

    public function resolve(Request $request, $id)
    {
        $request->validate([
            'resolution_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $incident = $this->find($id);

        $incident->forceFill([
            'status'          => 'resolved',
            'resolved_at'     => now(),
            'resolution_note' => $request->input('resolution_note'),
            // An incident resolved without being acknowledged still needs an
            // owner on the record.
            'acknowledged_by' => $incident->acknowledged_by ?: auth()->id(),
            'acknowledged_at' => $incident->acknowledged_at ?: now(),
        ])->save();

        $this->notifyDriver($incident, 'incident:resolved');

        return redirect()
            ->route('dispatcher.incidents.index')
            ->with('success', 'Incident resolved.');
    }

    public function updateSeverity(Request $request, $id)
    {
        $request->validate([
            'severity' => ['required', Rule::in(array_keys(TripIncident::SEVERITIES))],
        ]);

        $incident = $this->find($id);
        $incident->update(['severity' => $request->input('severity')]);

        return back()->with('success', 'Severity updated.');
    }

    /**
     * Unread count and the newest open alerts, for the navbar badge.
     */
    public function summary(): JsonResponse
    {
        $companyId = auth()->user()->companyId();

        $open = TripIncident::forDispatcher($companyId)
            ->open()
            ->with('driver')
            ->orderByRaw("CASE WHEN type = 'sos' THEN 0 ELSE 1 END")
            ->latest()
            ->limit(5)
            ->get();

        return response()->json([
            'status' => true,
            'data'   => [
                'open_count' => TripIncident::forDispatcher($companyId)->open()->count(),
                'sos_count'  => TripIncident::forDispatcher($companyId)->open()->where('type', 'sos')->count(),
                'recent'     => $open->map(fn (TripIncident $incident) => [
                    'id'       => $incident->id,
                    'headline' => $incident->headline(),
                    'driver'   => $incident->driver?->name,
                    'is_sos'   => $incident->isSos(),
                    'severity' => $incident->severity,
                    'ago'      => $incident->created_at->diffForHumans(),
                    'url'      => route('dispatcher.incidents.show', $incident->id),
                ])->values(),
            ],
        ]);
    }

    private function notifyDriver(TripIncident $incident, string $event): void
    {
        $this->socket->queue(
            SocketEmitter::userRoom($incident->driver_id),
            $event,
            [
                'id'     => $incident->id,
                'status' => $incident->status,
                'by'     => auth()->user()->name,
                'note'   => $incident->resolution_note,
            ]
        );
    }

    private function find($id): TripIncident
    {
        return TripIncident::forDispatcher(auth()->user()->companyId())->findOrFail($id);
    }
}
