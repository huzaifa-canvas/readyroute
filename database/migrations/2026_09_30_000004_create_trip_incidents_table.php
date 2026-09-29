<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Incidents a driver reports from the road, and the SOS panic alert.
 *
 * Both are the same record with a different type: an SOS is an incident the
 * driver had no time to describe, so it is written immediately from whatever
 * the phone knows (position, current trip) and the detail can follow. Keeping
 * them in one table means the dispatcher has a single queue to work through.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_incidents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('dispatcher_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('driver_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // An incident can happen between trips, so this is optional.
            $table->foreignId('trip_id')
                ->nullable()
                ->constrained('trips')
                ->nullOnDelete();

            $table->foreignId('vehicle_id')
                ->nullable()
                ->constrained('vehicles')
                ->nullOnDelete();

            $table->string('type', 30);      // sos, accident, vehicle, passenger, delay, other
            $table->string('severity', 20)->default('medium'); // low, medium, high, critical

            $table->string('title', 160)->nullable();
            $table->text('description')->nullable();

            // Where it happened, captured at the moment of reporting rather
            // than looked up later from the GPS trail.
            $table->decimal('lat', 10, 8)->nullable();
            $table->decimal('lng', 11, 8)->nullable();
            $table->string('address', 255)->nullable();

            // Photos the driver attached, stored on the private disk.
            $table->json('attachments')->nullable();

            $table->string('status', 20)->default('open'); // open, acknowledged, resolved

            $table->foreignId('acknowledged_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();

            $table->timestamps();

            $table->index(['dispatcher_id', 'status', 'created_at']);
            $table->index(['driver_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_incidents');
    }
};
