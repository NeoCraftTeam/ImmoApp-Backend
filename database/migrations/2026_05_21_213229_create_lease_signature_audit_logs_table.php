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
        Schema::create('lease_signature_audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('lease_contract_id')
                ->constrained('lease_contracts')
                ->cascadeOnDelete();

            // nullable so system-generated events (e.g. auto-generation) can be logged without a user
            $table->foreignUuid('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // event type: generated | viewed | downloaded | signed | sent | countersigned
            $table->string('event');

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            // extra context: signer name, signature provider, document hash, etc.
            $table->jsonb('metadata')->nullable();

            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->index(['lease_contract_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lease_signature_audit_logs');
    }
};
