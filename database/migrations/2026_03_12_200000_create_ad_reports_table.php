<?php

use App\Enums\AdReportReason;
use App\Enums\AdReportStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_reports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('ad_id')->constrained('ad')->cascadeOnDelete();
            $table->foreignUuid('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->default(AdReportReason::OTHER->value);
            $table->string('scam_reason')->nullable();
            $table->json('payment_methods')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default(AdReportStatus::PENDING->value);
            $table->text('admin_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignUuid('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['ad_id', 'status']);
            $table->index(['reporter_id', 'status']);
            $table->index('reason');
            $table->index('scam_reason');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_reports');
    }
};
