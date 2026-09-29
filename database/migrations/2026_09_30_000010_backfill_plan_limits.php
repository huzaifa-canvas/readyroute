<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Give the three seeded plans their numbers.
 *
 * The plans were created with display strings only ("$599", "Up to 10
 * vehicles"), so the usage bars had nothing to measure against. The limits
 * here follow the wording already in each plan's description; Enterprise is
 * left unlimited.
 */
return new class extends Migration
{
    public function up(): void
    {
        $limits = [
            'basic'        => ['vehicle_limit' => 10,  'driver_limit' => 15,  'trip_limit' => 500,  'price_amount' => 299],
            'professional' => ['vehicle_limit' => 50,  'driver_limit' => 75,  'trip_limit' => 5000, 'price_amount' => 599],
            'enterprise'   => ['vehicle_limit' => null,'driver_limit' => null,'trip_limit' => null, 'price_amount' => null],
        ];

        foreach ($limits as $slug => $values) {
            DB::table('subscription_plans')->where('slug', $slug)->update($values);
        }

        // Existing companies start on the entry plan rather than on nothing,
        // so the subscription screen has something to show on first open.
        $basic = DB::table('subscription_plans')->where('slug', 'basic')->value('id');

        if ($basic) {
            DB::table('users')
                ->where('role', 'dispatcher')
                ->whereNull('dispatcher_id')
                ->whereNull('subscription_plan_id')
                ->update([
                    'subscription_plan_id' => $basic,
                    'subscribed_at'        => now(),
                    'renews_at'            => now()->addMonth()->startOfMonth()->toDateString(),
                ]);
        }
    }

    public function down(): void
    {
        DB::table('users')->update([
            'subscription_plan_id' => null,
            'subscribed_at'        => null,
            'renews_at'            => null,
        ]);
    }
};
