<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Requests\Driver\LoginRequest;
use App\Http\Resources\Driver\DriverResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class AuthController extends BaseDriverController
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        // One message for both a missing account and a wrong password, so the
        // endpoint cannot be used to discover which emails are registered.
        if (! $user || ! Hash::check($request->password, $user->password)) {
            return $this->fail('The email or password is incorrect.', 422, [
                'email' => ['The email or password is incorrect.'],
            ]);
        }

        if (! $user->isDriver()) {
            return $this->forbidden('This app is for drivers. Please use the web panel to sign in.');
        }

        if ($request->filled('device_token')) {
            $user->device_token = $request->device_token;
        }

        // The driver is considered present from the moment they sign in; the
        // socket connection takes over maintaining this in Phase 8.
        $user->is_online    = true;
        $user->last_seen_at = now();
        $user->save();

        $token = $user->createToken($request->input('device_name') ?: 'driver-app')->plainTextToken;

        $user->load(['assignedVehicle', 'dispatcher', 'metas']);

        return $this->ok([
            'token'  => $token,
            'driver' => new DriverResource($user),
        ], 'Signed in successfully.');
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

        // Clearing the push token stops notifications reaching a device the
        // driver has signed out of, which may be a shared vehicle tablet.
        $user->forceFill([
            'device_token' => null,
            'is_online'    => false,
            'last_seen_at' => now(),
        ])->save();

        return $this->ok(null, 'Signed out.');
    }

    /**
     * Password reset is handled by the standard broker, so the emailed link is
     * the same one the web panel uses.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        Password::sendResetLink($request->only('email'));

        // Always the same reply, whether or not the address exists.
        return $this->ok(null, 'If that email is registered, a reset link is on its way.');
    }
}
