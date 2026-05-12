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
        Schema::create('email_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('ad_updates')->default(true);
            $table->boolean('search_alerts')->default(true);
            $table->boolean('subscription_updates')->default(true);
            $table->boolean('survey_notifications')->default(true);
            $table->boolean('admin_notifications')->default(true);
            $table->boolean('welcome_emails')->default(true);
            $table->string('unsubscribe_token', 64)->unique();
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_preferences');
    }
};
