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
        Schema::table('trips', function (Blueprint $table) {
            $table->decimal('pickup_lat', 10, 8)->nullable()->after('pickup_address');
            $table->decimal('pickup_lng', 11, 8)->nullable()->after('pickup_lat');
            $table->decimal('dropoff_lat', 10, 8)->nullable()->after('dropoff_address');
            $table->decimal('dropoff_lng', 11, 8)->nullable()->after('dropoff_lat');
            $table->decimal('distance', 8, 2)->nullable()->after('dropoff_lng'); // in miles
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn(['pickup_lat', 'pickup_lng', 'dropoff_lat', 'dropoff_lng', 'distance']);
        });
    }
};
