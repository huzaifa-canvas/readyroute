<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stop a company adding more than its tier covers.
 *
 * Used as `limit:driver` on the routes that create one of something. It is a
 * different question from both of the other subscription gates: `subscribed`
 * asks whether they are paying at all, `plan` asks whether the tier includes
 * the feature, and this asks whether there is room left inside it.
 *
 * Only creation is checked. Editing or deleting what they already have stays
 * open, because a company that has gone over the line — after a downgrade, say
 * — still has to be able to tidy up, and blocking the edits would trap them
 * above the cap with no way down.
 */
class EnsurePlanLimit
{
    public function handle(Request $request, Closure $next, string $resource): Response
    {
        $user = $request->user();

        if (! $user || $user->isAdmin()) {
            return $next($request);
        }

        // Reading never consumes anything.
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        $company = \App\Models\User::find($user->companyId());

        if (! $company || $company->withinPlanLimit($resource)) {
            return $next($request);
        }

        $message = $company->planLimitMessage($resource);

        if ($request->expectsJson()) {
            return response()->json([
                'status'  => false,
                'message' => $message,
                'data'    => null,
                'errors'  => null,
            ], 402);
        }

        return back()->with('error', $message);
    }
}
