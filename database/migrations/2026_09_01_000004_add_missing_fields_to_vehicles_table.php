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
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('year')->nullable()->after('make_model_year');
            $table->string('color')->nullable()->after('year');
            $table->integer('seating_capacity')->nullable()->after('vin_number');
            $table->boolean('wheelchair_ramp')->default(false)->after('seating_capacity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn(['year', 'color', 'seating_capacity', 'wheelchair_ramp']);
        });
    }
};
