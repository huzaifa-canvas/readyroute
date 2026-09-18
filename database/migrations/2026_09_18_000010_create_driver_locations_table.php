<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * GPS breadcrumbs. This is the highest-volume table in the system, so it
     * carries only a created_at rather than the usual pair of timestamps, and
     * the current position is also mirrored onto the users row so the live map
     * never has to scan this history.
     */
    public function up(): void
    {
        Schema::create('driver_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Null while the driver is between trips.
            $table->foreignId('trip_id')->nullable()->constrained('trips')->nullOnDelete();

            $table->decimal('lat', 10, 8);
            $table->decimal('lng', 11, 8);

            $table->decimal('speed_mph', 6, 2)->nullable();
            $table->decimal('heading', 5, 2)->nullable();
            $table->decimal('accuracy_m', 7, 2)->nullable();

            // When the device recorded the fix, which is not necessarily when
            // the server received it.
            $table->timestamp('recorded_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'recorded_at']);
            $table->index(['trip_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_locations');
    }
};
