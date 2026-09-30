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
            return $company->stripe_customer_id;
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
                $updated = $this->client()->subscriptions->update($existing->id, [
                    'items' => [[
                        'id'    => $existing->items->data[0]->id,
                        'price' => $priceId,
                    ]],
                    'proration_behavior'   => 'create_prorations',
                    'cancel_at_period_end' => false,
                    'metadata'             => [
                        'company_id' => (string) $company->id,
                        'plan_id'    => (string) $plan->id,
                    ],
                ]);

                $company->forceFill([
                    'subscription_plan_id' => $plan->id,
                    'cancels_at'           => null,
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

        if ($status === 'active' && blank($company->subscribed_at)) {
            $changes['subscribed_at'] = now();
        }

        // Mirror a pending cancellation, including one made from the Stripe
        // dashboard rather than from our panel.
        $changes['cancels_at'] = ($subscription->cancel_at_period_end ?? false) && ($subscription->cancel_at ?? null)
            ? now()->setTimestamp($subscription->cancel_at)
            : null;

        $company->forceFill($changes)->save();

        return $status;
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

        try {
            $subscription = $this->client()->subscriptions->update(
                $company->stripe_subscription_id,
                ['cancel_at_period_end' => true]
            );
        } catch (\Throwable $e) {
            report($e);

            return null;
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
