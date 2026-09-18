<?php

use App\Enums\InspectionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One pre-trip inspection per driver, per vehicle, per day. It stays a
     * draft while the driver works down the checklist and locks on submission.
     */
    public function up(): void
    {
        Schema::create('vehicle_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispatcher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();

            $table->date('inspection_date');
            $table->string('status', 20)->default(InspectionStatus::Draft->value);

            // Set when any required item is marked as a defect, so the
            // dispatcher can be alerted without reading every response row.
            $table->boolean('has_defects')->default(false);

            $table->string('signature_path')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['driver_id', 'vehicle_id', 'inspection_date'], 'inspection_daily_unique');
            $table->index(['dispatcher_id', 'inspection_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_inspections');
    }
};
