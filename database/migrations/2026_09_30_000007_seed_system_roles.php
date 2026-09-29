<?php

use App\Support\Permissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Install the three platform roles the design names.
 *
 * Done as a migration rather than a seeder so every environment picks them up
 * on deploy — the role editor is useless with an empty list, and a company
 * adding its first staff user needs something to assign them to on day one.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (Permissions::systemRoles() as $role) {
            $exists = DB::table('roles')
                ->whereNull('dispatcher_id')
                ->where('slug', $role['slug'])
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('roles')->insert([
                'dispatcher_id' => null,
                'name'          => $role['name'],
                'slug'          => $role['slug'],
                'description'   => $role['description'],
                'permissions'   => json_encode(Permissions::clean($role['permissions'])),
                'is_system'     => true,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('roles')
            ->whereNull('dispatcher_id')
            ->whereIn('slug', array_column(Permissions::systemRoles(), 'slug'))
            ->delete();
    }
};
