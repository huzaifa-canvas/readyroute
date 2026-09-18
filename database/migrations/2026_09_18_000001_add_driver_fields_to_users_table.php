<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Human-facing driver reference shown on the trip sign-off screen,
            // e.g. #DRV-0047.
            if (! Schema::hasColumn('users', 'driver_code')) {
                $table->string('driver_code', 20)->nullable()->unique()->after('role');
            }

            // Firebase token, collected now so push can be switched on later
            // without another release of the mobile app.
            if (! Schema::hasColumn('users', 'device_token')) {
                $table->string('device_token', 255)->nullable()->after('phone_number');
            }

            // Presence. The dispatcher dashboard currently assumes every driver
            // is online; these columns let it report the truth.
            if (! Schema::hasColumn('users', 'is_online')) {
                $table->boolean('is_online')->default(false)->after('device_token');
            }
            if (! Schema::hasColumn('users', 'last_seen_at')) {
                $table->timestamp('last_seen_at')->nullable()->after('is_online');
            }

            // Denormalised latest position, so the live map can render every
            // driver without scanning the location history table.
            if (! Schema::hasColumn('users', 'last_lat')) {
                $table->decimal('last_lat', 10, 8)->nullable()->after('last_seen_at');
            }
            if (! Schema::hasColumn('users', 'last_lng')) {
                $table->decimal('last_lng', 11, 8)->nullable()->after('last_lat');
            }
            if (! Schema::hasColumn('users', 'last_location_at')) {
                $table->timestamp('last_location_at')->nullable()->after('last_lng');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'driver_code',
                'device_token',
                'is_online',
                'last_seen_at',
                'last_lat',
                'last_lng',
                'last_location_at',
            ]);
        });
    }
};
