<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\StripeService;
use Illuminate\Console\Command;

/**
 * Pull subscription payments across from Stripe into our own records.
 *
 * Needed whenever the webhook could not deliver — a local install Stripe
 * cannot reach, a firewall, or an outage. Safe to run repeatedly: each invoice
 * is matched on its Stripe id and updated rather than duplicated.
 */
class StripeBackfillInvoices extends Command
{
    protected $signature = 'stripe:backfill-invoices
        {company? : A company id, or leave empty for every company}';

    protected $description = 'Import subscription payments from Stripe into the platform records';

    public function handle(StripeService $stripe): int
    {
        if (! $stripe->isConfigured()) {
            $this->error('STRIPE_SECRET is not set.');

            return self::FAILURE;
        }

        $companies = $this->argument('company')
            ? User::query()->companies()->whereKey($this->argument('company'))->get()
            : User::query()->companies()->whereNotNull('stripe_customer_id')->get();

        if ($companies->isEmpty()) {
            $this->line('No companies with a Stripe customer to import from.');

            return self::SUCCESS;
        }

        $total = 0;

        foreach ($companies as $company) {
            if (blank($company->stripe_customer_id)) {
                $this->line("  {$company->name}: never paid by card, nothing to import");
                continue;
            }

            $count = $stripe->backfillInvoices($company);
            $total += $count;

            $this->line("  {$company->name}: {$count} invoice(s)");
        }

        $this->newLine();
        $this->info("Imported {$total} invoice(s).");

        return self::SUCCESS;
    }
}
