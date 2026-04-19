<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add `last_message_id` denormalised FK to conversations.
 *
 * Using a FK instead of latestOfMany() avoids the PostgreSQL MAX(uuid) error
 * and eliminates a correlated subquery on every conversation list load.
 */
return new class () extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->uuid('last_message_id')->nullable()->after('last_message_at');
            $table->foreign('last_message_id')
                ->references('id')
                ->on('messages')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['last_message_id']);
            $table->dropColumn('last_message_id');
        });
    }
};
