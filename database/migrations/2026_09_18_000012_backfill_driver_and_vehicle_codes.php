<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Drivers and vehicles created before these columns existed have no
     * reference code. The sign-off screen and the inspection header both print
     * one, so they are backfilled here rather than left to a seeder someone has
     * to remember to run on each environment.
     */
    public function up(): void
    {
        $counter = 0;

        DB::table('users')
            ->where('role', 'driver')
            ->whereNull('driver_code')
            ->orderBy('id')
            ->each(function ($user) use (&$counter) {
                $counter++;

                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'driver_code' => 'DRV-' . str_pad((string) $counter, 4, '0', STR_PAD_LEFT),
                    ]);
            });

        $vehicleCounter = 0;

        DB::table('vehicles')
            ->whereNull('vehicle_code')
            ->orderBy('id')
            ->each(function ($vehicle) use (&$vehicleCounter) {
                $vehicleCounter++;

                DB::table('vehicles')
                    ->where('id', $vehicle->id)
                    ->update([
                        'vehicle_code' => 'V-' . (200 + $vehicleCounter),
                    ]);
            });
    }

    public function down(): void
    {
        // Codes are generated values, so reversing simply clears them.
        DB::table('users')->where('role', 'driver')->update(['driver_code' => null]);
        DB::table('vehicles')->update(['vehicle_code' => null]);
    }
};
