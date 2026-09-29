<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Licences, medical cards and insurance a driver has to keep current.
 *
 * The compliance screens on both sides read from this one table: the driver
 * sees what of theirs is expiring, the dispatcher sees the same across the
 * whole roster. Expiry is a plain date so "expires within 30 days" is a query
 * rather than something recomputed per row in PHP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_documents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('dispatcher_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('driver_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('type', 40);   // drivers_license, medical_card, insurance, background_check, training, other
            $table->string('label', 160)->nullable();
            $table->string('reference', 120)->nullable(); // licence or policy number

            $table->date('issued_on')->nullable();

            // Nullable because some documents (a completed training course)
            // never expire.
            $table->date('expires_on')->nullable();

            // Path on the private disk; served through a signed URL like
            // signatures are, never linked directly.
            $table->string('file_path', 255)->nullable();

            $table->string('status', 20)->default('valid'); // valid, expiring, expired, missing
            $table->text('notes')->nullable();

            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['dispatcher_id', 'expires_on']);
            $table->index(['driver_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_documents');
    }
};
