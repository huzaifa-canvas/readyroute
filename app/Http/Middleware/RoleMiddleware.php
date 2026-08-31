<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (! $request->user()) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['status' => false, 'message' => 'Unauthenticated.'], 401);
            }
            return redirect()->route('login');
        }

        if (! in_array($request->user()->role, $roles)) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['status' => false, 'message' => 'Unauthorized access for your role.'], 403);
            }
            abort(403, 'Unauthorized access.');
        }

        return $next($request);
    }
}
