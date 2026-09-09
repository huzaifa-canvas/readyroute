<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CompanyController extends Controller
{
    /**
     * List all dispatcher "companies"
     */
    public function index(Request $request)
    {
        $query = User::where('role', 'dispatcher')
            ->withCount(['drivers', 'vehicles']);

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $companies = $query->latest()->paginate(15);

        return view('content.admin.companies.list', compact('companies'));
    }

    /**
     * Show register company form
     */
    public function create()
    {
        return view('content.admin.companies.create');
    }

    /**
     * Store a new dispatcher as a "company"
     */
    public function store(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'phone_number' => 'nullable|string|max:50',
            'region'   => 'nullable|string|max:255',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'role'     => 'dispatcher',
            'phone_number' => $request->phone_number,
        ]);

        // Save region as meta
        if ($request->filled('region')) {
            $user->setMeta('region', $request->region);
        }

        return redirect()->route('admin.company.list')->with('success', 'Company registered successfully.');
    }

    /**
     * Delete a dispatcher "company"
     */
    public function destroy($id)
    {
        $company = User::where('role', 'dispatcher')->findOrFail($id);

        // Prevent deleting yourself
        if ($company->id === auth()->id()) {
            return redirect()->back()->with('error', 'You cannot delete your own account.');
        }

        $company->delete();

        return redirect()->back()->with('success', 'Company removed successfully.');
    }
}
