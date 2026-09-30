<?php

namespace App\Console\Commands;

use App\Services\StripeService;
use Illuminate\Console\Command;

/**
 * Register this app's webhook endpoint with Stripe and print the signing
 * secret to put in .env.
 *
 * Doing it from the secret key rather than the dashboard means the event list
 * is the one the code actually handles, instead of whatever was ticked by hand
 * six months ago.
 */
class StripeWebhookSetup extends Command
{
    protected $signature = 'stripe:webhook
        {url? : Public HTTPS URL of the site, e.g. https://readyroute.com}
        {--list : Show the endpoints already registered and do nothing else}
        {--delete= : Remove an endpoint by its Stripe id}';

    protected $description = 'Create or update the Stripe webhook endpoint for this app';

    /**
     * The events this app acts on. Anything else would just be noise on the
     * endpoint — Stripe retries what it sends, so listening to events nothing
     * handles costs real traffic.
     *
     * @var array<int, string>
     */
    public const EVENTS = [
        // A subscription's first payment, and every renewal after it.
        'invoice.paid',
        // A card that stops working; the panel goes read-only.
        'invoice.payment_failed',
        // The subscription's own lifecycle, including cancellation from the
        // Stripe dashboard rather than from our panel.
        'customer.subscription.created',
        'customer.subscription.updated',
        'customer.subscription.deleted',
    ];

    public function handle(StripeService $stripe): int
    {
        if (! $stripe->isConfigured()) {
            $this->error('STRIPE_SECRET is not set. Add the keys to .env first.');

            return self::FAILURE;
        }

        $client = $stripe->client();

        if ($this->option('list')) {
            return $this->listEndpoints($client);
        }

        if ($id = $this->option('delete')) {
            $client->webhookEndpoints->delete($id, []);
            $this->info('Removed ' . $id);

            return self::SUCCESS;
        }

        $base = rtrim((string) ($this->argument('url') ?: config('app.url')), '/');
        $url  = $base . '/api/stripe/webhook';

        if (! str_starts_with($url, 'https://')) {
            $this->error('Stripe only accepts HTTPS endpoints, and it must be reachable from the internet.');
            $this->line('  You gave: ' . $url);
            $this->newLine();
            $this->line('For local development use the Stripe CLI instead:');
            $this->line('  <fg=cyan>stripe listen --forward-to ' . rtrim((string) config('app.url'), '/') . '/api/stripe/webhook</>');
            $this->line('It prints a whsec_... secret — put that in STRIPE_WEBHOOK_SECRET.');

            return self::FAILURE;
        }

        // Reuse the endpoint if this URL is already registered, so running the
        // command twice does not leave duplicates delivering the same events.
        $existing = collect($client->webhookEndpoints->all(['limit' => 100])->data)
            ->firstWhere('url', $url);

        if ($existing) {
            $client->webhookEndpoints->update($existing->id, [
                'enabled_events' => self::EVENTS,
                'disabled'       => false,
            ]);

            $this->info('Updated the existing endpoint ' . $existing->id);
            $this->warn('Stripe only reveals the signing secret once, when the endpoint is created.');
            $this->line('If you no longer have it, delete and recreate:');
            $this->line('  <fg=cyan>php artisan stripe:webhook --delete=' . $existing->id . '</>');
            $this->line('  <fg=cyan>php artisan stripe:webhook ' . $base . '</>');

            return self::SUCCESS;
        }

        $endpoint = $client->webhookEndpoints->create([
            'url'            => $url,
            'enabled_events' => self::EVENTS,
            'description'    => config('app.name') . ' — subscription events',
        ]);

        $this->newLine();
        $this->info('Webhook endpoint created.');
        $this->line('  URL     ' . $endpoint->url);
        $this->line('  Id      ' . $endpoint->id);
        $this->newLine();
        $this->line('Add this to your .env, then clear the config cache:');
        $this->line('  <fg=green>STRIPE_WEBHOOK_SECRET=' . $endpoint->secret . '</>');
        $this->newLine();
        $this->line('Events enabled:');

        foreach (self::EVENTS as $event) {
            $this->line('  - ' . $event);
        }

        return self::SUCCESS;
    }

    private function listEndpoints($client): int
    {
        $endpoints = $client->webhookEndpoints->all(['limit' => 100])->data;

        if ($endpoints === []) {
            $this->line('No webhook endpoints registered on this Stripe account.');

            return self::SUCCESS;
        }

        $this->table(
            ['Id', 'Status', 'Events', 'URL'],
            collect($endpoints)->map(fn ($e) => [
                $e->id,
                $e->status,
                count($e->enabled_events),
                \Illuminate\Support\Str::limit($e->url, 60),
            ])->all()
        );

        return self::SUCCESS;
    }
}
