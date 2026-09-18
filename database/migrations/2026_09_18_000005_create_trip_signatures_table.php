<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Trip sign-off signatures. The image itself lives on a private disk and is
     * only ever served through a short-lived signed URL, so this table stores
     * the path rather than the image.
     *
     * signer_role is recorded now so a passenger signature can be added later
     * alongside the driver's without a schema change.
     */
    public function up(): void
    {
        Schema::create('trip_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('trips')->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('signer_role', 20)->default('driver');
            $table->string('signer_name')->nullable();
            $table->string('signature_path');

            $table->timestamp('signed_at');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            // One signature per role per trip; re-signing replaces the record.
            $table->unique(['trip_id', 'signer_role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_signatures');
    }
};
