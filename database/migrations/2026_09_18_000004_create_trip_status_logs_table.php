<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per lifecycle transition. This is what the Status Log on the
     * trip completion screen reads, and it is the audit trail an NEMT contract
     * will ask for: who reported each event, when, and from where.
     */
    public function up(): void
    {
        Schema::create('trip_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('trips')->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('status', 30);

            // Where the driver actually was when they reported the event.
            $table->decimal('lat', 10, 8)->nullable();
            $table->decimal('lng', 11, 8)->nullable();

            $table->timestamp('logged_at');
            $table->timestamps();

            $table->index(['trip_id', 'logged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_status_logs');
    }
};
