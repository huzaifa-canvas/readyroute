<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cancelling a trip used to delete the row, so there was nothing left to show
 * the driver and nothing to report on. A cancelled trip is now kept, and this
 * column records when it happened — the same way every other point in the run
 * already stamps its own timestamp.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('completed_at');
        });

        // Any trip already sitting at cancelled predates the column. Its own
        // last update is the closest honest answer to when that happened.
        DB::table('trips')
            ->where('status', 'cancelled')
            ->whereNull('cancelled_at')
            ->update(['cancelled_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn('cancelled_at');
        });
    }
};
