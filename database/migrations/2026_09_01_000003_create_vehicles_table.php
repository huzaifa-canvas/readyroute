<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispatcher_id')->constrained('users')->onDelete('cascade');
            $table->string('name');
            $table->string('make_model_year')->nullable();
            $table->string('number_plate')->nullable();
            $table->enum('status', ['available', 'in_use', 'maintenance'])->default('available');
            $table->string('vin_number')->nullable();
            $table->text('maintenance_log_notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
