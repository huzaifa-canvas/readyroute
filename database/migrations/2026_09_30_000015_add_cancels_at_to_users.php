<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a cancelled subscription actually stops.
 *
 * Cancelling used to take effect at once, which cut a company off from a month
 * they had already paid for. Stripe's own behaviour is to run to the end of
 * the period, and this column records that date so the panel can keep working
 * until then and say plainly when it will stop.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'cancels_at')) {
                $table->timestamp('cancels_at')->nullable()->after('renews_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'cancels_at')) {
                $table->dropColumn('cancels_at');
            }
        });
    }
};
