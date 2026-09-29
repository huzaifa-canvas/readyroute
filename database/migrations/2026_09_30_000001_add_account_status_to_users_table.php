<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Companies (and any other account) need to be suspendable and recoverable.
 *
 * Until now "remove company" was a hard delete, which made the wireframe's
 * restore screen impossible to build and meant a mis-click destroyed a tenant
 * and every trip hanging off it. Soft deletes plus an explicit status column
 * give the admin a reversible path: suspend to lock an account out while
 * keeping it visible, archive to hide it, restore to bring it back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'status')) {
                $table->enum('status', ['active', 'suspended'])
                    ->default('active')
                    ->after('role');
            }

            if (! Schema::hasColumn('users', 'suspended_at')) {
                $table->timestamp('suspended_at')->nullable()->after('status');
            }

            // Kept so the admin can see why an account was locked without
            // digging through an audit log.
            if (! Schema::hasColumn('users', 'suspension_reason')) {
                $table->string('suspension_reason', 255)->nullable()->after('suspended_at');
            }

            if (! Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $drop = array_values(array_filter(
                ['status', 'suspended_at', 'suspension_reason', 'deleted_at'],
                fn ($column) => Schema::hasColumn('users', $column)
            ));

            if ($drop) {
                $table->dropColumn($drop);
            }
        });
    }
};
