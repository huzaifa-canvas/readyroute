<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Our own record of what each company paid for its subscription.
 *
 * The first version of the admin screens read this straight from Stripe's
 * invoice list, which was wrong: the Stripe account is shared with other
 * products, so the platform's revenue included money that was never ours.
 * Every row here is written from a webhook for a customer we recognise, so the
 * figures can only ever describe this platform.
 *
 * It also means the dashboard does not call Stripe to draw a chart, and the
 * history survives independently of what Stripe keeps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_invoices', function (Blueprint $table) {
            $table->id();

            // The company that was billed.
            $table->foreignId('dispatcher_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Which tier it was for; nulled rather than cascaded so deleting a
            // plan never rewrites financial history.
            $table->foreignId('subscription_plan_id')
                ->nullable()
                ->constrained('subscription_plans')
                ->nullOnDelete();

            $table->string('stripe_invoice_id', 120)->unique();
            $table->string('stripe_subscription_id', 120)->nullable();

            // What Stripe shows the customer, e.g. PAGTBAAJ-0001.
            $table->string('number', 60)->nullable();

            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency', 10)->default('usd');

            // paid | open | uncollectible | void | draft
            $table->string('status', 20)->default('open');

            $table->timestamp('issued_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            // Stripe's own hosted copy, for the customer to download.
            $table->string('hosted_url', 500)->nullable();
            $table->string('pdf_url', 500)->nullable();

            $table->timestamps();

            $table->index(['dispatcher_id', 'issued_at']);
            $table->index(['status', 'issued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_invoices');
    }
};
