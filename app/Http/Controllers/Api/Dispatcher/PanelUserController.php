<?php

namespace App\Http\Controllers\Api\Dispatcher;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * The people in the office, and the roles that decide what they can reach.
 *
 * Only the company account itself may add or change staff — a panel user must
 * not be able to promote themselves, which is why every write here goes
 * through requireOwner() first.
 */
class PanelUserController extends BaseDispatcherController
{
    public function index(): JsonResponse
    {
        $companyId = $this->companyId();

        $owner = User::find($companyId);

        return $this->ok([
            // The company account is listed but never editable: it is the
            // account everything else hangs off.
            'owner' => $owner ? [
                'id'    => $owner->id,
                'name'  => $owner->name,
                'email' => $owner->email,
                'role'  => 'Company account',
                'avatar_url' => $owner->avatar_url,
            ] : null,

            'users' => User::staffOf($companyId)->with('accessRole')->orderBy('name')->get()
                ->map(fn (User $user) => $this->row($user)),

            'roles' => Role::availableTo($companyId)
                ->withCount('users')
                ->orderByDesc('is_system')
                ->orderBy('name')
                ->get()
                ->map(fn (Role $role) => [
                    'id'          => $role->id,
                    'name'        => $role->name,
                    'description' => $role->description,
                    'is_system'   => (bool) $role->is_system,
                    'user_count'  => $role->users_count,
                    'permissions' => $role->permissions ?? [],
                ]),

            'can_manage' => $this->user()->isCompanyOwner() || $this->user()->isAdmin(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->requireOwner();

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone_number' => ['nullable', 'string', 'max:50'],
            'role_id'  => ['required', 'integer', $this->roleRule()],
        ]);

        $user = User::create([
            'name'          => $data['name'],
            'email'         => $data['email'],
            'password'      => Hash::make($data['password']),
            'phone_number'  => $data['phone_number'] ?? null,
            'role'          => 'dispatcher',
            'dispatcher_id' => $this->companyId(),
            'role_id'       => $data['role_id'],
            'status'        => 'active',
        ]);

        return $this->created($this->row($user->load('accessRole')), 'Panel user added.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->requireOwner();

        $user = User::staffOf($this->companyId())->find($id);

        if (! $user) {
            return $this->notFound('User not found.');
        }

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone_number' => ['nullable', 'string', 'max:50'],
            'role_id'  => ['required', 'integer', $this->roleRule()],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $user->fill([
            'name'         => $data['name'],
            'email'        => $data['email'],
            'phone_number' => $data['phone_number'] ?? null,
            'role_id'      => $data['role_id'],
        ]);

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        return $this->ok($this->row($user->fresh()->load('accessRole')), 'Panel user updated.');
    }

    public function suspend(int $id): JsonResponse
    {
        $this->requireOwner();

        $user = User::staffOf($this->companyId())->find($id);

        if (! $user) {
            return $this->notFound('User not found.');
        }

        $user->forceFill(['status' => 'suspended', 'suspended_at' => now()])->save();

        // Their tokens go with them, or a suspended account keeps working on
        // whatever device is already signed in.
        $user->tokens()->delete();

        return $this->ok($this->row($user->fresh()->load('accessRole')), $user->name . ' has been suspended.');
    }

    public function activate(int $id): JsonResponse
    {
        $this->requireOwner();

        $user = User::staffOf($this->companyId())->find($id);

        if (! $user) {
            return $this->notFound('User not found.');
        }

        $user->forceFill(['status' => 'active', 'suspended_at' => null])->save();

        return $this->ok($this->row($user->fresh()->load('accessRole')), $user->name . ' can sign in again.');
    }

    public function destroy(int $id): JsonResponse
    {
        $this->requireOwner();

        $user = User::staffOf($this->companyId())->find($id);

        if (! $user) {
            return $this->notFound('User not found.');
        }

        $user->tokens()->delete();
        $user->delete();

        return $this->ok(null, 'Panel user removed.');
    }

    // ── Roles ────────────────────────────────────────────────────────

    public function storeRole(Request $request): JsonResponse
    {
        $this->requireOwner();

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions'   => ['required', 'array'],
            'permissions.*' => ['string'],
        ]);

        $role = Role::create([
            'dispatcher_id' => $this->companyId(),
            'name'          => $data['name'],
            'description'   => $data['description'] ?? null,
            'permissions'   => array_values($data['permissions']),
            'is_system'     => false,
        ]);

        return $this->created([
            'id'          => $role->id,
            'name'        => $role->name,
            'description' => $role->description,
            'permissions' => $role->permissions,
        ], 'Role created.');
    }

    public function updateRole(Request $request, int $id): JsonResponse
    {
        $this->requireOwner();

        $role = Role::availableTo($this->companyId())->find($id);

        if (! $role) {
            return $this->notFound('Role not found.');
        }

        // A built-in role is shared by every company on the platform, so it
        // cannot be edited from inside one of them.
        if ($role->is_system) {
            return $this->fail('Built-in roles cannot be changed.', 422);
        }

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions'   => ['required', 'array'],
            'permissions.*' => ['string'],
        ]);

        $role->update([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'permissions' => array_values($data['permissions']),
        ]);

        return $this->ok([
            'id'          => $role->id,
            'name'        => $role->name,
            'description' => $role->description,
            'permissions' => $role->permissions,
        ], 'Role updated.');
    }

    public function destroyRole(int $id): JsonResponse
    {
        $this->requireOwner();

        $role = Role::availableTo($this->companyId())->withCount('users')->find($id);

        if (! $role) {
            return $this->notFound('Role not found.');
        }

        if ($role->is_system) {
            return $this->fail('Built-in roles cannot be removed.', 422);
        }

        if ($role->users_count > 0) {
            return $this->fail(
                'That role is still held by ' . $role->users_count . ' user(s). Move them to another role first.',
                422
            );
        }

        $role->delete();

        return $this->ok(null, 'Role removed.');
    }

    // ── Shared pieces ────────────────────────────────────────────────

    /**
     * A role from another company must not be assignable here.
     */
    private function roleRule(): \Illuminate\Validation\Rules\Exists
    {
        $companyId = $this->companyId();

        // Matches Role::availableTo(): a platform role has no owner, a
        // company role has this one.
        return Rule::exists('roles', 'id')->where(function ($query) use ($companyId) {
            $query->whereNull('dispatcher_id')->orWhere('dispatcher_id', $companyId);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function row(User $user): array
    {
        return [
            'id'     => $user->id,
            'name'   => $user->name,
            'email'  => $user->email,
            'phone'  => $user->phone_number,
            'avatar_url' => $user->avatar_url,
            'status' => $user->status,
            'is_suspended' => $user->status === 'suspended',
            'role' => $user->accessRole ? [
                'id'   => $user->accessRole->id,
                'name' => $user->accessRole->name,
            ] : null,
            'created_at' => optional($user->created_at)->toIso8601String(),
        ];
    }
}
