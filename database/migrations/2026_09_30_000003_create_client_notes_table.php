<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Timestamped, attributed notes on a client profile.
 *
 * clients.special_notes is a single free-text box: it cannot say who wrote a
 * note or when, and a second dispatcher editing it overwrites the first. The
 * design calls for a history the driver can read on the trip screen, so each
 * note becomes its own row with an author and a time.
 *
 * special_notes is deliberately left in place — it still holds standing
 * information about the client rather than dated observations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_notes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('client_id')
                ->constrained('clients')
                ->cascadeOnDelete();

            // Denormalised so a company's notes can be scoped without joining
            // through clients on every driver request.
            $table->foreignId('dispatcher_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Who actually typed it. Nulled rather than cascaded so a note
            // survives the author leaving.
            $table->foreignId('author_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('body');

            // Drivers see the client profile on every assigned trip, so a
            // dispatcher needs a way to keep an internal note off that screen.
            $table->boolean('visible_to_driver')->default(true);

            $table->timestamps();

            $table->index(['client_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_notes');
    }
};
