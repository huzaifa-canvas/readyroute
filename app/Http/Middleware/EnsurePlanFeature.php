<?php

namespace App\Http\Middleware;

use App\Support\PlanFeatures;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate a route on the company's subscription tier.
 *
 * Used as `plan:system_users`. Separate from the permission middleware because
 * the two answer different questions — a Head Dispatcher has every permission
 * and still cannot open a screen their company has not paid for.
 *
 * The refusal says which plan is missing it rather than a bare 403, because
 * the fix is an upgrade and the person needs to know that.
 */
class EnsurePlanFeature
{
    public function handle(Request $request, Closure $next, string ...$features): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        foreach ($features as $feature) {
            if ($user->planAllows($feature)) {
                continue;
            }

            $label = PlanFeatures::label($feature);

            if ($request->expectsJson()) {
                return response()->json([
                    'status'  => false,
                    'message' => $label . ' is not included in your plan.',
                    'data'    => null,
                    'errors'  => null,
                ], 402);
            }

            return redirect()
                ->route('dispatcher.subscription')
                ->with('error', $label . ' is not included in your current plan. Upgrade to use it.');
        }

        return $next($request);
    }
}
