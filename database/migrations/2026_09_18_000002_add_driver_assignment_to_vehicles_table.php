<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            // The pre-trip inspection screen opens on a specific vehicle, so a
            // driver must have one assigned before any inspection can exist.
            if (! Schema::hasColumn('vehicles', 'assigned_driver_id')) {
                $table->foreignId('assigned_driver_id')
                    ->nullable()
                    ->after('dispatcher_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            // Fleet reference shown to the driver, e.g. V-204.
            if (! Schema::hasColumn('vehicles', 'vehicle_code')) {
                $table->string('vehicle_code', 20)->nullable()->after('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropForeign(['assigned_driver_id']);
            $table->dropColumn(['assigned_driver_id', 'vehicle_code']);
        });
    }
};
