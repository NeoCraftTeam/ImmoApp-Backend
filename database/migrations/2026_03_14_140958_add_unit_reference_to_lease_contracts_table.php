<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lease_contracts', function (Blueprint $table): void {
            $table->string('unit_reference')->nullable()->after('ad_id');
        });
    }

    public function down(): void
    {
        Schema::table('lease_contracts', function (Blueprint $table): void {
            $table->dropColumn('unit_reference');
        });
    }
};
