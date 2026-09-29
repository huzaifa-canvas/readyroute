<?php

namespace App\Http\Controllers\Web\Dispatcher;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientNote;
use App\Models\Trip;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $dispatcher = auth()->user();

        $query = Client::where('dispatcher_id', $dispatcher->companyId());

        // Search by name or phone
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        $clients = $query->latest()->paginate(15)->withQueryString();
        
        $totalClients = Client::where('dispatcher_id', $dispatcher->companyId())->count();

        return view('content.dispatcher.client.list', compact('clients', 'totalClients'));
    }

    public function create()
    {
        return view('content.dispatcher.client.form');
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'full_name' => 'required|string|max:255',
            'dob' => 'nullable|date',
            'phone_number' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'home_address' => 'nullable|string|max:255',
            'apt_unit' => 'nullable|string|max:50',
            'city' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string|max:20',
            'funding_type' => 'nullable|string|in:medicaid,medicare,private,insurance',
            'insurance_id' => 'nullable|string|max:100',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'special_notes' => 'nullable|string',
        ]);

        $validatedData['wheelchair_required'] = $request->has('wheelchair_required') ? 1 : 0;
        $validatedData['ambulatory_assistance'] = $request->has('ambulatory_assistance') ? 1 : 0;
        $validatedData['stretcher_transport'] = $request->has('stretcher_transport') ? 1 : 0;
        $validatedData['bariatric_vehicle'] = $request->has('bariatric_vehicle') ? 1 : 0;
        $validatedData['dispatcher_id'] = auth()->user()->companyId();

        Client::create($validatedData);

        return redirect()->route('dispatcher.client.index')->with('success', 'Client profile created successfully.');
    }

    public function edit($id)
    {
        $client = $this->findClient($id);
        
        return view('content.dispatcher.client.form', compact('client'));
    }

    public function update(Request $request, $id)
    {
        $client = $this->findClient($id);

        $validatedData = $request->validate([
            'full_name' => 'required|string|max:255',
            'dob' => 'nullable|date',
            'phone_number' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'home_address' => 'nullable|string|max:255',
            'apt_unit' => 'nullable|string|max:50',
            'city' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string|max:20',
            'funding_type' => 'nullable|string|in:medicaid,medicare,private,insurance',
            'insurance_id' => 'nullable|string|max:100',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'special_notes' => 'nullable|string',
        ]);

        $validatedData['wheelchair_required'] = $request->has('wheelchair_required') ? 1 : 0;
        $validatedData['ambulatory_assistance'] = $request->has('ambulatory_assistance') ? 1 : 0;
        $validatedData['stretcher_transport'] = $request->has('stretcher_transport') ? 1 : 0;
        $validatedData['bariatric_vehicle'] = $request->has('bariatric_vehicle') ? 1 : 0;

        $client->update($validatedData);

        return redirect()->route('dispatcher.client.index')->with('success', 'Client profile updated successfully.');
    }

    /**
     * The client profile screen: contact details, requirements, trip history
     * and the running note thread dispatchers keep on the client.
     */
    public function show($id)
    {
        $client = Client::where('dispatcher_id', auth()->user()->companyId())
            ->with(['notes.author'])
            ->findOrFail($id);

        $trips = Trip::where('client_id', $client->id)
            ->with('driver')
            ->latest('pickup_date')
            ->limit(10)
            ->get();

        $tripCount = Trip::where('client_id', $client->id)->count();

        return view('content.dispatcher.client.show', compact('client', 'trips', 'tripCount'));
    }

    /**
     * Add a dated note. The author is taken from the session, never the form,
     * so a note can always be traced back to who actually wrote it.
     */
    public function storeNote(Request $request, $id)
    {
        $client = $this->findClient($id);

        $request->validate([
            'body'              => ['required', 'string', 'max:2000'],
            'visible_to_driver' => ['nullable', 'boolean'],
        ]);

        ClientNote::create([
            'client_id'         => $client->id,
            'dispatcher_id'     => $client->dispatcher_id,
            'author_id'         => auth()->id(),
            'body'              => $request->input('body'),
            'visible_to_driver' => $request->boolean('visible_to_driver'),
        ]);

        return back()->with('success', 'Note added.');
    }

    public function updateNote(Request $request, $id, $noteId)
    {
        $client = $this->findClient($id);

        $note = ClientNote::where('client_id', $client->id)->findOrFail($noteId);

        $request->validate([
            'body'              => ['required', 'string', 'max:2000'],
            'visible_to_driver' => ['nullable', 'boolean'],
        ]);

        $note->update([
            'body'              => $request->input('body'),
            'visible_to_driver' => $request->boolean('visible_to_driver'),
        ]);

        return back()->with('success', 'Note updated.');
    }

    public function destroyNote($id, $noteId)
    {
        $client = $this->findClient($id);

        ClientNote::where('client_id', $client->id)->findOrFail($noteId)->delete();

        return back()->with('success', 'Note deleted.');
    }

    /**
     * Clients are always reached through the signed-in user's company, so a
     * mistyped id can never reach another tenant's record.
     */
    private function findClient($id): Client
    {
        return Client::where('dispatcher_id', auth()->user()->companyId())->findOrFail($id);
    }

    public function destroy($id)
    {
        $client = $this->findClient($id);
        $client->delete();

        return redirect()->back()->with('success', 'Client deleted successfully.');
    }
}
