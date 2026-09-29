<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Platform-wide role templates.
 *
 * These are the roles every company can assign — Head Dispatcher, Standard
 * Dispatcher, Read-Only Analyst and anything the admin adds. A company's own
 * custom roles are managed from the dispatcher panel and are not listed here.
 */
class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::whereNull('dispatcher_id')
            ->withCount('users')
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get();

        // Shown alongside each role so the admin can see how much a template
        // actually grants without opening it.
        $totalPermissions = count(Permissions::all());

        return view('content.admin.roles.list', compact('roles', 'totalPermissions'));
    }

    public function create()
    {
        $role   = new Role(['permissions' => []]);
        $groups = Permissions::groups();

        return view('content.admin.roles.form', compact('role', 'groups'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        Role::create([
            'dispatcher_id' => null,
            'name'          => $data['name'],
            'description'   => $data['description'] ?? null,
            'permissions'   => Permissions::clean($request->input('permissions', [])),
            'is_system'     => false,
        ]);

        return redirect()
            ->route('admin.role.list')
            ->with('success', 'Role created.');
    }

    public function edit($id)
    {
        $role   = Role::whereNull('dispatcher_id')->findOrFail($id);
        $groups = Permissions::groups();

        return view('content.admin.roles.form', compact('role', 'groups'));
    }

    public function update(Request $request, $id)
    {
        $role = Role::whereNull('dispatcher_id')->findOrFail($id);
        $data = $this->validated($request, $role->id);

        $role->update([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'permissions' => Permissions::clean($request->input('permissions', [])),
        ]);

        return redirect()
            ->route('admin.role.list')
            ->with('success', 'Role updated.');
    }

    public function destroy($id)
    {
        $role = Role::whereNull('dispatcher_id')->withCount('users')->findOrFail($id);

        if (! $role->isDeletable()) {
            return back()->with('error', 'System roles cannot be deleted. You can edit their permissions instead.');
        }

        if ($role->users_count > 0) {
            return back()->with(
                'error',
                'This role is assigned to ' . $role->users_count . ' user(s). Reassign them before deleting it.'
            );
        }

        $role->delete();

        return back()->with('success', 'Role deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('roles', 'name')->whereNull('dispatcher_id')->ignore($ignoreId),
            ],
            'description'   => ['nullable', 'string', 'max:255'],
            // Only the shape is validated. Which keys are real is decided by
            // Permissions::clean(), which drops anything unknown — a tampered
            // checkbox should be ignored, not bounce the whole form back with
            // an error the UI could never have produced.
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ]);
    }
}
