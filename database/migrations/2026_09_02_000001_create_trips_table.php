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
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispatcher_id')->constrained('users')->onDelete('cascade');
            
            // Passenger Information
            $table->foreignId('client_id')->nullable()->constrained('clients')->onDelete('set null');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone_number')->nullable();
            $table->string('member_id')->nullable();
            
            // Special Requirements
            $table->boolean('req_wheelchair')->default(false);
            $table->boolean('req_stretcher')->default(false);
            $table->boolean('req_o2_tank')->default(false);
            $table->boolean('req_bariatric')->default(false);
            $table->boolean('req_no_steps')->default(false);
            
            // Trip Details
            $table->date('pickup_date');
            $table->time('pickup_time');
            $table->string('pickup_address');
            $table->string('dropoff_address');
            $table->enum('trip_type', ['one_way', 'round_trip', 'recurring'])->default('one_way');
            $table->text('notes')->nullable();
            
            // Assignment Information
            $table->foreignId('driver_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->onDelete('set null');
            $table->string('billing_type')->nullable(); // medicaid, medicare, private, insurance
            
            // Status
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled'])->default('scheduled');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
