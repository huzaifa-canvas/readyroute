<?php

namespace App\Http\Controllers\Web\Dispatcher;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * System Users and their permissions.
 *
 * Staff are stored as dispatcher accounts carrying the company's
 * dispatcher_id, so every tenant-scoped query already covers them. What
 * changes per person is their role, which is what this screen manages.
 *
 * A company may also define roles of its own alongside the platform
 * templates the admin maintains.
 */
class UserController extends Controller
{
    public function index()
    {
        $companyId = auth()->user()->companyId();

        $users = User::staffOf($companyId)
            ->with('accessRole')
            ->orderBy('name')
            ->get();

        $owner = User::find($companyId);

        $roles = Role::availableTo($companyId)
            ->withCount('users')
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get();

        return view('content.dispatcher.users.index', compact('users', 'owner', 'roles'));
    }

    public function store(Request $request)
    {
        $this->authorizeOwner();

        $companyId = auth()->user()->companyId();

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone_number' => ['nullable', 'string', 'max:50'],
            'role_id'  => ['required', 'integer', Rule::exists('roles', 'id')],
        ]);

        // A role from another company must not be assignable here.
        $this->assertRoleBelongs((int) $data['role_id'], $companyId);

        User::create([
            'name'          => $data['name'],
            'email'         => $data['email'],
            'password'      => Hash::make($data['password']),
            'phone_number'  => $data['phone_number'] ?? null,
            'role'          => 'dispatcher',
            'dispatcher_id' => $companyId,
            'role_id'       => $data['role_id'],
            'status'        => 'active',
        ]);

        return back()->with('success', 'Panel user added.');
    }

    public function update(Request $request, $id)
    {
        $this->authorizeOwner();

        $companyId = auth()->user()->companyId();
        $user      = User::staffOf($companyId)->findOrFail($id);

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone_number' => ['nullable', 'string', 'max:50'],
            'role_id'  => ['required', 'integer', Rule::exists('roles', 'id')],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $this->assertRoleBelongs((int) $data['role_id'], $companyId);

        $user->fill([
            'name'         => $data['name'],
            'email'        => $data['email'],
            'phone_number' => $data['phone_number'] ?? null,
            'role_id'      => $data['role_id'],
        ]);

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
            // A password change should end sessions opened with the old one.
            $user->tokens()->delete();
        }

        $user->save();

        return back()->with('success', 'User updated.');
    }

    public function suspend(Request $request, $id)
    {
        $this->authorizeOwner();

        $user = User::staffOf(auth()->user()->companyId())->findOrFail($id);
        $user->suspend($request->input('suspension_reason'));

        return back()->with('success', $user->name . ' has been suspended.');
    }

    public function activate($id)
    {
        $this->authorizeOwner();

        $user = User::staffOf(auth()->user()->companyId())->findOrFail($id);
        $user->activate();

        return back()->with('success', $user->name . ' is active again.');
    }

    public function destroy($id)
    {
        $this->authorizeOwner();

        $user = User::staffOf(auth()->user()->companyId())->findOrFail($id);

        $user->tokens()->delete();
        $user->delete();

        return back()->with('success', 'User removed.');
    }

    // ── Company roles ────────────────────────────────

    public function createRole()
    {
        $this->authorizeOwner();

        $role   = new Role(['permissions' => []]);
        $groups = Permissions::groups();

        return view('content.dispatcher.users.role-form', compact('role', 'groups'));
    }

    public function storeRole(Request $request)
    {
        $this->authorizeOwner();

        $companyId = auth()->user()->companyId();

        $data = $this->validateRole($request, $companyId);

        Role::create([
            'dispatcher_id' => $companyId,
            'name'          => $data['name'],
            'description'   => $data['description'] ?? null,
            'permissions'   => Permissions::clean($request->input('permissions', [])),
            'is_system'     => false,
        ]);

        return redirect()
            ->route('dispatcher.users.index')
            ->with('success', 'Role created.');
    }

    public function editRole($id)
    {
        $this->authorizeOwner();

        // Only the company's own roles are editable here; platform templates
        // belong to the admin.
        $role   = Role::where('dispatcher_id', auth()->user()->companyId())->findOrFail($id);
        $groups = Permissions::groups();

        return view('content.dispatcher.users.role-form', compact('role', 'groups'));
    }

    public function updateRole(Request $request, $id)
    {
        $this->authorizeOwner();

        $companyId = auth()->user()->companyId();
        $role      = Role::where('dispatcher_id', $companyId)->findOrFail($id);

        $data = $this->validateRole($request, $companyId, $role->id);

        $role->update([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'permissions' => Permissions::clean($request->input('permissions', [])),
        ]);

        return redirect()
            ->route('dispatcher.users.index')
            ->with('success', 'Role updated.');
    }

    public function destroyRole($id)
    {
        $this->authorizeOwner();

        $role = Role::where('dispatcher_id', auth()->user()->companyId())
            ->withCount('users')
            ->findOrFail($id);

        if ($role->users_count > 0) {
            return back()->with('error', 'That role is still assigned to ' . $role->users_count . ' user(s).');
        }

        $role->delete();

        return back()->with('success', 'Role deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateRole(Request $request, int $companyId, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('roles', 'name')->where('dispatcher_id', $companyId)->ignore($ignoreId),
            ],
            'description'   => ['nullable', 'string', 'max:255'],
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ]);
    }

    /**
     * Managing users is the company owner's job. Staff can see the screen but
     * not change who else has access, whatever role they hold.
     */
    private function authorizeOwner(): void
    {
        abort_unless(
            auth()->user()->isCompanyOwner() || auth()->user()->isAdmin(),
            403,
            'Only the company account can manage panel users.'
        );
    }

    private function assertRoleBelongs(int $roleId, int $companyId): void
    {
        $ok = Role::availableTo($companyId)->whereKey($roleId)->exists();

        abort_unless($ok, 422, 'That role is not available to your company.');
    }
}
