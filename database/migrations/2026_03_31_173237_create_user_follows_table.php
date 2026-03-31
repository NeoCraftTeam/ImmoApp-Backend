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
        Schema::create('user_follows', function (Blueprint $table) {
            $table->uuid('follower_id');
            $table->uuid('followed_id');
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['follower_id', 'followed_id']);
            $table->foreign('follower_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('followed_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index('followed_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_follows');
    }
};
