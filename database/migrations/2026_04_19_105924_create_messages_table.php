<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignUuid('sender_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['text', 'image', 'file', 'system'])->default('text');
            $table->text('body')->nullable();
            $table->string('body_iv')->nullable();
            $table->jsonb('attachments')->nullable();
            $table->uuid('reply_to_id')->nullable();
            $table->enum('status', ['sending', 'sent', 'delivered', 'read'])->default('sent');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['conversation_id', 'created_at']);
            $table->index('sender_id');
            $table->index('status');
            $table->index('reply_to_id');
        });

        // Self-referential FK must be added after the table is created (PostgreSQL requirement)
        Schema::table('messages', function (Blueprint $table) {
            $table->foreign('reply_to_id')
                ->references('id')
                ->on('messages')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
