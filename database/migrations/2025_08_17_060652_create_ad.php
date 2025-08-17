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
            $table->id();
            $table->string('title')->nullable(false);
            $table->string('slug')->unique();
            $table->text('description')->nullable(false);
            $table->string('adresse')->nullable(false);
            $table->decimal('price', 12, 2)->nullable(true);
            $table->decimal('surface_area', 12, 2)->nullable(false);
            $table->integer('bedrooms')->nullable(false);
            $table->integer('bathrooms')->nullable(false);
            $table->boolean('has_parking')->default(false);
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->enum('status', ['available', 'reserved', 'rent', 'pending', 'sold']);
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('user_id')->constrained()->onDelete('cascade')->references('id')->on('user');
            $table->foreignId('quarter_id')->constrained()->onDelete('cascade')->references('id')->on('quarter');
            $table->foreignId('type_id')->constrained()->onDelete('cascade')->references('id')->on('ad_type');
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
