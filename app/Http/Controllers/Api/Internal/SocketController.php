<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Endpoints the socket server calls. Not for clients.
 *
 * The socket server deliberately does not know how to read a Sanctum token: it
 * asks here instead, so there is one place that decides who a token belongs to
 * and what they may join.
 */
class SocketController extends Controller
{
    use ApiResponse;

    /**
     * Identify the holder of a token and tell the socket server which rooms
     * they are allowed in.
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
        ]);

        $accessToken = PersonalAccessToken::findToken($request->input('token'));

        if (! $accessToken) {
            return $this->fail('Invalid token.', 401);
        }

        // Respect token expiry the same way the HTTP guard does.
        $expiration = config('sanctum.expiration');

        if ($expiration && $accessToken->created_at->lte(now()->subMinutes($expiration))) {
            return $this->fail('Token has expired.', 401);
        }

        $user = $accessToken->tokenable;

        if (! $user instanceof User) {
            return $this->fail('Invalid token.', 401);
        }

        $companyId = $user->companyId();

        $rooms = ['user.' . $user->id];

        if ($companyId) {
            $rooms[] = 'dispatcher.' . $companyId;

            if ($user->isDriver()) {
                $rooms[] = 'thread.' . $companyId . '.' . $user->id;
            }
        }

        $accessToken->forceFill(['last_used_at' => now()])->save();

        return $this->ok([
            'user' => [
                'id'            => $user->id,
                'name'          => $user->name,
                'role'          => $user->role,
                'dispatcher_id' => $user->dispatcher_id,
                'company_id'    => $companyId,
            ],
            'rooms' => $rooms,
        ]);
    }

    /**
     * Presence, reported by the socket server on connect and disconnect. This
     * is what makes the dispatcher's "drivers online" count real rather than
     * assumed.
     */
    public function presence(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => ['required', 'integer'],
            'online'  => ['required', 'boolean'],
        ]);

        $user = User::find($request->integer('user_id'));

        if (! $user) {
            return $this->notFound('User not found.');
        }

        $user->forceFill([
            'is_online'    => $request->boolean('online'),
            'last_seen_at' => now(),
        ])->saveQuietly();

        return $this->ok(['user_id' => $user->id, 'online' => $user->is_online]);
    }
}
