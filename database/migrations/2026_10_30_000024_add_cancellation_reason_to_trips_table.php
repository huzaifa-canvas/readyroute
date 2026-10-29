<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A cancelled trip is kept on the record, so the reason it was cancelled is
 * kept with it. The dispatcher now picks one when they cancel; "Other" carries
 * a short free-text note in the second column. Both are nullable because every
 * trip cancelled before this column existed has neither.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->string('cancellation_reason')->nullable()->after('cancelled_at');
            $table->text('cancellation_note')->nullable()->after('cancellation_reason');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn(['cancellation_reason', 'cancellation_note']);
        });
    }
};
