<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Puts each trip in the vehicle its driver actually has.
 *
 * A trip used to carry its own vehicle, chosen separately from the driver, so
 * the two could disagree. They now come from the same place, but trips booked
 * before that still hold whatever was picked at the time.
 *
 * It is not only untidy. A driver can only inspect the vehicle assigned to
 * them, so a trip pointing at a different one described a pre-trip inspection
 * that could never be completed — the driver was told to go and do one they
 * had already done.
 */
return new class extends Migration
{
    public function up(): void
    {
        $misaligned = DB::table('trips')
            ->join('users', 'users.id', '=', 'trips.driver_id')
            ->leftJoin('vehicles', 'vehicles.assigned_driver_id', '=', 'users.id')
            ->whereNotNull('trips.driver_id')
            ->whereColumn('trips.vehicle_id', '!=', DB::raw('COALESCE(vehicles.id, -1)'))
            ->select('trips.id as trip_id', 'vehicles.id as driver_vehicle_id')
            ->get();

        foreach ($misaligned as $row) {
            DB::table('trips')
                ->where('id', $row->trip_id)
                ->update(['vehicle_id' => $row->driver_vehicle_id]);
        }
    }

    public function down(): void
    {
        // Deliberately irreversible: what each trip used to point at is not
        // recorded anywhere, and the old values were the problem.
    }
};
