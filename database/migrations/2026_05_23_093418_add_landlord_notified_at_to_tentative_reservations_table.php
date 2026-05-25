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
        Schema::table('tentative_reservations', function (Blueprint $table): void {
            $table->timestamp('landlord_notified_at')->nullable()->after('notified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tentative_reservations', function (Blueprint $table): void {
            $table->dropColumn('landlord_notified_at');
        });
    }
};
