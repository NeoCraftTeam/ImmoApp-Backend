<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_reactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('message_id');
            $table->uuid('user_id');
            // Single emoji codepoint(s). Length capped at 16 chars to fit
            // surrogate pairs / combining marks while staying tight.
            $table->string('emoji', 16);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('message_id')
                ->references('id')->on('messages')
                ->onDelete('cascade');
            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onDelete('cascade');

            // One reaction per (message, user, emoji) — toggling re-uses the row's
            // delete cycle. Index by message for fast lookup at render time.
            $table->unique(['message_id', 'user_id', 'emoji']);
            $table->index('message_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_reactions');
    }
};
