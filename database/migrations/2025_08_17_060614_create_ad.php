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
        Schema::create('ad', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title')->nullable(false);
            $table->string('slug')->unique();
            $table->text('description')->nullable(false);
            $table->string('adresse')->nullable(false);
            $table->decimal('price', 12, 2)->nullable(true);
            $table->decimal('surface_area', 12, 2)->nullable(false);
            $table->integer('bedrooms')->nullable(false);
            $table->integer('bathrooms')->nullable(false);
            $table->boolean('has_parking')->default(false);
            $table->magellanPoint('location', 4326)->nullable();
            $table->enum('status', ['available', 'reserved', 'rent', 'pending', 'sold']);
            $table->timestamp('expires_at')->nullable();
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignUuid('quarter_id')->constrained('quarter')->onDelete('cascade')->references('id')->on('quarter');
            $table->foreignUuid('type_id')->constrained('ad_type')->onDelete('cascade')->references('id')->on('ad_type');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ad');
    }
};
