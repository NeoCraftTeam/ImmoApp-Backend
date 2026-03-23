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
        Schema::table('reviews', function (Blueprint $table) {
            $table->boolean('is_verified')->default(false)->after('agency_id');
            $table->text('owner_response')->nullable()->after('is_verified');
            $table->timestamp('owner_responded_at')->nullable()->after('owner_response');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn(['is_verified', 'owner_response', 'owner_responded_at']);
        });
    }
};
