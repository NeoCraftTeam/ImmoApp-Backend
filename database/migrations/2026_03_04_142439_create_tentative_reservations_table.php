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
        Schema::create('tentative_reservations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ad_id')->constrained('ad')->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('appointment_schedule_id')->constrained('schedules')->cascadeOnDelete();
            $table->date('slot_date');
            $table->time('slot_starts_at');
            $table->time('slot_ends_at');
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'expired'])->default('pending');
            $table->text('client_message')->nullable();
            $table->text('landlord_notes')->nullable();
            $table->enum('cancelled_by', ['client', 'landlord', 'system'])->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Prevent double-booking: only one active reservation per slot
            $table->unique(['ad_id', 'slot_date', 'slot_starts_at', 'status'], 'tr_unique_active_slot')
                ->where('status', 'IN', ['pending', 'confirmed']);

            // Composite indexes for common queries
            $table->index(['ad_id', 'slot_date', 'status'], 'tr_ad_date_status_index');
            $table->index(['client_id', 'status'], 'tr_client_status_index');
            $table->index('expires_at', 'tr_expires_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tentative_reservations');
    }
};
