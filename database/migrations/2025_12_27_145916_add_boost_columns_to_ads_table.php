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
            $table->boolean('is_boosted')->default(false)->after('status');
            $table->integer('boost_score')->default(0)->after('is_boosted');
            $table->timestamp('boost_expires_at')->nullable()->after('boost_score');
            $table->timestamp('boosted_at')->nullable()->after('boost_expires_at');

            $table->index(['is_boosted', 'boost_score']);
            $table->index('boost_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ad', function (Blueprint $table) {
            $table->dropIndex(['is_boosted', 'boost_score']);
            $table->dropIndex(['boost_expires_at']);
            $table->dropColumn(['is_boosted', 'boost_score', 'boost_expires_at', 'boosted_at']);
        });
    }
};
