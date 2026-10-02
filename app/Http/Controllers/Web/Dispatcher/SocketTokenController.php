<?php

namespace App\Http\Controllers\Web\Dispatcher;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Hands the panel a token it can open a socket with.
 *
 * The socket server only understands Sanctum tokens — it is the same door the
 * driver app comes through — but the panel runs on a session. Rather than
 * teach the socket server a second way to identify someone, the panel asks
 * here for a token of its own.
 */
class SocketTokenController extends Controller
{
    private const TOKEN_NAME = 'panel-socket';

    /**
     * How long a panel token lives. Long enough to cover a working day
     * without reissuing on every page load, short enough that one copied out
     * of a browser is not useful for long.
     */
    private const LIFETIME_HOURS = 12;

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Nothing to connect to yet. Said plainly so the panel can stay on
        // polling instead of retrying a connection that cannot succeed.
        if (blank(config('socket.public_url'))) {
            return response()->json(['enabled' => false]);
        }

        /*
         * The plaintext half of a token cannot be read back once issued, so it
         * is kept in the session and reused. Minting one per page load would
         * leave a trail of live tokens behind a single afternoon's work.
         */
        $token = $request->session()->get('panel_socket_token');
        $tokenId = $request->session()->get('panel_socket_token_id');

        $stillValid = $token
            && $tokenId
            && $user->tokens()
                ->whereKey($tokenId)
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->exists();

        if (! $stillValid) {
            // Clear out this user's spent panel tokens before adding another.
            $user->tokens()
                ->where('name', self::TOKEN_NAME)
                ->where('expires_at', '<=', now())
                ->delete();

            $new = $user->createToken(
                self::TOKEN_NAME,
                ['socket'],
                now()->addHours(self::LIFETIME_HOURS)
            );

            $token = $new->plainTextToken;
            $tokenId = $new->accessToken->getKey();

            $request->session()->put('panel_socket_token', $token);
            $request->session()->put('panel_socket_token_id', $tokenId);
        }

        return response()->json([
            'enabled' => true,
            'url'     => config('socket.public_url'),
            'token'   => $token,
        ]);
    }
}
