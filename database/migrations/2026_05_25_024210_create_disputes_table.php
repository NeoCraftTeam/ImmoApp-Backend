<?php

declare(strict_types=1);

use App\Enums\DisputeStatus;
use App\Enums\DisputeType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disputes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('reference', 32)->unique();

            $table->string('type')->default(DisputeType::OTHER->value);
            $table->string('status')->default(DisputeStatus::OPEN->value);

            $table->foreignUuid('initiator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('respondent_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('admin_id')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignUuid('ad_id')->nullable()->constrained('ad')->nullOnDelete();
            $table->foreignUuid('lease_id')->nullable()->constrained('lease_contracts')->nullOnDelete();
            $table->foreignUuid('payment_id')->nullable()->constrained('payments')->nullOnDelete();

            $table->string('title');
            $table->text('description');
            $table->bigInteger('amount_claimed')->nullable();
            $table->text('resolution_note')->nullable();

            $table->timestamp('sla_deadline');
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            $table->index(['initiator_id', 'status']);
            $table->index(['respondent_id', 'status']);
            $table->index(['admin_id', 'status']);
            $table->index('status');
            $table->index('type');
        });

        Schema::create('dispute_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('dispute_id')->constrained('disputes')->cascadeOnDelete();
            $table->foreignUuid('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->boolean('is_internal')->default(false);
            $table->timestamps();

            $table->index(['dispute_id', 'created_at']);
        });

        Schema::create('dispute_evidences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('dispute_id')->constrained('disputes')->cascadeOnDelete();
            $table->foreignUuid('uploader_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('disk', 32)->default('public');
            $table->string('path', 500);
            $table->string('original_name', 255)->nullable();
            $table->string('mime_type', 128)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamps();

            $table->index(['dispute_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispute_evidences');
        Schema::dropIfExists('dispute_messages');
        Schema::dropIfExists('disputes');
    }
};
