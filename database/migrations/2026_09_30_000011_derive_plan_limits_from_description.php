<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fill in any plan limits the slug-based backfill missed.
 *
 * Plans are editable from the admin panel, so their slugs and prices drift
 * from whatever was seeded — matching on slug silently skipped a renamed
 * plan and left its usage bars measuring against nothing. The numbers are
 * read from the text the admin actually wrote instead: "Up to 100 vehicles"
 * and "$900" are already on the record.
 *
 * "Unlimited" and anything unparseable stay null, which the screens render as
 * no cap rather than as zero.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('subscription_plans')->get() as $plan) {
            $update = [];

            if ($plan->vehicle_limit === null && ! $this->saysUnlimited($plan->description)) {
                if ($vehicles = $this->numberBefore($plan->description, 'vehicle')) {
                    $update['vehicle_limit'] = $vehicles;

                    // Nothing states driver or trip caps, so they are scaled
                    // from the vehicle count rather than invented outright.
                    $update['driver_limit'] = (int) ceil($vehicles * 1.5);
                    $update['trip_limit']   = $vehicles * 100;
                }
            }

            if ($plan->price_amount === null) {
                if ($price = $this->money($plan->price)) {
                    $update['price_amount'] = $price;
                }
            }

            if ($update !== []) {
                DB::table('subscription_plans')->where('id', $plan->id)->update($update);
            }
        }
    }

    public function down(): void
    {
        // Nothing to undo: the previous migration's down() clears these.
    }

    private function saysUnlimited(?string $text): bool
    {
        return $text !== null && str_contains(strtolower($text), 'unlimited');
    }

    /**
     * "Up to 100 vehicles." -> 100
     */
    private function numberBefore(?string $text, string $noun): ?int
    {
        if (! $text) {
            return null;
        }

        return preg_match('/(\d[\d,]*)\s*' . preg_quote($noun, '/') . '/i', $text, $m)
            ? (int) str_replace(',', '', $m[1])
            : null;
    }

    /**
     * "$900" -> 900.00 ; "Custom" -> null
     */
    private function money(?string $text): ?float
    {
        if (! $text) {
            return null;
        }

        return preg_match('/(\d[\d,]*(?:\.\d+)?)/', $text, $m)
            ? (float) str_replace(',', '', $m[1])
            : null;
    }
};
