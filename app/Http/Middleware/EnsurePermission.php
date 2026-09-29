<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate a route on a dispatcher-side permission.
 *
 * Used as `permission:trips.view` or with several alternatives,
 * `permission:trips.edit,trips.assign`, which passes if the user holds any of
 * them. Admins and company owners are allowed through unconditionally by
 * User::hasPermission(), so this only ever constrains added panel staff.
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        if ($permissions === [] || $user->hasAnyPermission($permissions)) {
            return $next($request);
        }

        // A suspended account is a different problem and deserves its own
        // message rather than a bare permission refusal.
        if ($user->isSuspended()) {
            abort(403, 'Your account has been suspended. Contact your company administrator.');
        }

        abort(403, 'Your role does not allow that.');
    }
}
