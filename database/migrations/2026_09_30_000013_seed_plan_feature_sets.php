<?php

use App\Support\PlanFeatures;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Give each tier a starting feature set.
 *
 * The admin edits these from the plan form afterwards; this only exists so the
 * tiers differ from each other on day one instead of every plan silently
 * unlocking everything. Plans are matched on their price rather than their
 * slug, because the slugs have already been renamed once — the cheapest tier
 * is the entry one whatever it ends up being called.
 */
return new class extends Migration
{
    public function up(): void
    {
        $plans = DB::table('subscription_plans')
            ->orderByRaw('price_amount IS NULL')  // "Custom" sorts last
            ->orderBy('price_amount')
            ->get();

        if ($plans->isEmpty()) {
            return;
        }

        $entry = [
            // Core dispatching, and the operational screens a small operator
            // still needs day to day.
            'trips', 'drivers', 'clients', 'fleet',
            'live_map', 'incidents', 'messaging', 'tracking_link',
        ];

        $middle = array_merge($entry, [
            'auto_dispatch', 'compliance', 'reports', 'system_users',
        ]);

        $top = PlanFeatures::all();

        $tiers = [$entry, $middle, $top];

        foreach ($plans->values() as $index => $plan) {
            // Anything beyond the third tier gets everything; an admin who
            // added a fourth plan did not intend it to be the most limited.
            $features = $tiers[$index] ?? $top;

            DB::table('subscription_plans')
                ->where('id', $plan->id)
                ->update(['features' => json_encode(PlanFeatures::clean($features))]);
        }
    }

    public function down(): void
    {
        DB::table('subscription_plans')->update(['features' => null]);
    }
};
