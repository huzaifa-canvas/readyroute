<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The DVIR checklist template, owned per dispatcher company. ReadyRoute is
     * multi-tenant and fleets differ: a company with no wheelchair vans should
     * not be asked to inspect a wheelchair lift, and another will want an
     * oxygen tank check. Every new company is seeded with a default list.
     */
    public function up(): void
    {
        Schema::create('inspection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispatcher_id')->constrained('users')->cascadeOnDelete();

            $table->string('label');
            $table->unsignedSmallInteger('sort_order')->default(0);

            // A required item blocks submission until it has been checked.
            $table->boolean('is_required')->default(true);

            // Retired items are hidden from new inspections but kept so past
            // inspection records still resolve their labels.
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['dispatcher_id', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_items');
    }
};
