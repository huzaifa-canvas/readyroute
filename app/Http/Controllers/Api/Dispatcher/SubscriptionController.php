<?php

namespace App\Http\Controllers\Api\Dispatcher;

use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The company's own subscription.
 *
 * Payment is confirmed by Stripe and the webhook, never by the app saying it
 * went through. What the app gets here is a client secret for Stripe's own
 * mobile SDK to confirm against — the card never reaches our servers.
 */
class SubscriptionController extends BaseDispatcherController
{
    public function __construct(private readonly StripeService $stripe)
    {
    }

    public function index(): JsonResponse
    {
        $company = User::findOrFail($this->companyId());

        /*
         * Read the real state from Stripe before answering.
         *
         * Everything else relies on the webhook to keep this row honest, and a
         * webhook cannot reach a server Stripe cannot see. One read on the one
         * screen that shows this is cheap, and it is where being wrong is most
         * visible.
         */
        $this->stripe->syncSubscriptionState($company);

        $company->refresh()->load(['subscriptionPlan', 'pendingPlan']);

        $usage = $company->planUsage();

        return $this->ok([
            'status'       => $company->subscription_status,
            'status_label' => $company->subscriptionStatusLabel(),
            'is_active'    => $company->subscription_status === 'active',

            'current_plan' => $company->subscriptionPlan ? $this->plan($company->subscriptionPlan) : null,

            'dates' => [
                'started_on'   => optional($company->subscribed_at)->toDateString(),
                'renews_on'    => optional($company->renews_at)->toDateString(),
                'cancels_on'   => optional($company->cancels_at)->toDateString(),
                'is_cancelled' => $company->cancels_at?->isFuture() ?? false,
            ],

            // A plan change the company asked for but is not on yet. Nothing
            // is charged for it until the date arrives.
            'pending_change' => $company->pendingPlan ? [
                'plan'      => $this->plan($company->pendingPlan),
                'starts_on' => optional($company->pending_plan_starts_at)->toDateString(),
            ] : null,

            'usage' => $usage,
            'exceeded' => collect($usage)
                ->filter(fn ($row) => $row['limit'] !== null && $row['used'] > $row['limit'])
                ->keys()
                ->values(),

            'plans' => SubscriptionPlan::orderBy('price_amount')->get()->map(fn ($plan) => $this->plan($plan)),

            // Card payment is only offered when Stripe is configured; the
            // tiers are still listed so the company can see what exists.
            'can_pay'         => $this->stripe->isConfigured() && filled($this->stripe->publishableKey()),
            'publishable_key' => $this->stripe->publishableKey(),
            'is_owner'        => $this->user()->isCompanyOwner() || $this->user()->isAdmin(),
        ]);
    }

    /**
     * Start paying for a plan, or move to another one.
     *
     * `when` is the whole difference between the two ways of changing tier:
     * 'renewal' keeps them on what they paid for until it runs out, 'now'
     * moves them this minute and charges the difference.
     */
    public function checkout(Request $request): JsonResponse
    {
        $this->requireOwner();

        $request->validate([
            'subscription_plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
            'when'                 => ['nullable', 'in:now,renewal'],
        ]);

        $company = User::findOrFail($this->companyId());
        $plan    = SubscriptionPlan::findOrFail($request->integer('subscription_plan_id'));

        if (! $this->stripe->isConfigured()) {
            return $this->fail('Card payments are not configured yet. Ask your administrator to assign this plan.', 422);
        }

        if ($plan->price_amount === null) {
            return $this->fail($plan->name . ' is priced on request. Please contact us to arrange it.', 422);
        }

        // Only offered to someone already paying: there is nothing to wait for
        // otherwise, and no period end to move to.
        if ($request->input('when') === 'renewal' && $company->subscription_status === 'active') {
            try {
                $startsAt = $this->stripe->schedulePlanChange($company, $plan);
            } catch (\RuntimeException $e) {
                return $this->fail($e->getMessage(), 422);
            } catch (\Throwable $e) {
                report($e);

                return $this->fail('That change could not be scheduled. Please try again.', 422);
            }

            return $this->ok([
                'client_secret'    => null,
                'requires_payment' => false,
                'scheduled'        => true,
                'plan'             => $plan->name,
                'starts_on'        => $startsAt->toDateString(),
                'message'          => 'You stay on ' . ($company->subscriptionPlan?->name ?? 'your current plan')
                    . ' until ' . $startsAt->format('d M Y') . '. ' . $plan->name
                    . ' starts that day, and the new price applies from then.',
            ]);
        }

        try {
            $intent = $this->stripe->startSubscription($company, $plan);
        } catch (\Throwable $e) {
            report($e);

            return $this->fail('Stripe could not start that subscription. Please try again.', 422);
        }

        return $this->ok([
            'client_secret'    => $intent['client_secret'],
            'requires_payment' => $intent['requires_payment'],
            'scheduled'        => false,
            'plan'             => $plan->name,
            'amount'           => (float) $plan->price_amount,
            'message'          => $intent['requires_payment']
                ? null
                : 'You are on ' . $plan->name . ' from now. The difference for the rest of '
                    . 'this period has been charged to your card.',
        ]);
    }

    /**
     * Called once the app reports the payment went through.
     *
     * The state is read back from Stripe rather than taken on trust, and the
     * invoice is pulled across at the same time. Both are idempotent, so the
     * webhook arriving later changes nothing.
     */
    public function confirm(): JsonResponse
    {
        $this->requireOwner();

        $company = User::findOrFail($this->companyId());
        $status  = $this->stripe->syncSubscriptionState($company);

        $this->stripe->backfillInvoices($company);

        return $this->ok([
            'subscription_status' => $status,
            'is_active'           => $status === 'active',
        ]);
    }

    /**
     * Stop renewing. Access runs to the end of the period already paid for.
     */
    public function cancel(): JsonResponse
    {
        $this->requireOwner();

        $company = User::findOrFail($this->companyId());

        try {
            $endsAt = $this->stripe->cancelAtPeriodEnd($company);
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->ok(
            ['access_until' => optional($endsAt)->toDateString()],
            $endsAt
                ? 'Your subscription will not renew. You keep full access until ' . $endsAt->format('d M Y') . '.'
                : 'Your subscription has been cancelled.'
        );
    }

    public function resume(): JsonResponse
    {
        $this->requireOwner();

        $company = User::findOrFail($this->companyId());

        return $this->stripe->resumeSubscription($company)
            ? $this->ok(null, 'Your subscription will renew as normal.')
            : $this->fail('That subscription could not be resumed. Please choose a plan instead.', 422);
    }

    /**
     * Drop a plan change that has not happened yet. Nothing was charged for
     * it, so backing out costs nothing.
     */
    public function cancelPlanChange(): JsonResponse
    {
        $this->requireOwner();

        $company = User::findOrFail($this->companyId());

        if (! $company->hasPendingPlanChange()) {
            return $this->fail('There is no upcoming plan change to cancel.', 422);
        }

        $pending = $company->pendingPlan?->name;

        $this->stripe->cancelScheduledPlanChange($company);

        return $this->ok(null, $pending
            ? 'The move to ' . $pending . ' has been called off.'
            : 'The upcoming plan change has been called off.');
    }

    /**
     * The company's own payment history with us.
     */
    public function invoices(): JsonResponse
    {
        $company = User::findOrFail($this->companyId());

        return $this->ok(
            \App\Models\SubscriptionInvoice::where('dispatcher_id', $company->id)
                ->latest('issued_at')
                ->limit(50)
                ->get()
                ->map(fn ($invoice) => [
                    'id'        => $invoice->id,
                    'number'    => $invoice->number,
                    'amount'    => (float) $invoice->amount,
                    'currency'  => strtoupper((string) $invoice->currency),
                    'status'    => $invoice->status,
                    'issued_at' => optional($invoice->issued_at)->toIso8601String(),
                    'pdf_url'    => $invoice->pdf_url,
                    'hosted_url' => $invoice->hosted_url,
                ])
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function plan(SubscriptionPlan $plan): array
    {
        return [
            'id'          => $plan->id,
            'name'        => $plan->name,
            'description' => $plan->description,
            'price'       => $plan->price_amount !== null ? (float) $plan->price_amount : null,
            'price_label' => $plan->priceLabel(),
            'is_featured' => (bool) $plan->is_featured,
            'limits' => [
                'vehicles' => $plan->limitLabel('vehicle'),
                'drivers'  => $plan->limitLabel('driver'),
                'trips'    => $plan->limitLabel('trip'),
            ],
            'features' => collect($plan->featureList())
                ->map(fn ($feature) => [
                    'key'   => $feature,
                    'label' => \App\Support\PlanFeatures::label($feature),
                ])
                ->values(),
        ];
    }
}
