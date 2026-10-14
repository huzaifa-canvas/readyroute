<?php

namespace App\Http\Controllers\Api\Dispatcher;

use App\Models\DriverDocument;
use App\Models\User;
use App\Services\SignatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Licences, medical cards and insurance.
 *
 * The board is sorted worst-first so whoever is blocked appears at the top. A
 * driver with no documents at all counts as a problem in its own right, not a
 * clean sheet — that distinction is the whole reason this screen exists.
 *
 * The files themselves live on a private disk: they are identity documents and
 * must never be reachable by URL alone, so downloads are handed out as short
 * lived links rather than paths.
 */
class ComplianceController extends BaseDispatcherController
{
    public function index(Request $request): JsonResponse
    {
        $rank = ['expired' => 0, 'expiring' => 1, 'missing' => 2, 'valid' => 3];

        $rows = $this->drivers()
            ->with(['documents' => fn ($q) => $q->orderBy('expires_on')])
            ->orderBy('name')
            ->get()
            ->map(function (User $driver) {
                $documents = $driver->documents;

                $expired  = $documents->filter->isExpired();
                $expiring = $documents->filter->isExpiring();

                $state = match (true) {
                    $expired->isNotEmpty()  => 'expired',
                    $expiring->isNotEmpty() => 'expiring',
                    $documents->isEmpty()   => 'missing',
                    default                 => 'valid',
                };

                $soonest = $documents->whereNotNull('expires_on')->sortBy('expires_on')->first();

                return [
                    'driver' => [
                        'id'         => $driver->id,
                        'name'       => $driver->name,
                        'code'       => $driver->driver_code,
                        'avatar_url' => $driver->avatar_url,
                    ],
                    'state'          => $state,
                    'state_label'    => ucfirst($state),
                    'document_count' => $documents->count(),
                    'expired'        => $expired->count(),
                    'expiring'       => $expiring->count(),
                    'soonest_expiry' => optional($soonest?->expires_on)->toDateString(),
                ];
            })
            ->sortBy(fn ($row) => $rank[$row['state']])
            ->values();

        $counts = [
            'expired'  => $rows->where('state', 'expired')->count(),
            'expiring' => $rows->where('state', 'expiring')->count(),
            'missing'  => $rows->where('state', 'missing')->count(),
            'valid'    => $rows->where('state', 'valid')->count(),
        ];

        if ($request->filled('state') && array_key_exists($request->input('state'), $rank)) {
            $rows = $rows->where('state', $request->input('state'))->values();
        }

        return $this->ok([
            'drivers' => $rows,
            'counts'  => $counts,
            // Vehicles matter to compliance too, but only their maintenance
            // state is tracked today, so this is kept deliberately small.
            'vehicles_in_maintenance' => $this->vehicles()->where('status', 'maintenance')->count(),
            'document_types' => collect(DriverDocument::TYPES)
                ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                ->values(),
        ]);
    }

    /**
     * One driver's documents.
     */
    public function driver(int $driverId): JsonResponse
    {
        $driver = $this->drivers()->find($driverId);

        if (! $driver) {
            return $this->notFound('Driver not found.');
        }

        return $this->ok([
            'driver' => [
                'id'         => $driver->id,
                'name'       => $driver->name,
                'code'       => $driver->driver_code,
                'avatar_url' => $driver->avatar_url,
            ],
            'documents' => DriverDocument::where('driver_id', $driver->id)
                ->with('uploader')
                ->orderByRaw('expires_on IS NULL')
                ->orderBy('expires_on')
                ->get()
                ->map(fn (DriverDocument $doc) => $this->row($doc)),
            'document_types' => collect(DriverDocument::TYPES)
                ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                ->values(),
        ]);
    }

    public function store(Request $request, int $driverId): JsonResponse
    {
        $driver = $this->drivers()->find($driverId);

        if (! $driver) {
            return $this->notFound('Driver not found.');
        }

        $data = $request->validate($this->rules());

        $data['dispatcher_id'] = $this->companyId();
        $data['driver_id']     = $driver->id;
        $data['uploaded_by']   = $this->user()->id;

        if ($request->hasFile('file')) {
            $data['file_path'] = $this->storeFile($request, $driver->id);
        }

        $document = DriverDocument::create($data);

        return $this->created($this->row($document), 'Document added.');
    }

    public function update(Request $request, int $driverId, int $documentId): JsonResponse
    {
        $driver = $this->drivers()->find($driverId);

        if (! $driver) {
            return $this->notFound('Driver not found.');
        }

        $document = DriverDocument::where('driver_id', $driver->id)->find($documentId);

        if (! $document) {
            return $this->notFound('Document not found.');
        }

        $data = $request->validate($this->rules());

        if ($request->hasFile('file')) {
            $data['file_path'] = $this->storeFile($request, $driver->id);
        }

        $document->update($data);

        return $this->ok($this->row($document->fresh()), 'Document updated.');
    }

    public function destroy(int $driverId, int $documentId): JsonResponse
    {
        $driver = $this->drivers()->find($driverId);

        if (! $driver) {
            return $this->notFound('Driver not found.');
        }

        $document = DriverDocument::where('driver_id', $driver->id)->find($documentId);

        if (! $document) {
            return $this->notFound('Document not found.');
        }

        $document->delete();

        return $this->ok(null, 'Document removed.');
    }

    /**
     * A short lived link to the file itself.
     */
    public function download(int $driverId, int $documentId, SignatureService $signatures): JsonResponse
    {
        $driver = $this->drivers()->find($driverId);

        if (! $driver) {
            return $this->notFound('Driver not found.');
        }

        $document = DriverDocument::where('driver_id', $driver->id)->find($documentId);

        if (! $document || ! $document->file_path) {
            return $this->notFound('There is no file on that document.');
        }

        return $this->ok(['url' => $signatures->temporaryUrl($document->file_path)]);
    }

    // ── Shared pieces ────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'type'       => ['required', Rule::in(array_keys(DriverDocument::TYPES))],
            'label'      => ['nullable', 'string', 'max:160'],
            'reference'  => ['nullable', 'string', 'max:120'],
            'issued_on'  => ['nullable', 'date'],
            'expires_on' => ['nullable', 'date', 'after_or_equal:issued_on'],
            'notes'      => ['nullable', 'string', 'max:2000'],
            'file'       => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    private function storeFile(Request $request, int $driverId): string
    {
        // Private disk, same as signatures.
        return $request->file('file')->store(
            'driver-documents/' . $driverId,
            config('readyroute.signatures.disk', 'local')
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function row(DriverDocument $doc): array
    {
        return [
            'id'         => $doc->id,
            'type'       => $doc->type,
            'type_label' => DriverDocument::TYPES[$doc->type] ?? $doc->type,
            'label'      => $doc->label,
            'reference'  => $doc->reference,
            'issued_on'  => optional($doc->issued_on)->toDateString(),
            'expires_on' => optional($doc->expires_on)->toDateString(),
            'is_expired' => $doc->isExpired(),
            'is_expiring' => $doc->isExpiring(),
            'days_left'  => $doc->expires_on
                ? (int) now()->startOfDay()->diffInDays($doc->expires_on, false)
                : null,
            'status'     => $doc->status,
            'notes'      => $doc->notes,
            'has_file'   => (bool) $doc->file_path,
            'uploaded_by' => $doc->uploader?->name,
            'created_at' => optional($doc->created_at)->toIso8601String(),
        ];
    }
}
