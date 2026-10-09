<?php

namespace App\Services;

use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

/**
 * Everything the panel needs from Stripe, in one place.
 *
 * Payment happens inside the app with the Payment Element, so nothing here
 * ever hands the customer a Stripe-hosted page. The flow is the standard
 * incomplete-subscription one: create the subscription unpaid, hand the
 * browser the payment intent's client secret, and let the webhook confirm what
 * actually happened rather than trusting the browser's word for it.
 */
class StripeService
{
    public function __construct(private readonly ?StripeClient $client = null)
    {
    }

    /**
     * Configured means "has a secret key". Without one the panel still works,
     * plans just cannot be bought.
     */
    public function isConfigured(): bool
    {
        return filled(config('services.stripe.secret'));
    }

    public function client(): StripeClient
    {
        return $this->client ?? new StripeClient(config('services.stripe.secret'));
    }

    public function publishableKey(): ?string
    {
        return config('services.stripe.key');
    }

    public function currency(): string
    {
        return strtolower((string) config('services.stripe.currency', 'usd'));
    }

    /**
     * The company's Stripe customer, created on first use and remembered.
     */
    public function customerFor(User $company): string
    {
        if (filled($company->stripe_customer_id)) {
            /*
             * The stored id is trusted only as far as Stripe still honours it.
             * A customer deleted in the Stripe dashboard keeps its id — reads
             * succeed and come back marked deleted — but nothing can be
             * created against it. Without this check the company could never
             * subscribe again: every attempt failed with "Stripe could not
             * start that subscription", and the id that caused it was never
             * replaced, so retrying could not help.
             */
            try {
                $existing = $this->client()->customers->retrieve($company->stripe_customer_id, []);

                if (empty($existing->deleted)) {
                    return $company->stripe_customer_id;
                }

                Log::info('Stripe customer was deleted; creating a replacement', [
                    'company_id'  => $company->id,
                    'customer_id' => $company->stripe_customer_id,
                ]);
            } catch (\Throwable $e) {
                // Gone entirely, or from another account after a key change.
                Log::warning('Stripe customer could not be read; creating a replacement', [
                    'company_id'  => $company->id,
                    'customer_id' => $company->stripe_customer_id,
                    'error'       => $e->getMessage(),
                ]);
            }

            /*
             * The old subscription belonged to the customer that has gone, so
             * it cannot be reused or resumed either. Clearing it keeps
             * startSubscription from trying to update something unreachable.
             */
            $company->forceFill([
                'stripe_customer_id'     => null,
                'stripe_subscription_id' => null,
            ])->save();
        }

        $customer = $this->client()->customers->create([
            'name'     => $company->name,
            'email'    => $company->email,
            'phone'    => $company->phone_number,
            // So a Stripe dashboard row can be traced back to a tenant.
            'metadata' => ['company_id' => (string) $company->id],
        ]);

        $company->forceFill(['stripe_customer_id' => $customer->id])->save();

        return $customer->id;
    }

    /**
     * Make sure the plan exists in Stripe with a price matching what the admin
     * typed, and return that price id.
     *
     * Stripe prices are immutable, so a changed amount means creating a new
     * price and archiving the old one — existing subscribers keep paying the
     * price they signed up on, which is the behaviour you want.
     *
     * Everything here runs off the secret key; the admin never copies an id by
     * hand.
     */
    public function syncPlan(SubscriptionPlan $plan): ?string
    {
        if (! $this->isConfigured() || $plan->price_amount === null) {
            // A "contact us" tier, or a platform with no keys yet.
            return null;
        }

        $cents    = (int) round((float) $plan->price_amount * 100);
        $currency = $this->currency();
        $interval = $plan->billing_interval ?: 'month';

        // Already priced correctly? Nothing to do.
        if (filled($plan->stripe_price_id)) {
            try {
                $existing = $this->client()->prices->retrieve($plan->stripe_price_id, []);

                $sameInterval = ($existing->recurring->interval ?? null) === $interval;

                if ($existing->active
                    && $existing->unit_amount === $cents
                    && $existing->currency === $currency
                    && $sameInterval) {
                    return $plan->stripe_price_id;
                }

                // Superseded: stop offering it, but leave it alive so the
                // subscriptions already on it keep working.
                $this->client()->prices->update($plan->stripe_price_id, ['active' => false]);
            } catch (\Throwable $e) {
                Log::info('Stored Stripe price could not be read; creating a fresh one', [
                    'plan_id' => $plan->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        $productId = $this->productFor($plan);

        $price = $this->client()->prices->create([
            'product'     => $productId,
            'unit_amount' => $cents,
            'currency'    => $currency,
            'recurring'   => ['interval' => $interval],
            'metadata'    => ['plan_id' => (string) $plan->id],
        ]);

        $plan->forceFill(['stripe_price_id' => $price->id])->save();

        return $price->id;
    }

    /**
     * The plan's Stripe product, reused across price changes so the dashboard
     * shows one product with its price history rather than a new product each
     * time the amount moves.
     */
    private function productFor(SubscriptionPlan $plan): string
    {
        if (filled($plan->stripe_product_id)) {
            try {
                $product = $this->client()->products->retrieve($plan->stripe_product_id, []);

                // Keep the Stripe dashboard in step with the panel.
                $this->client()->products->update($product->id, [
                    'name'        => $plan->name,
                    'description' => $plan->description,
                ]);

                return $product->id;
            } catch (\Throwable $e) {
                Log::info('Stored Stripe product could not be read; creating a fresh one', [
                    'plan_id' => $plan->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        $product = $this->client()->products->create([
            'name'        => $plan->name,
            'description' => $plan->description,
            'metadata'    => ['plan_id' => (string) $plan->id],
        ]);

        $plan->forceFill(['stripe_product_id' => $product->id])->save();

        return $product->id;
    }


    /**
     * Keep our own copy of a subscription invoice.
     *
     * Only ever called for a company we already know, which is what stops
     * another product's invoices on the same Stripe account from being counted
     * as this platform's revenue.
     */
    public function recordInvoice(User $company, object $invoice): SubscriptionInvoice
    {
        $planId = $company->subscription_plan_id;

        // Prefer the plan the invoice was actually for, where Stripe says.
        foreach (($invoice->lines->data ?? []) as $lineItem) {
            $fromMetadata = $lineItem->price->metadata->plan_id ?? null;

            if ($fromMetadata) {
                $planId = (int) $fromMetadata;
                break;
            }
        }

        return SubscriptionInvoice::updateOrCreate(
            ['stripe_invoice_id' => $invoice->id],
            [
                'dispatcher_id'          => $company->id,
                'subscription_plan_id'   => $planId,
                'stripe_subscription_id' => $invoice->subscription ?? $company->stripe_subscription_id,
                'number'                 => $invoice->number ?: $invoice->id,
                'amount'                 => ($invoice->amount_paid ?: $invoice->amount_due ?: 0) / 100,
                'currency'               => strtolower($invoice->currency ?? $this->currency()),
                'status'                 => $invoice->status ?? 'open',
                'issued_at'              => $invoice->created ? now()->setTimestamp($invoice->created) : now(),
                'paid_at'                => ($invoice->status ?? null) === 'paid' && $invoice->status_transitions?->paid_at
                    ? now()->setTimestamp($invoice->status_transitions->paid_at)
                    : (($invoice->status ?? null) === 'paid' ? now() : null),
                'hosted_url'             => $invoice->hosted_invoice_url ?? null,
                'pdf_url'                => $invoice->invoice_pdf ?? null,
            ]
        );
    }

    /**
     * Pull this company's invoices across from Stripe.
     *
     * Used to backfill a company that paid before we started recording, and
     * scoped to their customer id so nothing belonging to another product on
     * the same account can come with it.
     */
    public function backfillInvoices(User $company): int
    {
        if (! $this->isConfigured() || blank($company->stripe_customer_id)) {
            return 0;
        }

        try {
            $invoices = $this->client()->invoices->all([
                'customer' => $company->stripe_customer_id,
                'limit'    => 100,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }

        foreach ($invoices->data as $invoice) {
            $this->recordInvoice($company, $invoice);
        }

        return count($invoices->data);
    }

    /**
     * Start, resume or switch a subscription, and hand back what the browser
     * needs to pay.
     *
     * Three cases, and getting them confused is what made repeat clicks fail:
     *
     *  - Already paying: change the price on the existing subscription. Stripe
     *    prorates it; nothing new has to be paid on the spot.
     *  - A half-finished attempt for the same price: hand back the payment
     *    intent that is already open, rather than cancelling it and minting
     *    another. Clicking the button twice must not invalidate the card form
     *    already on screen.
     *  - Nothing yet: create an incomplete subscription to be paid for now.
     *
     * The old subscription is never cancelled up front. Cancelling it takes
     * its payment intent down with it, which is exactly how a customer ends
     * up staring at "a processing error occurred".
     *
     * @return array{subscription_id: string, client_secret: ?string, status: string, requires_payment: bool}
     */
    public function startSubscription(User $company, SubscriptionPlan $plan): array
    {
        $priceId = $this->syncPlan($plan);

        if (! $priceId) {
            throw new \RuntimeException('That plan has no price in Stripe yet.');
        }

        $customerId = $this->customerFor($company);
        $existing   = $this->currentSubscription($company);

        if ($existing) {
            // Paying already — move them to the new price in place.
            if (in_array($existing->status, ['active', 'trialing'], true)) {
                // A schedule and an immediate switch would fight over the same
                // subscription; the one they just asked for wins.
                $this->releaseSchedule($company);

                $updated = $this->client()->subscriptions->update($existing->id, [
                    'items' => [[
                        'id'    => $existing->items->data[0]->id,
                        'price' => $priceId,
                    ]],
                    /*
                     * Invoiced now, not folded into next month's bill.
                     *
                     * They are being given the new tier this minute, so the
                     * difference is charged this minute — Stripe credits the
                     * unused part of the plan they are leaving, which is why
                     * moving up costs the gap rather than the full price. A
                     * charge that appears weeks later, against a change they
                     * made today, is the kind of surprise people dispute.
                     */
                    'proration_behavior'   => 'always_invoice',
                    'cancel_at_period_end' => false,
                    'metadata'             => [
                        'company_id' => (string) $company->id,
                        'plan_id'    => (string) $plan->id,
                    ],
                ]);

                $company->forceFill([
                    'subscription_plan_id'   => $plan->id,
                    'cancels_at'             => null,
                    'stripe_schedule_id'     => null,
                    'pending_plan_id'        => null,
                    'pending_plan_starts_at' => null,
                ])->save();

                $this->syncSubscriptionState($company);

                return [
                    'subscription_id' => $updated->id,
                    'client_secret'   => null,
                    'status'          => $updated->status,
                    'requires_payment' => false,
                ];
            }

            // An unfinished attempt at this same price: reuse it so the card
            // form already open stays valid.
            if ($existing->status === 'incomplete'
                && ($existing->items->data[0]->price->id ?? null) === $priceId) {
                $secret = $this->secretFrom($this->client()->subscriptions->retrieve($existing->id, [
                    'expand' => ['latest_invoice.payment_intent', 'latest_invoice.confirmation_secret'],
                ]));

                if ($secret) {
                    return [
                        'subscription_id'  => $existing->id,
                        'client_secret'    => $secret,
                        'status'           => $existing->status,
                        'requires_payment' => true,
                    ];
                }
            }

            // An unfinished attempt at a different price is dead weight; it was
            // never paid for, so nothing is lost by clearing it away.
            if (in_array($existing->status, ['incomplete', 'incomplete_expired'], true)) {
                $this->cancelImmediately($company);
            }
        }

        $subscription = $this->client()->subscriptions->create([
            'customer'         => $customerId,
            'items'            => [['price' => $priceId]],
            'payment_behavior' => 'default_incomplete',
            'payment_settings' => [
                'save_default_payment_method' => 'on_subscription',
                'payment_method_types'        => ['card'],
            ],
            'expand'   => ['latest_invoice.payment_intent', 'latest_invoice.confirmation_secret'],
            'metadata' => [
                'company_id' => (string) $company->id,
                'plan_id'    => (string) $plan->id,
            ],
        ]);

        $company->forceFill([
            'stripe_subscription_id' => $subscription->id,
            'subscription_plan_id'   => $plan->id,
            // Not active until the money lands.
            'subscription_status'    => $company->subscription_status === 'active'
                ? 'active'   // still covered by the one they are replacing
                : 'none',
            'cancels_at'             => null,
        ])->save();

        return [
            'subscription_id'  => $subscription->id,
            'client_secret'    => $this->secretFrom($subscription),
            'status'           => $subscription->status,
            'requires_payment' => true,
        ];
    }

    /**
     * Move the company to another tier when the period they have paid for ends.
     *
     * Nothing is charged today and nothing changes today: they keep the plan
     * they bought until its last day, and the new one begins when the next
     * period does. Stripe holds this as a two-phase subscription schedule, so
     * it still happens on the date even if our server is never asked again.
     *
     * Returns the date the new plan starts.
     */
    public function schedulePlanChange(User $company, SubscriptionPlan $plan): \Illuminate\Support\Carbon
    {
        $priceId = $this->syncPlan($plan);

        if (! $priceId) {
            throw new \RuntimeException('That plan has no price in Stripe yet.');
        }

        $subscription = $this->currentSubscription($company);

        if (! $subscription || ! in_array($subscription->status, ['active', 'trialing'], true)) {
            throw new \RuntimeException('There is no running subscription to change.');
        }

        $item         = $subscription->items->data[0];
        $currentPrice = $item->price->id;

        if ($currentPrice === $priceId) {
            throw new \RuntimeException('You are already on that plan.');
        }

        // A change asked for twice replaces the first one rather than stacking
        // schedules, so the customer can keep changing their mind.
        $this->releaseSchedule($company);

        $schedule = $this->client()->subscriptionSchedules->create([
            'from_subscription' => $subscription->id,
        ]);

        $phase = $schedule->phases[0];

        $schedule = $this->client()->subscriptionSchedules->update($schedule->id, [
            // Once the new phase has run, Stripe hands the subscription back
            // and it simply keeps renewing on the new price.
            'end_behavior' => 'release',
            'phases'       => [
                [
                    'items'              => [['price' => $currentPrice, 'quantity' => 1]],
                    'start_date'         => $phase->start_date,
                    'end_date'           => $phase->end_date,
                    'proration_behavior' => 'none',
                ],
                [
                    'items'              => [['price' => $priceId, 'quantity' => 1]],
                    'proration_behavior' => 'none',
                ],
            ],
            'metadata' => [
                'company_id'      => (string) $company->id,
                'pending_plan_id' => (string) $plan->id,
            ],
        ]);

        $startsAt = now()->setTimestamp($phase->end_date);

        $company->forceFill([
            'stripe_schedule_id'     => $schedule->id,
            'pending_plan_id'        => $plan->id,
            'pending_plan_starts_at' => $startsAt,
            // Asking for a different plan is not a cancellation; if they had
            // one pending, choosing to carry on supersedes it.
            'cancels_at'             => null,
        ])->save();

        return $startsAt;
    }

    /**
     * Drop a scheduled plan change and stay where they are.
     */
    public function cancelScheduledPlanChange(User $company): bool
    {
        $released = $this->releaseSchedule($company);

        $company->forceFill([
            'stripe_schedule_id'     => null,
            'pending_plan_id'        => null,
            'pending_plan_starts_at' => null,
        ])->save();

        return $released;
    }

    /**
     * Hand the subscription back from its schedule, leaving it untouched.
     *
     * Releasing is not cancelling: the subscription carries on exactly as it
     * is, only without the future phase. A schedule that has already finished
     * or was removed in the dashboard is not an error here — the goal is that
     * no schedule is attached afterwards, and that is already true.
     */
    private function releaseSchedule(User $company): bool
    {
        if (blank($company->stripe_schedule_id)) {
            return false;
        }

        try {
            $this->client()->subscriptionSchedules->release($company->stripe_schedule_id);

            return true;
        } catch (\Throwable $e) {
            Log::info('Subscription schedule could not be released', [
                'company_id' => $company->id,
                'schedule'   => $company->stripe_schedule_id,
                'error'      => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * The company's subscription as Stripe currently has it, or null.
     */
    private function currentSubscription(User $company): ?object
    {
        if (blank($company->stripe_subscription_id)) {
            return null;
        }

        try {
            return $this->client()->subscriptions->retrieve($company->stripe_subscription_id, []);
        } catch (\Throwable $e) {
            Log::info('Stored subscription could not be read', [
                'company_id' => $company->id,
                'error'      => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Read back the live state and mirror it onto the company.
     *
     * Called after the browser reports success and again from the webhook, so
     * a customer who closes the tab mid-payment is still reconciled.
     */
    public function syncSubscriptionState(User $company): string
    {
        // Callers often hand over a model they were holding before the
        // subscription was created, whose stripe_subscription_id is still
        // empty. Reading the row back costs one query and stops the sync
        // silently doing nothing.
        $company->refresh();

        if (blank($company->stripe_subscription_id)) {
            return $company->subscription_status;
        }

        try {
            $subscription = $this->client()->subscriptions->retrieve($company->stripe_subscription_id, []);
        } catch (\Throwable $e) {
            Log::warning('Could not read the Stripe subscription', [
                'company_id' => $company->id,
                'error'      => $e->getMessage(),
            ]);

            return $company->subscription_status;
        }

        $status = match ($subscription->status) {
            'active', 'trialing'              => 'active',
            'past_due', 'unpaid', 'incomplete' => 'past_due',
            'canceled', 'incomplete_expired'  => 'cancelled',
            default                           => 'none',
        };

        $changes = ['subscription_status' => $status];

        // current_period_end moved onto the item in recent API versions.
        $periodEnd = $subscription->current_period_end
            ?? ($subscription->items->data[0]->current_period_end ?? null);

        if ($periodEnd) {
            $changes['renews_at'] = now()->setTimestamp($periodEnd)->toDateString();
        }

        /*
         * When the subscription they are on today actually began.
         *
         * Taken from Stripe rather than stamped locally, and rewritten on
         * every sync rather than only when empty. Writing it once meant a
         * company that cancelled and signed up again kept the date of the
         * subscription it had left behind — the page said "Started 29 Sep" to
         * someone who had subscribed that morning.
         *
         * start_date is the beginning of this subscription, not of the current
         * billing period, so it keeps reading as the day they signed up rather
         * than resetting every month.
         */
        $startedAt = $subscription->start_date
            ?? ($subscription->items->data[0]->current_period_start ?? null);

        if ($startedAt) {
            $changes['subscribed_at'] = now()->setTimestamp($startedAt);
        } elseif ($status === 'active' && blank($company->subscribed_at)) {
            $changes['subscribed_at'] = now();
        }

        // Mirror a pending cancellation, including one made from the Stripe
        // dashboard rather than from our panel.
        $changes['cancels_at'] = ($subscription->cancel_at_period_end ?? false) && ($subscription->cancel_at ?? null)
            ? now()->setTimestamp($subscription->cancel_at)
            : null;

        $company->forceFill(array_merge($changes, $this->reconcilePendingPlan($company, $subscription)))->save();

        return $status;
    }

    /**
     * Work out where a scheduled plan change has got to.
     *
     * The switch itself is Stripe's to make, on the date, whether or not
     * anyone has opened this panel. All that is decided here is what our own
     * row should say about it:
     *
     *  - the live price is the one they were waiting for, so the change has
     *    happened and they are simply on the new plan now;
     *  - the schedule is gone but the price never moved, so it was released
     *    or removed and there is nothing pending any more;
     *  - otherwise the change is still ahead of them, and the date is taken
     *    from the schedule rather than from whatever we stored, so a shifted
     *    billing period does not leave the panel quoting a stale date.
     */
    private function reconcilePendingPlan(User $company, object $subscription): array
    {
        if (blank($company->pending_plan_id)) {
            return [];
        }

        $cleared = [
            'pending_plan_id'        => null,
            'pending_plan_starts_at' => null,
            'stripe_schedule_id'     => null,
        ];

        $pendingPriceId = SubscriptionPlan::find($company->pending_plan_id)?->stripe_price_id;
        $livePriceId    = $subscription->items->data[0]->price->id ?? null;

        if ($pendingPriceId && $livePriceId === $pendingPriceId) {
            return $cleared + ['subscription_plan_id' => $company->pending_plan_id];
        }

        if (blank($company->stripe_schedule_id)) {
            return $cleared;
        }

        try {
            $schedule = $this->client()->subscriptionSchedules->retrieve($company->stripe_schedule_id, []);
        } catch (\Throwable $e) {
            Log::info('Scheduled plan change could not be read', [
                'company_id' => $company->id,
                'error'      => $e->getMessage(),
            ]);

            return [];
        }

        if (! in_array($schedule->status, ['active', 'not_started'], true)) {
            return $cleared;
        }

        $startsAt = $schedule->phases[1]->start_date ?? null;

        return $startsAt ? ['pending_plan_starts_at' => now()->setTimestamp($startsAt)] : [];
    }

    /**
     * End the subscription immediately, losing the rest of the paid period.
     *
     * Only used when replacing one subscription with another — a company
     * switching tiers must not be left paying for both. A customer who simply
     * wants to stop gets cancelAtPeriodEnd() instead, because they have
     * already paid for the month they are in.
     */
    public function cancelImmediately(User $company): void
    {
        if (blank($company->stripe_subscription_id)) {
            return;
        }

        try {
            $this->client()->subscriptions->cancel($company->stripe_subscription_id, []);
        } catch (\Throwable $e) {
            // Already gone, or never completed. Not worth failing over.
            Log::info('Nothing to cancel in Stripe', [
                'company_id' => $company->id,
                'error'      => $e->getMessage(),
            ]);
        }

        $company->forceFill([
            'stripe_subscription_id' => null,
            'cancels_at'             => null,
        ])->save();
    }

    /**
     * Stop renewing, but let the company use what they have paid for.
     *
     * The subscription stays active until the period ends; Stripe then sends
     * customer.subscription.deleted and the webhook locks the panel. Until
     * that day the company keeps working and the revenue still counts.
     *
     * @return \Illuminate\Support\Carbon|null when access actually ends
     */
    public function cancelAtPeriodEnd(User $company): ?\Illuminate\Support\Carbon
    {
        if (blank($company->stripe_subscription_id)) {
            // Nothing in Stripe to wind down — an assigned plan, say. Stop now.
            $company->forceFill(['subscription_status' => 'cancelled'])->save();

            return null;
        }

        /*
         * A plan change still waiting for the renewal date has to go first.
         *
         * Stripe refuses to set cancel_at_period_end on a subscription a
         * schedule is driving, so leaving one attached made the Cancel button
         * fail outright. Stopping altogether also answers the question the
         * scheduled change was asking, so there is nothing to preserve.
         */
        if (filled($company->stripe_schedule_id)) {
            $this->cancelScheduledPlanChange($company);
        }

        try {
            $subscription = $this->client()->subscriptions->update(
                $company->stripe_subscription_id,
                ['cancel_at_period_end' => true]
            );
        } catch (\Throwable $e) {
            report($e);

            /*
             * Stripe refuses to schedule a cancellation on a subscription that
             * has already ended, which is the usual reason to land here: our
             * record still says active because the webhook that would have
             * told us never arrived. Read the truth back and store it, so the
             * customer sees the real state instead of being told a second
             * cancellation worked.
             */
            $status = $this->syncSubscriptionState($company);

            if ($status === 'cancelled') {
                return null;
            }

            throw new \RuntimeException(
                'Stripe would not cancel this subscription. Nothing has been changed — please try again, or contact support if it keeps happening.'
            );
        }

        $endsAt = $subscription->cancel_at
            ?? $subscription->current_period_end
            ?? ($subscription->items->data[0]->current_period_end ?? null);

        $date = $endsAt ? now()->setTimestamp($endsAt) : null;

        $company->forceFill(['cancels_at' => $date])->save();

        return $date;
    }

    /**
     * Undo a pending cancellation, while the period is still running.
     */
    public function resumeSubscription(User $company): bool
    {
        if (blank($company->stripe_subscription_id)) {
            return false;
        }

        try {
            $this->client()->subscriptions->update(
                $company->stripe_subscription_id,
                ['cancel_at_period_end' => false]
            );
        } catch (\Throwable $e) {
            report($e);

            return false;
        }

        $company->forceFill(['cancels_at' => null])->save();

        return true;
    }

    /**
     * The client secret the Payment Element confirms against. Stripe has moved
     * this between the payment intent and a confirmation secret across API
     * versions, so both are checked.
     */
    private function secretFrom(object $subscription): ?string
    {
        $invoice = $subscription->latest_invoice ?? null;

        if (! $invoice) {
            return null;
        }

        if (isset($invoice->confirmation_secret->client_secret)) {
            return $invoice->confirmation_secret->client_secret;
        }

        return $invoice->payment_intent->client_secret ?? null;
    }
}
