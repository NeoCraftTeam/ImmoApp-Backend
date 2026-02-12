<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add Index for Feed Optimization: (status, created_at)
        Schema::table('ad', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'ad_status_created_at_idx');
        });

        // 2. Add Index for Payment Lookups: (user_id, status)
        Schema::table('payments', function (Blueprint $table) {
            $table->index(['user_id', 'status'], 'payments_user_status_idx');
            $table->index(['user_id', 'ad_id', 'type'], 'payments_user_ad_type_idx');
        });

        // 3. Safety: Change User Deletion Cascade to Restrict
        // This prevents accidental mass deletion of ads if a user is deleted.
        Schema::table('ad', function (Blueprint $table) {
            // Drop existing FK constraints safely
            $table->dropForeign(['user_id']);

            // Re-add with 'restrict' constraint
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ad', function (Blueprint $table) {
            $table->dropIndex('ad_status_created_at_idx');

            // Revert FK to Cascade
            $table->dropForeign(['user_id']);
            $table->foreignUuid('user_id')
                ->change()
                ->constrained('users')
                ->onDelete('cascade');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_user_status_idx');
            $table->dropIndex('payments_user_ad_type_idx');
        });
    }
};
