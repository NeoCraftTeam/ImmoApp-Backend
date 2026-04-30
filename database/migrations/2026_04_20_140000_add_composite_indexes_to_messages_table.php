<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add composite indexes for markAsRead bulk update and unread count queries.
 *
 * (conversation_id, sender_id, status) — used by ConversationService::markAsRead()
 * which filters on all three columns in a single UPDATE statement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->index(
                ['conversation_id', 'sender_id', 'status'],
                'messages_conv_sender_status_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_conv_sender_status_idx');
        });
    }
};
