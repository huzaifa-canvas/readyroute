<?php

namespace App\Http\Controllers\Web\Dispatcher;

use App\Http\Controllers\Controller;
use App\Models\DriverDocument;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Compliance Center.
 *
 * One board answering a single question: is anything about to lapse? Drivers
 * are grouped by their worst document, because a roster is scanned for
 * problems rather than read row by row.
 */
class ComplianceController extends Controller
{
    public function index(Request $request)
    {
        $companyId = auth()->user()->companyId();

        $drivers = User::driversOf($companyId)
            ->with(['documents' => fn ($q) => $q->orderBy('expires_on')])
            ->orderBy('name')
            ->get();

        // Worst-first, so whoever is blocked appears at the top.
        $rank = ['expired' => 0, 'expiring' => 1, 'missing' => 2, 'valid' => 3];

        $rows = $drivers->map(function (User $driver) {
            $documents = $driver->documents;

            $expired  = $documents->filter->isExpired();
            $expiring = $documents->filter->isExpiring();

            // A driver with no documents at all is a compliance problem in
            // its own right, not a clean sheet.
            $state = match (true) {
                $expired->isNotEmpty()    => 'expired',
                $expiring->isNotEmpty()   => 'expiring',
                $documents->isEmpty()     => 'missing',
                default                   => 'valid',
            };

            return (object) [
                'driver'    => $driver,
                'documents' => $documents,
                'expired'   => $expired->count(),
                'expiring'  => $expiring->count(),
                'state'     => $state,
                'soonest'   => $documents->whereNotNull('expires_on')->sortBy('expires_on')->first(),
            ];
        })
        ->sortBy(fn ($row) => $rank[$row->state])
        ->values();

        if ($request->filled('state') && array_key_exists($request->state, $rank)) {
            $rows = $rows->where('state', $request->state)->values();
        }

        $counts = [
            'expired'  => $rows->where('state', 'expired')->count(),
            'expiring' => $rows->where('state', 'expiring')->count(),
            'missing'  => $rows->where('state', 'missing')->count(),
            'valid'    => $rows->where('state', 'valid')->count(),
        ];

        // Vehicles matter to compliance too, but only their inspection state
        // is tracked today, so this is kept deliberately small.
        $vehiclesInMaintenance = Vehicle::where('dispatcher_id', $companyId)
            ->where('status', 'maintenance')
            ->count();

        return view('content.dispatcher.compliance.index', compact('rows', 'counts', 'vehiclesInMaintenance'));
    }

    /**
     * One driver's documents, with the form to add another.
     */
    public function driver($driverId)
    {
        $driver = $this->findDriver($driverId);

        $documents = DriverDocument::where('driver_id', $driver->id)
            ->with('uploader')
            ->orderByRaw('expires_on IS NULL')
            ->orderBy('expires_on')
            ->get();

        return view('content.dispatcher.compliance.driver', compact('driver', 'documents'));
    }

    public function store(Request $request, $driverId)
    {
        $driver = $this->findDriver($driverId);

        $data = $request->validate([
            'type'       => ['required', Rule::in(array_keys(DriverDocument::TYPES))],
            'label'      => ['nullable', 'string', 'max:160'],
            'reference'  => ['nullable', 'string', 'max:120'],
            'issued_on'  => ['nullable', 'date'],
            'expires_on' => ['nullable', 'date', 'after_or_equal:issued_on'],
            'notes'      => ['nullable', 'string', 'max:2000'],
            'file'       => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $data['dispatcher_id'] = $driver->companyId();
        $data['driver_id']     = $driver->id;
        $data['uploaded_by']   = auth()->id();

        if ($request->hasFile('file')) {
            // Private disk, same as signatures: these are identity documents
            // and must never be reachable by URL alone.
            $data['file_path'] = $request->file('file')->store(
                'driver-documents/' . $driver->id,
                config('readyroute.signatures.disk', 'local')
            );
        }

        DriverDocument::create($data);

        return back()->with('success', 'Document added.');
    }

    public function update(Request $request, $driverId, $documentId)
    {
        $driver   = $this->findDriver($driverId);
        $document = DriverDocument::where('driver_id', $driver->id)->findOrFail($documentId);

        $data = $request->validate([
            'type'       => ['required', Rule::in(array_keys(DriverDocument::TYPES))],
            'label'      => ['nullable', 'string', 'max:160'],
            'reference'  => ['nullable', 'string', 'max:120'],
            'issued_on'  => ['nullable', 'date'],
            'expires_on' => ['nullable', 'date', 'after_or_equal:issued_on'],
            'notes'      => ['nullable', 'string', 'max:2000'],
            'file'       => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        if ($request->hasFile('file')) {
            $this->deleteFile($document);

            $data['file_path'] = $request->file('file')->store(
                'driver-documents/' . $driver->id,
                config('readyroute.signatures.disk', 'local')
            );
        }

        $document->update($data);

        return back()->with('success', 'Document updated.');
    }

    public function destroy($driverId, $documentId)
    {
        $driver   = $this->findDriver($driverId);
        $document = DriverDocument::where('driver_id', $driver->id)->findOrFail($documentId);

        $this->deleteFile($document);
        $document->delete();

        return back()->with('success', 'Document removed.');
    }

    /**
     * Hand the file over through a short-lived signed URL rather than a path,
     * so a link copied out of the page stops working.
     */
    public function download($driverId, $documentId)
    {
        $driver   = $this->findDriver($driverId);
        $document = DriverDocument::where('driver_id', $driver->id)->findOrFail($documentId);

        abort_if(! $document->file_path, 404, 'No file was attached to this document.');

        $disk = config('readyroute.signatures.disk', 'local');

        abort_if(! Storage::disk($disk)->exists($document->file_path), 404, 'The stored file is missing.');

        return Storage::disk($disk)->download(
            $document->file_path,
            $driver->name . ' — ' . $document->typeLabel() . '.' . pathinfo($document->file_path, PATHINFO_EXTENSION)
        );
    }

    private function deleteFile(DriverDocument $document): void
    {
        if (! $document->file_path) {
            return;
        }

        Storage::disk(config('readyroute.signatures.disk', 'local'))->delete($document->file_path);
    }

    private function findDriver($driverId): User
    {
        return User::driversOf(auth()->user()->companyId())->findOrFail($driverId);
    }
}
