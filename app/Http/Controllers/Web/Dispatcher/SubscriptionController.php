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

        /*
         * Read the real state from Stripe before drawing the page.
         *
         * Everything else relies on the webhook to keep this row honest, and a
         * webhook cannot reach a server that Stripe cannot see — on staging it
         * never arrives at all. Without this the page can sit on "Active" long
         * after Stripe has ended the subscription, and offer a Cancel button
         * for something already cancelled. One read on the one page that shows
         * this is cheap, and it is the page where being wrong is most visible.
         */
        $this->stripe->syncSubscriptionState($company);

        $company->refresh()->load('subscriptionPlan');

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
            // 'renewal' keeps them on what they paid for until it runs out;
            // 'now' moves them this minute and charges the difference.
            'when'                 => ['nullable', 'in:now,renewal'],
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

        /*
         * A company already paying can wait for the period it bought to end.
         *
         * Only offered to someone with a running subscription: there is
         * nothing to wait for otherwise, and no period end to move to.
         */
        if ($request->input('when') === 'renewal' && $company->subscription_status === 'active') {
            try {
                $startsAt = $this->stripe->schedulePlanChange($company, $plan);
            } catch (\RuntimeException $e) {
                return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
            } catch (\Throwable $e) {
                report($e);

                return response()->json([
                    'status'  => false,
                    'message' => 'That change could not be scheduled. Please try again.',
                ], 422);
            }

            return response()->json([
                'status' => true,
                'data'   => [
                    'client_secret'    => null,
                    'requires_payment' => false,
                    'scheduled'        => true,
                    'plan'             => $plan->name,
                    'starts_on'        => $startsAt->format('d M Y'),
                    'message'          => 'You stay on ' . ($company->subscriptionPlan?->name ?? 'your current plan')
                        . ' until ' . $startsAt->format('d M Y') . '. ' . $plan->name
                        . ' starts that day, and the new price applies from then.',
                ],
            ]);
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
                // Stripe charges the difference against the card it already
                // holds, so there is no card step to show.
                'requires_payment' => $intent['requires_payment'],
                'plan'             => $plan->name,
                'amount'           => (float) $plan->price_amount,
                'message'          => $intent['requires_payment']
                    ? null
                    : 'You are on ' . $plan->name . ' from now. The difference for the rest of '
                        . 'this period has been charged to your card, and ' . $plan->priceLabel()
                        . ' applies from your next renewal.',
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

        try {
            $endsAt = $this->stripe->cancelAtPeriodEnd($company);
        } catch (\RuntimeException $e) {
            // Saying "cancelled" when nothing was cancelled is worse than
            // saying nothing: the customer stops watching for the charge.
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $endsAt
            ? 'Your subscription will not renew. You keep full access until ' . $endsAt->format('d M Y') . '.'
            : 'Your subscription has been cancelled.');
    }

    /**
     * Drop a plan change that has not happened yet.
     *
     * Nothing was charged for it, so backing out costs nothing and leaves the
     * current subscription exactly as it was.
     */
    public function cancelPlanChange()
    {
        $this->authorizeOwner();

        $company = User::findOrFail(auth()->user()->companyId());
        $pending = $company->pendingPlan?->name;

        if (! $company->hasPendingPlanChange()) {
            return back()->with('error', 'There is no upcoming plan change to cancel.');
        }

        $this->stripe->cancelScheduledPlanChange($company);

        return back()->with('success', $pending
            ? 'The move to ' . $pending . ' has been called off. You stay on '
                . ($company->subscriptionPlan?->name ?? 'your current plan') . '.'
            : 'The upcoming plan change has been called off.');
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
