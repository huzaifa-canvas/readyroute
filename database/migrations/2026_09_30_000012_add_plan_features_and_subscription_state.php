<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turn plans into something the admin actually controls, and give a company's
 * subscription a state the panel can act on.
 *
 * Until now a plan was a price and a sentence. It now carries the feature set
 * it unlocks, so the admin decides which tier gets System Users, billing or
 * reporting rather than that being hard-coded. And a company carries whether
 * it is paid up, because an unpaid panel is read-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            // Feature keys from App\Support\PlanFeatures. Null means "the
            // defaults", which keeps existing plans working until the admin
            // edits them.
            if (! Schema::hasColumn('subscription_plans', 'features')) {
                $table->json('features')->nullable()->after('trip_limit');
            }

            // Set once the plan exists in Stripe; without it the plan cannot
            // be bought, only assigned by the admin.
            if (! Schema::hasColumn('subscription_plans', 'stripe_price_id')) {
                $table->string('stripe_price_id', 120)->nullable()->after('features');
            }

            if (! Schema::hasColumn('subscription_plans', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('is_featured');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            // none | active | past_due | cancelled — only "active" unlocks
            // writing. A company in any other state can sign in and read.
            if (! Schema::hasColumn('users', 'subscription_status')) {
                $table->string('subscription_status', 20)
                    ->default('none')
                    ->after('subscription_plan_id');
            }

            if (! Schema::hasColumn('users', 'stripe_customer_id')) {
                $table->string('stripe_customer_id', 120)->nullable()->after('renews_at');
            }

            if (! Schema::hasColumn('users', 'stripe_subscription_id')) {
                $table->string('stripe_subscription_id', 120)->nullable()->after('stripe_customer_id');
            }

            // Lets a company try the panel before paying; null means no trial.
            if (! Schema::hasColumn('users', 'trial_ends_at')) {
                $table->timestamp('trial_ends_at')->nullable()->after('stripe_subscription_id');
            }
        });

        // Companies that already had a plan assigned keep working — locking
        // out a live tenant on deploy would be the wrong kind of surprise.
        DB::table('users')
            ->where('role', 'dispatcher')
            ->whereNull('dispatcher_id')
            ->whereNotNull('subscription_plan_id')
            ->update(['subscription_status' => 'active']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $drop = array_values(array_filter(
                ['subscription_status', 'stripe_customer_id', 'stripe_subscription_id', 'trial_ends_at'],
                fn ($column) => Schema::hasColumn('users', $column)
            ));

            if ($drop) {
                $table->dropColumn($drop);
            }
        });

        Schema::table('subscription_plans', function (Blueprint $table) {
            $drop = array_values(array_filter(
                ['features', 'stripe_price_id', 'is_active'],
                fn ($column) => Schema::hasColumn('subscription_plans', $column)
            ));

            if ($drop) {
                $table->dropColumn($drop);
            }
        });
    }
};
