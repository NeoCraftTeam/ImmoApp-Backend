<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * High-volume telemetry: one row per ad shown in the sponsored feed.
     * Kept separate from ad_interactions so the recommendation engine's
     * profile queries don't have to scan over millions of impression rows.
     */
    public function up(): void
    {
        Schema::create('sponsored_impressions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ad_id')->constrained('ad')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tier', 20); // premium / subscription / manual / organic
            $table->unsignedSmallInteger('slot');
            $table->timestamp('shown_at')->useCurrent();

            $table->index(['shown_at', 'tier'], 'idx_imp_shown_tier');
            $table->index(['ad_id', 'shown_at'], 'idx_imp_ad_shown');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsored_impressions');
    }
};
