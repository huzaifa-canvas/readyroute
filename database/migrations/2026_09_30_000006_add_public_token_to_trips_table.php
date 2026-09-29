<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A per-trip token so a passenger can follow their ride without an account.
 *
 * The tracking page is sent as an SMS link, so the token is the only thing
 * standing between a stranger and a trip's live position. It is therefore
 * random, unique and per-trip — never the trip id — and the page it unlocks
 * shows only what a waiting passenger needs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            if (! Schema::hasColumn('trips', 'public_token')) {
                $table->string('public_token', 64)->nullable()->unique()->after('id');
            }

            // Lets the dispatcher see whether the passenger was actually sent
            // the link, without a separate messages log.
            if (! Schema::hasColumn('trips', 'tracking_sent_at')) {
                $table->timestamp('tracking_sent_at')->nullable()->after('public_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $drop = array_values(array_filter(
                ['public_token', 'tracking_sent_at'],
                fn ($column) => Schema::hasColumn('trips', $column)
            ));

            if ($drop) {
                $table->dropColumn($drop);
            }
        });
    }
};
