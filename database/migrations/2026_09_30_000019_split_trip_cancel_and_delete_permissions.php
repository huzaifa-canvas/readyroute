<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Cancelling and deleting a trip are now two different things, so they need two
 * different permissions.
 *
 * trips.delete used to mean "cancel or delete". Every role that holds it keeps
 * exactly what it had, which means also granting it the new trips.cancel key —
 * without this, the people who could cancel a trip yesterday would find the
 * button gone. The standard dispatcher additionally gains trips.cancel on its
 * own, because cancelling a run is day-to-day dispatch work in a way that
 * deleting the record is not.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('roles')->get() as $role) {
            $permissions = json_decode($role->permissions ?? '[]', true);

            if (! is_array($permissions)) {
                continue;
            }

            $wantsCancel = in_array('trips.delete', $permissions, true)
                || $role->slug === 'standard-dispatcher';

            if (! $wantsCancel || in_array('trips.cancel', $permissions, true)) {
                continue;
            }

            $permissions[] = 'trips.cancel';

            DB::table('roles')
                ->where('id', $role->id)
                ->update(['permissions' => json_encode(array_values($permissions))]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('roles')->get() as $role) {
            $permissions = json_decode($role->permissions ?? '[]', true);

            if (! is_array($permissions) || ! in_array('trips.cancel', $permissions, true)) {
                continue;
            }

            DB::table('roles')
                ->where('id', $role->id)
                ->update([
                    'permissions' => json_encode(array_values(
                        array_diff($permissions, ['trips.cancel'])
                    )),
                ]);
        }
    }
};
