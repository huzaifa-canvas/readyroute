<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A company without a live subscription can look, but not touch.
 *
 * Signing in still works and every screen still reads, because locking a
 * tenant out of their own records over an unpaid bill is worse than the unpaid
 * bill. What stops is writing: anything that is not a GET is refused and sent
 * to the subscription page.
 *
 * The subscription and profile routes are exempt, or there would be no way
 * back — a company could never pay because paying is itself a write.
 */
class EnsureActiveSubscription
{
    /**
     * Route names that must keep working while a company is locked, so they
     * can pay, see why, or sign out.
     *
     * @var array<int, string>
     */
    private const ALWAYS_ALLOWED = [
        'dispatcher.subscription',
        'dispatcher.subscription.change',
        'dispatcher.subscription.checkout',
        'dispatcher.subscription.confirm',
        'dispatcher.profile.index',
        'dispatcher.profile.update',
        'dispatcher.profile.password',
        'dispatcher.profile.2fa',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->hasActiveSubscription()) {
            return $next($request);
        }

        // Reading is always fine; it is changing things that needs a plan.
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        if (in_array($request->route()?->getName(), self::ALWAYS_ALLOWED, true)) {
            return $next($request);
        }

        $message = 'Your company does not have an active subscription, so the panel is read-only. '
            . 'Choose a plan to start working again.';

        if ($request->expectsJson()) {
            return response()->json([
                'status'  => false,
                'message' => $message,
                'data'    => null,
                'errors'  => null,
            ], 402);
        }

        return redirect()
            ->route('dispatcher.subscription')
            ->with('error', $message);
    }
}
