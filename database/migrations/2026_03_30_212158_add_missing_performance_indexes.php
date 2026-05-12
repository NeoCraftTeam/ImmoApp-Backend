<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Soft-delete lookups on ads (most queries filter WHERE deleted_at IS NULL)
        Schema::table('ad', function (Blueprint $table) {
            $table->index('deleted_at', 'ad_deleted_at_index');
        });

        // Users — city_id FK used in geo queries and filters
        Schema::table('users', function (Blueprint $table) {
            $table->index('city_id', 'users_city_id_index');
        });

        // Users — soft-delete lookups
        Schema::table('users', function (Blueprint $table) {
            $table->index('deleted_at', 'users_deleted_at_index');
        });

        // Quarter — city_id FK used in location filtering
        Schema::table('quarter', function (Blueprint $table) {
            $table->index('city_id', 'quarter_city_id_index');
        });

        // Login history — retention pruning and user lookups
        Schema::table('login_histories', function (Blueprint $table) {
            $table->index('created_at', 'login_histories_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('ad', function (Blueprint $table) {
            $table->dropIndex('ad_deleted_at_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_city_id_index');
            $table->dropIndex('users_deleted_at_index');
        });

        Schema::table('quarter', function (Blueprint $table) {
            $table->dropIndex('quarter_city_id_index');
        });

        Schema::table('login_histories', function (Blueprint $table) {
            $table->dropIndex('login_histories_created_at_index');
        });
    }
};
