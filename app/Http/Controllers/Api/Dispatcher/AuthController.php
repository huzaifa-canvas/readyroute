<?php

namespace App\Http\Controllers\Api\Dispatcher;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

/**
 * Signing in to the dispatcher app.
 *
 * Only a dispatcher account can register here. The platform admin is created
 * by seeding, never by a form — a public endpoint that could mint an admin
 * would hand the whole platform to anyone who found it.
 */
class AuthController extends Controller
{
    use ApiResponse;

    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone_number' => ['nullable', 'string', 'max:50'],
        ]);

        $user = User::create([
            'name'         => $request->input('name'),
            'email'        => $request->input('email'),
            'password'     => Hash::make($request->input('password')),
            'phone_number' => $request->input('phone_number'),
            // Fixed, never taken from the request.
            'role'         => 'dispatcher',
            'status'       => 'active',
        ]);

        return $this->created($this->session($user, $request), 'Account created.');
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $request->input('email'))->first();

        // One message for both halves, so the endpoint cannot be used to find
        // out which addresses have accounts.
        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            return $this->fail('Those details do not match an account.', 422, [
                'email' => ['Those details do not match an account.'],
            ]);
        }

        if (! $user->isDispatcher() && ! $user->isAdmin()) {
            return $this->forbidden('This is not a dispatcher account.');
        }

        if ($user->isSuspended()) {
            return $this->forbidden('This account has been suspended. Contact your company administrator.');
        }

        return $this->ok($this->session($user, $request), 'Signed in.');
    }

    /**
     * Who is signed in, and what their account is allowed to do.
     */
    public function me(Request $request): JsonResponse
    {
        return $this->ok($this->profile($request->user()));
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        // Only a real personal access token can be revoked; a session-backed
        // request carries a TransientToken, which has nothing to delete.
        $token = $user->currentAccessToken();

        if ($token instanceof \Laravel\Sanctum\PersonalAccessToken) {
            $token->delete();
        }

        // Stops push reaching a device that has been signed out of.
        $user->forceFill(['device_token' => null])->save();

        return $this->ok(null, 'Signed out.');
    }

    /**
     * Where push notifications should be delivered.
     *
     * Notifications go out through Firebase, not the socket: the socket is for
     * chat and live events while the app is open, and cannot wake a phone.
     */
    public function storeDeviceToken(Request $request): JsonResponse
    {
        $request->validate([
            'device_token' => ['required', 'string', 'max:512'],
        ]);

        $request->user()->forceFill(['device_token' => $request->input('device_token')])->save();

        return $this->ok(null, 'Device registered for notifications.');
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        Password::sendResetLink($request->only('email'));

        // Always the same answer, whether or not the address is known.
        return $this->ok(null, 'If that address has an account, a reset link is on its way.');
    }

    /**
     * A signed-in session: the token plus everything the app needs to draw
     * its first screen without a second call.
     *
     * @return array<string, mixed>
     */
    private function session(User $user, Request $request): array
    {
        $name = $request->input('device_name', 'dispatcher-app');

        return [
            'token' => $user->createToken($name)->plainTextToken,
            'user'  => $this->profile($user),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function profile(User $user): array
    {
        $company = User::find($user->companyId());

        return [
            'id'         => $user->id,
            'name'       => $user->name,
            'email'      => $user->email,
            'phone'      => $user->phone_number,
            'avatar_url' => $user->avatar_url,
            'role'       => $user->role,

            'is_owner' => $user->isCompanyOwner(),
            'is_admin' => $user->isAdmin(),

            'company' => $company ? [
                'id'   => $company->id,
                'name' => $company->getMeta('company_name') ?: $company->name,
            ] : null,

            /*
             * Without a live subscription the panel is read-only: they can
             * sign in and look at everything, but nothing can be created or
             * changed. The app needs to know that up front so it can grey the
             * buttons rather than letting every write fail at the server.
             */
            'subscription' => [
                'status'     => $company?->subscription_status,
                'label'      => $user->subscriptionStatusLabel(),
                'is_active'  => $user->hasActiveSubscription(),
                'plan'       => $company?->subscriptionPlan?->name,
                'renews_on'  => optional($company?->renews_at)->toDateString(),
            ],
            'can_write' => $user->hasActiveSubscription(),
        ];
    }
}
