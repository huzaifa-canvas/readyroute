<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deleting a trip removes it from every list in the panel, but the row itself
 * is kept.
 *
 * A real DELETE would take more than the trip with it: trip_signatures and
 * trip_status_logs cascade, which destroys the proof-of-delivery signature and
 * the stamped record of the run, and invoice_items.trip_id is set to null,
 * which silently detaches a billed trip from the invoice line charging for it.
 * None of that is recoverable, so the delete is a soft one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
