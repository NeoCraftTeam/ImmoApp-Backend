<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_screening_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('lease_contract_id')->constrained('lease_contracts')->cascadeOnDelete();
            $table->foreignUuid('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('tenant_name');
            $table->string('tenant_email');
            $table->string('token', 64)->unique();
            $table->string('status', 32)->default('pending');
            $table->jsonb('required_documents')->nullable();
            $table->text('landlord_notes')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['lease_contract_id', 'status']);
            $table->index('status');
        });

        Schema::create('tenant_screening_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('screening_request_id')->constrained('tenant_screening_requests')->cascadeOnDelete();
            $table->string('document_type', 32);
            $table->string('original_name');
            $table->string('disk', 32)->default('local');
            $table->string('path');
            $table->string('mime_type', 100);
            $table->unsignedInteger('size_bytes');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['screening_request_id', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_screening_documents');
        Schema::dropIfExists('tenant_screening_requests');
    }
};
