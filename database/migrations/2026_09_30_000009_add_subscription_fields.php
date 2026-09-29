<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tie a company to a plan, and give plans the limits the usage bars measure
 * against.
 *
 * subscription_plans already existed as a marketing list — name, price,
 * description — with nothing linking a company to one and no numbers to show
 * usage against. Both are added here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            // Null means unlimited, which is what the Enterprise tier is.
            if (! Schema::hasColumn('subscription_plans', 'vehicle_limit')) {
                $table->unsignedInteger('vehicle_limit')->nullable()->after('description');
            }

            if (! Schema::hasColumn('subscription_plans', 'driver_limit')) {
                $table->unsignedInteger('driver_limit')->nullable()->after('vehicle_limit');
            }

            if (! Schema::hasColumn('subscription_plans', 'trip_limit')) {
                $table->unsignedInteger('trip_limit')->nullable()->after('driver_limit');
            }

            // The numeric price, kept alongside the display string so usage
            // and invoicing can do arithmetic without parsing "$599".
            if (! Schema::hasColumn('subscription_plans', 'price_amount')) {
                $table->decimal('price_amount', 10, 2)->nullable()->after('price');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'subscription_plan_id')) {
                $table->foreignId('subscription_plan_id')
                    ->nullable()
                    ->after('status')
                    ->constrained('subscription_plans')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('users', 'subscribed_at')) {
                $table->timestamp('subscribed_at')->nullable()->after('subscription_plan_id');
            }

            // When the current period ends; the screen counts down to this.
            if (! Schema::hasColumn('users', 'renews_at')) {
                $table->date('renews_at')->nullable()->after('subscribed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'subscription_plan_id')) {
                $table->dropForeign(['subscription_plan_id']);
                $table->dropColumn('subscription_plan_id');
            }

            $drop = array_values(array_filter(
                ['subscribed_at', 'renews_at'],
                fn ($column) => Schema::hasColumn('users', $column)
            ));

            if ($drop) {
                $table->dropColumn($drop);
            }
        });

        Schema::table('subscription_plans', function (Blueprint $table) {
            $drop = array_values(array_filter(
                ['vehicle_limit', 'driver_limit', 'trip_limit', 'price_amount'],
                fn ($column) => Schema::hasColumn('subscription_plans', $column)
            ));

            if ($drop) {
                $table->dropColumn($drop);
            }
        });
    }
};
