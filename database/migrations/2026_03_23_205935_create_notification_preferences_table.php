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
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('new_viewing_request')->default(true);
            $table->boolean('viewing_confirmed')->default(true);
            $table->boolean('new_review')->default(true);
            $table->boolean('payment_received')->default(true);
            $table->boolean('ad_expired')->default(true);
            $table->boolean('lease_expiring')->default(true);
            $table->boolean('new_message')->default(true);
            $table->boolean('email_enabled')->default(true);
            $table->boolean('push_enabled')->default(true);
            $table->boolean('sms_enabled')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
