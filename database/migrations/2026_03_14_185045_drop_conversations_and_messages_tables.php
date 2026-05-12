<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
    }

    public function down(): void
    {
        // Re-create tables if needed — see create_conversations_table and create_messages_table migrations
    }
};
