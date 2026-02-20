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
            // Premium info - unlocked after payment
            $table->string('deposit_amount')->nullable()->comment('Dépôt de garantie (ex: 2 mois CHF 1800)');
            $table->string('minimum_lease_duration')->nullable()->comment('Durée bail minimum (ex: 1 an renouvelable)');
            $table->string('detailed_charges')->nullable()->comment('Charges détaillées (ex: Eau/élec CHF 150/mois)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ad', function (Blueprint $table) {
            $table->dropColumn([
                'deposit_amount',
                'minimum_lease_duration',
                'detailed_charges',
            ]);
        });
    }
};
