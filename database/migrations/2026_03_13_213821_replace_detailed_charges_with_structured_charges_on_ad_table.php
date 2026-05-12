<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad', function (Blueprint $table) {
            $table->boolean('charges_forfaitaires')->default(false)->after('detailed_charges');
            $table->unsignedInteger('charges_montant_forfait')->nullable()->after('charges_forfaitaires');
            $table->unsignedInteger('charges_eau')->nullable()->after('charges_montant_forfait');
            $table->unsignedInteger('charges_electricite')->nullable()->after('charges_eau');
            $table->text('charges_autres')->nullable()->after('charges_electricite');
        });
    }

    public function down(): void
    {
        Schema::table('ad', function (Blueprint $table) {
            $table->dropColumn([
                'charges_forfaitaires',
                'charges_montant_forfait',
                'charges_eau',
                'charges_electricite',
                'charges_autres',
            ]);
        });
    }
};
