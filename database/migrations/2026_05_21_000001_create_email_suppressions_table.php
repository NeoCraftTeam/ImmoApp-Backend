<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E-2 : Suppression list for transactional email.
 *
 * Populated by the Resend webhook handler when a bounce or spam complaint
 * is received. Before every outgoing email is sent, Laravel's MessageSending
 * listener checks this table — if the recipient is listed, the send is silently
 * cancelled to protect sender reputation and comply with CAN-SPAM / GDPR.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_suppressions', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->unique();
            $table->string('reason');
            $table->string('resend_event_type')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_suppressions');
    }
};
