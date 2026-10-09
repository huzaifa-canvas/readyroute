<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A plan change the company has asked for but is not on yet.
 *
 * Switching tier used to take effect the moment it was clicked, which is wrong
 * in both directions: a company moving down loses features it has already paid
 * for until the end of the month, and one moving up gets the bigger tier for
 * free until the next invoice. Keeping the change pending lets the plan they
 * paid for run to its date and the new one begin when the next period does.
 *
 * Stripe holds the authority through a subscription schedule; these columns
 * exist so the panel can say what is coming without calling Stripe to draw a
 * page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('pending_plan_id')->nullable()->after('subscription_plan_id')
                ->constrained('subscription_plans')->nullOnDelete();
            $table->timestamp('pending_plan_starts_at')->nullable()->after('pending_plan_id');
            $table->string('stripe_schedule_id')->nullable()->after('stripe_subscription_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pending_plan_id');
            $table->dropColumn(['pending_plan_starts_at', 'stripe_schedule_id']);
        });
    }
};
