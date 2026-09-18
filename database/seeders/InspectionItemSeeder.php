<?php

namespace Database\Seeders;

use App\Models\InspectionItem;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Give every existing dispatcher company the default DVIR checklist. New
 * companies get theirs when they are created; this backfills the ones that
 * already exist. Safe to run repeatedly.
 */
class InspectionItemSeeder extends Seeder
{
    public function run(): void
    {
        User::where('role', 'dispatcher')->each(function (User $dispatcher) {
            InspectionItem::seedDefaultsFor($dispatcher);
        });
    }
}
