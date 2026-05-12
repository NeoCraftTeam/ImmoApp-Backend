<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('point_packages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');                          // e.g. "Pack Starter"
            $table->unsignedInteger('price');                // monetary value in XOF
            $table->unsignedInteger('points_awarded');       // points credited to the user
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_packages');
    }
};
