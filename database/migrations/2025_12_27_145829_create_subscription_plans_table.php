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
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name'); // Basic, Premium, Enterprise
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2); // Prix mensuel
            $table->integer('duration_days')->default(30); // Durée en jours
            $table->integer('boost_score')->default(0); // Score de boost (0-100)
            $table->integer('boost_duration_days')->default(7); // Durée du boost par annonce
            $table->integer('max_ads')->nullable(); // Nombre max d'annonces (null = illimité)
            $table->json('features')->nullable(); // Fonctionnalités supplémentaires
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
