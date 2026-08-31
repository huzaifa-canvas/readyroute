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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispatcher_id')->constrained('users')->onDelete('cascade');
            
            // Personal Info
            $table->string('full_name');
            $table->date('dob')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('email')->nullable();
            
            // Address
            $table->string('home_address')->nullable();
            $table->string('apt_unit')->nullable();
            $table->string('city')->nullable();
            $table->string('zip_code')->nullable();
            
            // Funding & Insurance
            $table->string('funding_type')->nullable(); // medicaid, medicare, private, insurance
            $table->string('insurance_id')->nullable();
            
            // Mobility Needs
            $table->boolean('wheelchair_required')->default(false);
            $table->boolean('ambulatory_assistance')->default(false);
            $table->boolean('stretcher_transport')->default(false);
            $table->boolean('bariatric_vehicle')->default(false);
            
            // Emergency Contact
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            
            // Special Notes
            $table->text('special_notes')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
