<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\StripeService;
use Illuminate\Console\Command;

/**
 * Catch up with plan changes whose date has passed.
 *
 * Stripe makes the switch itself, on the date, through the subscription
 * schedule — the money is never at risk here. What this fixes is the panel:
 * the tier a company is allowed to use comes from our own row, and that row
 * only moves when something reads Stripe back. Normally the webhook does it
 * within seconds, but a webhook cannot reach an install Stripe cannot see, and
 * on those the company would keep the old plan's limits until somebody
 * happened to open the subscription page.
 *
 * Only companies whose change is actually due are read, so this stays a
 * handful of API calls a day rather than a sweep of every customer.
 */
class ApplyDuePlanChanges extends Command
{
    protected $signature = 'subscriptions:apply-plan-changes';

    protected $description = 'Move companies onto the plan they scheduled, once the date has passed';

    public function handle(StripeService $stripe): int
    {
        if (! $stripe->isConfigured()) {
            $this->info('Stripe is not configured; nothing to do.');

            return self::SUCCESS;
        }

        $due = User::whereNotNull('pending_plan_id')
            ->whereNotNull('pending_plan_starts_at')
            // A little past the hour Stripe bills, so we are not asking before
            // it has had a chance to act.
            ->where('pending_plan_starts_at', '<=', now()->subMinutes(15))
            ->get();

        if ($due->isEmpty()) {
            $this->info('No plan changes are due.');

            return self::SUCCESS;
        }

        foreach ($due as $company) {
            $was = $company->subscriptionPlan?->name;

            $stripe->syncSubscriptionState($company);

            $company->refresh()->load('subscriptionPlan');

            $this->line(sprintf(
                '  %s: %s -> %s',
                $company->email,
                $was ?? '-',
                $company->subscriptionPlan?->name ?? '-'
            ));
        }

        $this->info($due->count() . ' company(ies) checked.');

        return self::SUCCESS;
    }
}
