<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What has been done to each vehicle, and what is booked in.
 *
 * Until now the fleet carried a single `maintenance_log_notes` text box and a
 * status of "maintenance". Between them they could say that something was
 * wrong, but never what was done, when, by whom or what it cost — so the
 * question an operator is actually asked ("when was this van last serviced?")
 * had no answer in the system.
 *
 * Each row is one job. `status` is what separates a booking from a record:
 * scheduled and in_progress are work still owed, completed is history. That is
 * also what lets the vehicle's own status be derived rather than typed — a
 * vehicle with a job under way is off the road whatever the form last said.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_maintenance_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('dispatcher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();

            // Who recorded it. Kept even if they later leave, so history does
            // not lose its author.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('type', 40)->default('service');
            $table->string('status', 20)->default('completed');

            $table->string('title', 160);
            $table->text('description')->nullable();

            $table->string('vendor', 160)->nullable();
            $table->decimal('cost', 10, 2)->nullable();
            // Mileage at the time, which is how service intervals are judged.
            $table->unsignedInteger('odometer')->nullable();

            $table->date('performed_on')->nullable();
            $table->date('due_on')->nullable();
            $table->timestamp('completed_at')->nullable();

            // Set when the job was raised from a driver's inspection defect,
            // so the two sides of the same fault stay connected.
            $table->foreignId('vehicle_inspection_id')->nullable()
                ->constrained('vehicle_inspections')->nullOnDelete();

            $table->timestamps();

            // The history screen reads one vehicle newest-first; the fleet
            // list asks every vehicle whether it has open work.
            $table->index(['vehicle_id', 'performed_on']);
            $table->index(['dispatcher_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_maintenance_logs');
    }
};
