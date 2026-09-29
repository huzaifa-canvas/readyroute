<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Finer-grained roles for dispatcher-side staff.
 *
 * users.role stays the coarse account type (admin / dispatcher / driver) —
 * everything already routes and authorises off it, so it is left alone. This
 * table sits alongside it and answers a different question: within a dispatcher
 * company, what is this person allowed to touch? That is what the wireframe's
 * Head Dispatcher, Standard Dispatcher and Read-Only Analyst screens describe.
 *
 * dispatcher_id null means a platform template the admin manages centrally;
 * a company may also define its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('dispatcher_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('slug', 80);
            $table->string('description', 255)->nullable();

            // A flat list of permission keys; App\Support\Permissions is the
            // authority on what is valid.
            $table->json('permissions')->nullable();

            // System roles ship with the platform and cannot be deleted, only
            // copied or edited.
            $table->boolean('is_system')->default(false);

            $table->timestamps();

            // Slugs only have to be unique inside one company.
            $table->unique(['dispatcher_id', 'slug']);
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'role_id')) {
                $table->foreignId('role_id')
                    ->nullable()
                    ->after('role')
                    ->constrained('roles')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'role_id')) {
                $table->dropForeign(['role_id']);
                $table->dropColumn('role_id');
            }
        });

        Schema::dropIfExists('roles');
    }
};
