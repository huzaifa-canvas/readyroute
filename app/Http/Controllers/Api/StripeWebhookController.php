<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook;

/**
 * Stripe's own report of what happened.
 *
 * This is the authority on a company's subscription state, not the browser: a
 * customer can close the tab mid-payment, and a card can fail a month later
 * with nobody at the keyboard. Every event is verified against the signing
 * secret before it is believed.
 */
class StripeWebhookController extends Controller
{
    public function __construct(private readonly StripeService $stripe)
    {
    }

    public function handle(Request $request): JsonResponse
    {
        $secret = config('services.stripe.webhook_secret');

        if (blank($secret)) {
            // Refusing is safer than trusting an unsigned POST that can change
            // who is allowed to use the panel.
            Log::warning('Stripe webhook received but STRIPE_WEBHOOK_SECRET is not set.');

            return response()->json(['received' => false], 400);
        }

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                $request->header('Stripe-Signature', ''),
                $secret
            );
        } catch (\Throwable $e) {
            Log::warning('Stripe webhook signature rejected', ['error' => $e->getMessage()]);

            return response()->json(['received' => false], 400);
        }

        $object = $event->data->object ?? null;

        $company = $this->companyFor($object);

        if (! $company) {
            // Not ours, or a company that has since been removed.
            return response()->json(['received' => true]);
        }

        // Invoice events carry the money; keep our own copy before anything
        // else, because the admin figures read from that and not from Stripe.
        if (in_array($event->type, ['invoice.paid', 'invoice.payment_failed', 'invoice.finalized'], true)) {
            $this->stripe->recordInvoice($company, $object);
        }

        match ($event->type) {
            'invoice.paid',
            'invoice.payment_failed',
            'customer.subscription.created',
            'customer.subscription.updated',
            'customer.subscription.deleted' => $this->stripe->syncSubscriptionState($company),
            default                          => null,
        };

        Log::info('Stripe webhook handled', [
            'type'       => $event->type,
            'company_id' => $company->id,
            'status'     => $company->fresh()->subscription_status,
        ]);

        return response()->json(['received' => true]);
    }

    /**
     * Find the tenant an event belongs to, by customer first and by the
     * metadata we set when the subscription was created as a fallback.
     */
    private function companyFor(?object $object): ?User
    {
        if (! $object) {
            return null;
        }

        if (! empty($object->customer)) {
            $company = User::where('stripe_customer_id', $object->customer)->first();

            if ($company) {
                return $company;
            }
        }

        $companyId = $object->metadata->company_id ?? null;

        return $companyId ? User::find((int) $companyId) : null;
    }
}
