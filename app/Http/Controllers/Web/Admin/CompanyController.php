<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{
    /**
     * List all dispatcher "companies".
     */
    public function index(Request $request)
    {
        $query = User::query()
            ->companies()
            ->withCount(['drivers', 'vehicles', 'clients']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status') && in_array($request->status, ['active', 'suspended'], true)) {
            $query->where('status', $request->status);
        }

        $companies = $query->latest()->paginate(15)->withQueryString();

        // Shown as a tab count so the admin knows recoverable accounts exist.
        $archivedCount = User::onlyTrashed()->companies()->count();

        return view('content.admin.companies.list', compact('companies', 'archivedCount'));
    }

    /**
     * Archived companies, which can be restored or removed for good.
     */
    public function archived(Request $request)
    {
        $companies = User::onlyTrashed()
            ->companies()
            ->withCount(['drivers', 'vehicles'])
            ->latest('deleted_at')
            ->paginate(15);

        return view('content.admin.companies.archived', compact('companies'));
    }

    public function create()
    {
        return view('content.admin.companies.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'         => 'required|string|max:255',
            'email'        => 'required|email|max:255|unique:users,email',
            'password'     => 'required|string|min:8|confirmed',
            'phone_number' => 'nullable|string|max:50',
            'region'       => 'nullable|string|max:255',
        ]);

        $user = User::create([
            'name'         => $request->name,
            'email'        => $request->email,
            'password'     => Hash::make($request->password),
            'role'         => 'dispatcher',
            'status'       => 'active',
            'phone_number' => $request->phone_number,
        ]);

        if ($request->filled('region')) {
            $user->setMeta('region', $request->region);
        }

        return redirect()
            ->route('admin.company.show', $user->id)
            ->with('success', 'Company registered successfully.');
    }

    /**
     * The "Manage — <company>" screen: everything about one tenant in one
     * place, with the actions that change its standing.
     */
    public function show($id)
    {
        $company = $this->findCompany($id, withTrashed: true);

        $stats = [
            'drivers'  => User::driversOf($company->id)->count(),
            'vehicles' => Vehicle::where('dispatcher_id', $company->id)->count(),
            'clients'  => Client::where('dispatcher_id', $company->id)->count(),
            'trips'    => Trip::where('dispatcher_id', $company->id)->count(),
            'staff'    => User::staffOf($company->id)->count(),
        ];

        $stats['trips_this_month'] = Trip::where('dispatcher_id', $company->id)
            ->whereBetween('pickup_date', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();

        $stats['drivers_online'] = User::driversOf($company->id)
            ->get()
            ->filter
            ->isCurrentlyOnline()
            ->count();

        $recentTrips = Trip::where('dispatcher_id', $company->id)
            ->with(['client', 'driver'])
            ->latest()
            ->limit(8)
            ->get();

        $staff = User::staffOf($company->id)->with('accessRole')->get();

        return view('content.admin.companies.show', compact('company', 'stats', 'recentTrips', 'staff'));
    }

    public function edit($id)
    {
        $company = $this->findCompany($id);

        return view('content.admin.companies.edit', compact('company'));
    }

    public function update(Request $request, $id)
    {
        $company = $this->findCompany($id);

        $request->validate([
            'name'         => 'required|string|max:255',
            'email'        => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($company->id)],
            'phone_number' => 'nullable|string|max:50',
            'region'       => 'nullable|string|max:255',
            // Optional: only changed when the admin actually types one.
            'password'     => 'nullable|string|min:8|confirmed',
        ]);

        $company->fill([
            'name'         => $request->name,
            'email'        => $request->email,
            'phone_number' => $request->phone_number,
        ]);

        if ($request->filled('password')) {
            $company->password = Hash::make($request->password);
        }

        $company->save();

        $company->setMeta('region', $request->region);

        return redirect()
            ->route('admin.company.show', $company->id)
            ->with('success', 'Company details updated.');
    }

    /**
     * Lock an account out without losing anything it owns.
     */
    public function suspend(Request $request, $id)
    {
        $request->validate([
            'suspension_reason' => 'nullable|string|max:255',
        ]);

        $company = $this->findCompany($id);

        if ($company->id === auth()->id()) {
            return back()->with('error', 'You cannot suspend your own account.');
        }

        $company->suspend($request->input('suspension_reason'));

        return back()->with('success', $company->name . ' has been suspended.');
    }

    public function activate($id)
    {
        $company = $this->findCompany($id);
        $company->activate();

        return back()->with('success', $company->name . ' is active again.');
    }

    /**
     * Archive: a soft delete, so the tenant and its trips survive and the
     * account can be brought back from the archived list.
     */
    public function destroy($id)
    {
        $company = $this->findCompany($id);

        if ($company->id === auth()->id()) {
            return back()->with('error', 'You cannot remove your own account.');
        }

        $company->tokens()->delete();
        $company->delete();

        return redirect()
            ->route('admin.company.list')
            ->with('success', 'Company archived. You can restore it from the archived list.');
    }

    public function restore($id)
    {
        $company = User::onlyTrashed()->companies()->findOrFail($id);
        $company->restore();

        return redirect()
            ->route('admin.company.show', $company->id)
            ->with('success', $company->name . ' has been restored.');
    }

    /**
     * Permanent removal, only reachable from the archived list.
     */
    public function forceDelete(Request $request, $id)
    {
        $company = User::onlyTrashed()->companies()->findOrFail($id);

        // Typing the name is the one guard against deleting a tenant and every
        // trip, driver and client under it by mis-click.
        $request->validate([
            'confirm_name' => 'required|string',
        ]);

        if (trim($request->confirm_name) !== $company->name) {
            return back()->with('error', 'The name you typed did not match. Nothing was deleted.');
        }

        $name = $company->name;
        $company->forceDelete();

        return redirect()
            ->route('admin.company.archived')
            ->with('success', $name . ' was permanently deleted.');
    }

    private function findCompany($id, bool $withTrashed = false): User
    {
        $query = User::query()->companies();

        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query->findOrFail($id);
    }
}
