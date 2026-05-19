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
        Schema::table('ad', function (Blueprint $table) {
            $table->string('price_period', 10)
                ->nullable()
                ->default('mois')
                ->after('price')
                ->comment('Période de facturation du loyer : mois (par mois) ou jour (par jour). Null pour les ventes.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ad', function (Blueprint $table) {
            $table->dropColumn('price_period');
        });
    }
};
