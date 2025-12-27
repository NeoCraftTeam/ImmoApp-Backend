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
        Schema::table('payments', function (Blueprint $table) {
            // Rendre ad_id nullable car pour les abonnements d'agence on n'a pas d'annonce spécifique
            $table->uuid('ad_id')->nullable()->change();

            // On convertit les enums en string pour donner plus de flexibilité et éviter les blocages de types
            $table->string('type')->change(); // 'unlock', 'boost', 'subscription'
            $table->string('payment_method')->change(); // 'fedapay', 'orange_money', etc.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->uuid('ad_id')->nullable(false)->change();
            // Revenir à l'enum est complexe en migration descendante, on laisse en string
        });
    }
};
