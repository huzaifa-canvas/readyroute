<?php

namespace App\Http\Controllers\Web\Dispatcher;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The company's own subscription.
 *
 * Payment happens inside the panel with Stripe's Payment Element — the
 * customer is never sent to a hosted page. What this controller returns is a
 * client secret; whether the money actually arrived is decided by Stripe and
 * confirmed through the webhook, never by the browser saying so.
 */
class SubscriptionController extends Controller
{
    public function __construct(private readonly StripeService $stripe)
    {
    }

    public function index()
    {
        $company = User::findOrFail(auth()->user()->companyId());
        $company->load('subscriptionPlan');

        $plans = SubscriptionPlan::orderBy('price_amount')->get();
        $usage = $company->planUsage();

        // Anything already over its cap is called out, because the usage bar
        // alone tops out at 100% and hides how far over it went.
        $exceeded = collect($usage)
            ->filter(fn ($row) => $row['limit'] !== null && $row['used'] > $row['limit'])
            ->keys()
            ->all();

        // Card payment is only offered when Stripe is configured; otherwise
        // the tiers are still listed so the customer can see what exists.
        $canPay    = $this->stripe->isConfigured() && filled($this->stripe->publishableKey());
        $stripeKey = $this->stripe->publishableKey();

        return view('content.dispatcher.subscription.index', compact(
            'company', 'plans', 'usage', 'exceeded', 'canPay', 'stripeKey'
        ));
    }

    /**
     * Begin paying for a plan.
     *
     * Returns the client secret the Payment Element confirms against; the
     * customer never leaves the panel.
     */
    public function checkout(Request $request): JsonResponse
    {
        $this->authorizeOwner();

        $request->validate([
            'subscription_plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
        ]);

        $company = User::findOrFail(auth()->user()->companyId());
        $plan    = SubscriptionPlan::findOrFail($request->integer('subscription_plan_id'));

        if (! $this->stripe->isConfigured()) {
            return response()->json([
                'status'  => false,
                'message' => 'Card payments are not configured yet. Ask your administrator to assign this plan.',
            ], 422);
        }

        if ($plan->price_amount === null) {
            return response()->json([
                'status'  => false,
                'message' => $plan->name . ' is priced on request. Please contact us to arrange it.',
            ], 422);
        }

        try {
            $intent = $this->stripe->startSubscription($company, $plan);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'status'  => false,
                'message' => 'Stripe could not start that subscription. Please try again.',
            ], 422);
        }

        return response()->json([
            'status' => true,
            'data'   => [
                'client_secret' => $intent['client_secret'],
                // A company already paying is moved to the new price in place;
                // Stripe prorates it and there is no card step to show.
                'requires_payment' => $intent['requires_payment'],
                'plan'             => $plan->name,
                'amount'           => (float) $plan->price_amount,
            ],
        ]);
    }

    /**
     * Called once the browser reports the payment went through.
     *
     * The state is read back from Stripe rather than taken on trust. The
     * invoice is pulled across at the same time: the webhook is the authority
     * in production, but it cannot reach a local or firewalled install, and a
     * payment that never lands in our own records is a payment the platform
     * cannot show or count.
     *
     * Both calls are idempotent, so the webhook arriving later changes
     * nothing.
     */
    public function confirm(): JsonResponse
    {
        $this->authorizeOwner();

        $company = User::findOrFail(auth()->user()->companyId());
        $status  = $this->stripe->syncSubscriptionState($company);

        // Scoped to this company's Stripe customer, so nothing belonging to
        // another product on the same account can come with it.
        $this->stripe->backfillInvoices($company);

        return response()->json([
            'status' => true,
            'data'   => [
                'subscription_status' => $status,
                'is_active'           => $status === 'active',
            ],
        ]);
    }

    /**
     * Stop renewing.
     *
     * Access runs to the end of the period the company has already paid for —
     * cutting them off the moment they click would be taking a month's money
     * for nothing. Stripe closes the subscription on that date and the webhook
     * locks the panel then.
     */
    public function cancel()
    {
        $this->authorizeOwner();

        $company = User::findOrFail(auth()->user()->companyId());

        $endsAt = $this->stripe->cancelAtPeriodEnd($company);

        return back()->with('success', $endsAt
            ? 'Your subscription will not renew. You keep full access until ' . $endsAt->format('d M Y') . '.'
            : 'Your subscription has been cancelled.');
    }

    /**
     * Change your mind while the period is still running.
     */
    public function resume()
    {
        $this->authorizeOwner();

        $company = User::findOrFail(auth()->user()->companyId());

        return $this->stripe->resumeSubscription($company)
            ? back()->with('success', 'Your subscription will renew as normal.')
            : back()->with('error', 'That subscription could not be resumed. Please choose a plan instead.');
    }

    private function authorizeOwner(): void
    {
        abort_unless(
            auth()->user()->isCompanyOwner() || auth()->user()->isAdmin(),
            403,
            'Only the company account can change the subscription.'
        );
    }
}
