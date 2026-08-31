<?php

namespace App\Http\Controllers\Web\Dispatcher;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $dispatcher = auth()->user();

        $query = Client::where('dispatcher_id', $dispatcher->id);

        // Search by name or phone
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        $clients = $query->latest()->paginate(15)->withQueryString();
        
        $totalClients = Client::where('dispatcher_id', $dispatcher->id)->count();

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
        $validatedData['dispatcher_id'] = auth()->id();

        Client::create($validatedData);

        return redirect()->route('dispatcher.client.index')->with('success', 'Client profile created successfully.');
    }

    public function edit($id)
    {
        $client = Client::where('dispatcher_id', auth()->id())->findOrFail($id);
        
        return view('content.dispatcher.client.form', compact('client'));
    }

    public function update(Request $request, $id)
    {
        $client = Client::where('dispatcher_id', auth()->id())->findOrFail($id);

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

    public function destroy($id)
    {
        $client = Client::where('dispatcher_id', auth()->id())->findOrFail($id);
        $client->delete();

        return redirect()->back()->with('success', 'Client deleted successfully.');
    }
}
