<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'role')) {
                $table->enum('role', ['admin', 'dispatcher', 'driver'])->default('dispatcher')->after('email');
            }
            if (!Schema::hasColumn('users', 'dispatcher_id')) {
                $table->foreignId('dispatcher_id')->nullable()->after('role')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('users', 'phone_number')) {
                $table->string('phone_number')->nullable()->after('dispatcher_id');
            }
            if (!Schema::hasColumn('users', 'profile_image')) {
                $table->string('profile_image')->nullable()->after('phone_number');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['dispatcher_id']);
            $table->dropColumn(['role', 'dispatcher_id', 'phone_number', 'profile_image']);
        });
    }
};
