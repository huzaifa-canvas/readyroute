<?php

use App\Enums\ConfirmationStatus;
use App\Enums\TripStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            // Patient confirmation, shown to the driver as a read-only badge.
            // Set by the dispatcher today; an IVR call will set it later, which
            // is why the source is recorded separately.
            if (! Schema::hasColumn('trips', 'confirmation_status')) {
                $table->enum('confirmation_status', ConfirmationStatus::values())
                    ->default(ConfirmationStatus::Unconfirmed->value)
                    ->after('status');
            }
            if (! Schema::hasColumn('trips', 'confirmed_at')) {
                $table->timestamp('confirmed_at')->nullable()->after('confirmation_status');
            }
            if (! Schema::hasColumn('trips', 'confirmed_via')) {
                $table->string('confirmed_via', 20)->nullable()->after('confirmed_at');
            }

            // Lifecycle stamps. arrived_pickup_at is the timestamp the on-time
            // rule is measured against.
            if (! Schema::hasColumn('trips', 'en_route_at')) {
                $table->timestamp('en_route_at')->nullable()->after('confirmed_via');
            }
            if (! Schema::hasColumn('trips', 'arrived_pickup_at')) {
                $table->timestamp('arrived_pickup_at')->nullable()->after('en_route_at');
            }
            if (! Schema::hasColumn('trips', 'started_at')) {
                $table->timestamp('started_at')->nullable()->after('arrived_pickup_at');
            }
            if (! Schema::hasColumn('trips', 'arrived_dropoff_at')) {
                $table->timestamp('arrived_dropoff_at')->nullable()->after('started_at');
            }
            if (! Schema::hasColumn('trips', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('arrived_dropoff_at');
            }

            // What the run actually cost, as opposed to the planned "distance".
            if (! Schema::hasColumn('trips', 'actual_distance')) {
                $table->decimal('actual_distance', 8, 2)->nullable()->after('completed_at');
            }
            if (! Schema::hasColumn('trips', 'actual_duration_min')) {
                $table->unsignedInteger('actual_duration_min')->nullable()->after('actual_distance');
            }

            // Resolved once at completion so history and performance queries
            // never have to recompute the grace-period rule per row.
            if (! Schema::hasColumn('trips', 'was_on_time')) {
                $table->boolean('was_on_time')->nullable()->after('actual_duration_min');
            }
        });

        // The original enum carries four states; the driver run needs seven.
        // Enum values are plain text outside MySQL, so nothing to alter there.
        if (DB::getDriverName() === 'mysql') {
            $values = collect(TripStatus::values())
                ->map(fn ($value) => "'" . $value . "'")
                ->implode(',');

            $default = TripStatus::Scheduled->value;

            DB::statement(
                "ALTER TABLE trips MODIFY COLUMN status ENUM({$values}) NOT NULL DEFAULT '{$default}'"
            );
        }
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn([
                'confirmation_status',
                'confirmed_at',
                'confirmed_via',
                'en_route_at',
                'arrived_pickup_at',
                'started_at',
                'arrived_dropoff_at',
                'completed_at',
                'actual_distance',
                'actual_duration_min',
                'was_on_time',
            ]);
        });

        if (DB::getDriverName() === 'mysql') {
            // Any trip sitting in one of the new states would violate the
            // narrower enum, so fold them back to the nearest legacy state.
            DB::table('trips')
                ->whereIn('status', ['en_route', 'arrived_pickup', 'arrived_dropoff'])
                ->update(['status' => 'in_progress']);

            DB::statement(
                "ALTER TABLE trips MODIFY COLUMN status "
                . "ENUM('scheduled','in_progress','completed','cancelled') NOT NULL DEFAULT 'scheduled'"
            );
        }
    }
};
