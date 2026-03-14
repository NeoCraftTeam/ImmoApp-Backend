<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lease_contracts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('ad_id')->constrained('ad')->cascadeOnDelete();
            $table->string('contract_number')->unique();
            $table->string('tenant_name');
            $table->string('tenant_phone');
            $table->string('tenant_email')->nullable();
            $table->string('tenant_id_number')->nullable();
            $table->date('lease_start');
            $table->date('lease_end');
            $table->unsignedSmallInteger('lease_duration_months');
            $table->decimal('monthly_rent', 12, 2);
            $table->decimal('deposit_amount', 12, 2)->nullable();
            $table->text('special_conditions')->nullable();
            $table->string('pdf_path');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lease_contracts');
    }
};
