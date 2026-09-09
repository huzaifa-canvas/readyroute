<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Basic Tier, Professional Tier, Enterprise Tier
            $table->string('slug')->unique(); // basic, professional, enterprise
            $table->string('price'); // $299, $599, Custom
            $table->string('billing_period')->default('/mo'); // /mo
            $table->string('description'); // Up to 10 vehicles. Core dispatching features.
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
