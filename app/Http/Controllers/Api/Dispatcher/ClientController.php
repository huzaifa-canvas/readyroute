<?php

namespace App\Http\Controllers\Api\Dispatcher;

use App\Http\Resources\Dispatcher\ClientNoteResource;
use App\Http\Resources\Dispatcher\ClientResource;
use App\Http\Resources\Dispatcher\TripListResource;
use App\Models\Client;
use App\Models\ClientNote;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Clients, and the running note thread the office keeps on each of them.
 *
 * Notes carry their author from the session, never the request, so a note can
 * always be traced back to whoever actually wrote it.
 */
class ClientController extends BaseDispatcherController
{
    public function index(Request $request): JsonResponse
    {
        $query = $this->clients()->withCount('trips');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('funding_type')) {
            $query->where('funding_type', $request->input('funding_type'));
        }

        return $this->paginated(
            ClientResource::collection($query->latest()->paginate($this->perPage()))
        );
    }

    /**
     * The profile screen: details, the note thread and recent trips, which are
     * the three tabs it draws.
     */
    public function show(int $id): JsonResponse
    {
        $client = $this->clients()->with(['notes.author'])->withCount('trips')->find($id);

        if (! $client) {
            return $this->notFound('Client not found.');
        }

        return $this->ok([
            'client' => new ClientResource($client),
            'trips'  => TripListResource::collection(
                Trip::where('client_id', $client->id)
                    ->with(['driver', 'vehicle', 'client'])
                    ->latest('pickup_date')
                    ->limit(20)
                    ->get()
            ),
            'trip_count' => $client->trips_count,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());

        $client = Client::create(
            $this->withFlags($request, $data) + ['dispatcher_id' => $this->companyId()]
        );

        return $this->created(new ClientResource($client), 'Client profile created.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $client = $this->clients()->find($id);

        if (! $client) {
            return $this->notFound('Client not found.');
        }

        $data = $request->validate($this->rules());

        $client->update($this->withFlags($request, $data));

        return $this->ok(new ClientResource($client->fresh()), 'Client profile updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        $client = $this->clients()->find($id);

        if (! $client) {
            return $this->notFound('Client not found.');
        }

        $client->delete();

        return $this->ok(null, 'Client deleted.');
    }

    // ── Notes ────────────────────────────────────────────────────────

    public function notes(int $id): JsonResponse
    {
        $client = $this->clients()->with('notes.author')->find($id);

        return $client
            ? $this->ok(ClientNoteResource::collection($client->notes))
            : $this->notFound('Client not found.');
    }

    public function storeNote(Request $request, int $id): JsonResponse
    {
        $client = $this->clients()->find($id);

        if (! $client) {
            return $this->notFound('Client not found.');
        }

        $request->validate([
            'body'              => ['required', 'string', 'max:2000'],
            'visible_to_driver' => ['nullable', 'boolean'],
        ]);

        $note = ClientNote::create([
            'client_id'         => $client->id,
            'dispatcher_id'     => $client->dispatcher_id,
            'author_id'         => $this->user()->id,
            'body'              => $request->input('body'),
            'visible_to_driver' => $request->boolean('visible_to_driver'),
        ]);

        return $this->created(new ClientNoteResource($note->load('author')), 'Note added.');
    }

    public function updateNote(Request $request, int $id, int $noteId): JsonResponse
    {
        $client = $this->clients()->find($id);

        if (! $client) {
            return $this->notFound('Client not found.');
        }

        $note = ClientNote::where('client_id', $client->id)->find($noteId);

        if (! $note) {
            return $this->notFound('Note not found.');
        }

        $request->validate([
            'body'              => ['required', 'string', 'max:2000'],
            'visible_to_driver' => ['nullable', 'boolean'],
        ]);

        $note->update([
            'body'              => $request->input('body'),
            'visible_to_driver' => $request->boolean('visible_to_driver'),
        ]);

        return $this->ok(new ClientNoteResource($note->fresh()->load('author')), 'Note updated.');
    }

    public function destroyNote(int $id, int $noteId): JsonResponse
    {
        $client = $this->clients()->find($id);

        if (! $client) {
            return $this->notFound('Client not found.');
        }

        $note = ClientNote::where('client_id', $client->id)->find($noteId);

        if (! $note) {
            return $this->notFound('Note not found.');
        }

        $note->delete();

        return $this->ok(null, 'Note deleted.');
    }

    // ── Shared pieces ────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'full_name'    => ['required', 'string', 'max:255'],
            'dob'          => ['nullable', 'date'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'email'        => ['nullable', 'email', 'max:255'],
            'home_address' => ['nullable', 'string', 'max:255'],
            'apt_unit'     => ['nullable', 'string', 'max:50'],
            'city'         => ['nullable', 'string', 'max:100'],
            'zip_code'     => ['nullable', 'string', 'max:20'],
            'funding_type' => ['nullable', 'string', 'in:medicaid,medicare,private,insurance'],
            'insurance_id' => ['nullable', 'string', 'max:100'],
            'emergency_contact_name'  => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'special_notes' => ['nullable', 'string'],

            'wheelchair_required'   => ['nullable', 'boolean'],
            'ambulatory_assistance' => ['nullable', 'boolean'],
            'stretcher_transport'   => ['nullable', 'boolean'],
            'bariatric_vehicle'     => ['nullable', 'boolean'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withFlags(Request $request, array $data): array
    {
        foreach ([
            'wheelchair_required',
            'ambulatory_assistance',
            'stretcher_transport',
            'bariatric_vehicle',
        ] as $flag) {
            $data[$flag] = $request->boolean($flag) ? 1 : 0;
        }

        return $data;
    }
}
