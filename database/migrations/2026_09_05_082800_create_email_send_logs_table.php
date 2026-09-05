<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per lifecycle email actually queued for a user.
 *
 * Until now nothing recorded that fact, so the daily engagement run could put
 * the welcome drip, the D7 inactivity reminder and the weekly digest in the
 * same inbox on the same morning — the fastest way to earn a spam complaint.
 * The cap needs to survive a deploy, so this is a table and not the cache.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_send_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('mail_key', 64);
            $table->timestamp('sent_at');

            // Serves both questions asked of this table: "did this user already
            // get this mail lately?" and "how many did they get this week?".
            $table->index(['user_id', 'mail_key', 'sent_at']);
            $table->index('sent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_send_logs');
    }
};
