<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two things the automatic Stripe sync needs.
 *
 * The product is kept across price changes, so the Stripe dashboard shows one
 * product with a price history rather than a new product every time the amount
 * moves. And the billing interval becomes a real value instead of a free-text
 * "/mo" — Stripe only accepts day, week, month or year, so a typed string was
 * never going to survive the round trip.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('subscription_plans', 'stripe_product_id')) {
                $table->string('stripe_product_id', 120)->nullable()->after('features');
            }

            if (! Schema::hasColumn('subscription_plans', 'billing_interval')) {
                $table->string('billing_interval', 10)->default('month')->after('billing_period');
            }
        });

        // Read the interval out of whatever was typed before, so nothing has
        // to be re-entered.
        foreach (DB::table('subscription_plans')->get() as $plan) {
            $text = strtolower((string) $plan->billing_period);

            $interval = match (true) {
                str_contains($text, 'year'), str_contains($text, 'yr'), str_contains($text, 'annual') => 'year',
                str_contains($text, 'week'), str_contains($text, 'wk')  => 'week',
                str_contains($text, 'day')                              => 'day',
                default                                                 => 'month',
            };

            DB::table('subscription_plans')->where('id', $plan->id)->update([
                'billing_interval' => $interval,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $drop = array_values(array_filter(
                ['stripe_product_id', 'billing_interval'],
                fn ($column) => Schema::hasColumn('subscription_plans', $column)
            ));

            if ($drop) {
                $table->dropColumn($drop);
            }
        });
    }
};
