<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the endpoints the socket server calls.
 *
 * These routes hand out user identity, so they are not public: the caller must
 * present the shared secret. The comparison is timing-safe, and a missing or
 * empty configured secret refuses everything rather than allowing everything.
 */
class VerifySocketSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('socket.secret');
        $provided = (string) $request->header('X-Socket-Secret', '');

        if ($expected === '' || $provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json([
                'status'  => false,
                'message' => 'Invalid socket credentials.',
                'data'    => null,
                'errors'  => null,
            ], 401);
        }

        return $next($request);
    }
}
