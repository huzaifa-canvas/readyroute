<?php

use App\Enums\InspectionItemStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The driver's answer to one checklist item on one inspection.
     */
    public function up(): void
    {
        Schema::create('inspection_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_inspection_id')->constrained('vehicle_inspections')->cascadeOnDelete();
            $table->foreignId('inspection_item_id')->constrained('inspection_items')->cascadeOnDelete();

            $table->string('status', 20)->default(InspectionItemStatus::Pending->value);

            // Required when an item fails, so a defect is never logged without
            // the driver saying what is wrong.
            $table->text('note')->nullable();

            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->unique(['vehicle_inspection_id', 'inspection_item_id'], 'inspection_response_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_responses');
    }
};
