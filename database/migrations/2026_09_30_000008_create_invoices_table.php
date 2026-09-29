<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invoices and claims a company sends to its payers.
 *
 * The money lives on the invoice line, not on the trip: a trip's worth depends
 * on who is being billed and under which contract, and the same trip can be
 * re-billed after a rejection. Keeping it here means trips stay an operational
 * record and nothing has to be back-filled when rates change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('dispatcher_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Human reference, unique inside the company: INV-2026-0001.
            $table->string('number', 40);

            // Who is being billed. Free text plus a coarse type, because the
            // payer may be a broker, an insurer or the client themselves.
            $table->string('payer_name', 160);
            $table->string('payer_type', 30)->default('other');
            $table->string('payer_reference', 120)->nullable();

            $table->date('issued_on');
            $table->date('due_on')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            // draft → submitted → processing → paid, or rejected at any point.
            $table->string('status', 20)->default('draft');

            // Cached sum of the lines, so a list does not aggregate per row.
            $table->decimal('amount', 12, 2)->default(0);

            $table->string('rejection_reason', 255)->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(['dispatcher_id', 'number']);
            $table->index(['dispatcher_id', 'status', 'issued_on']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('invoice_id')
                ->constrained('invoices')
                ->cascadeOnDelete();

            // Nulled rather than cascaded: deleting a trip must not silently
            // change what was billed.
            $table->foreignId('trip_id')
                ->nullable()
                ->constrained('trips')
                ->nullOnDelete();

            $table->string('description', 255);
            $table->decimal('amount', 12, 2)->default(0);

            $table->timestamps();

            $table->index('trip_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
