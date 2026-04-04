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
        Schema::create('search_alert_matches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('search_alert_id')->constrained('search_alerts')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('ad_id')->constrained('ad')->cascadeOnDelete();
            $table->timestamp('matched_at');
            $table->timestamp('digest_sent_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['search_alert_id', 'ad_id']);
            $table->index(['user_id', 'digest_sent_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('search_alert_matches');
    }
};
