<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('property_attribute_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('property_attributes', function (Blueprint $table): void {
            $table->foreignId('property_attribute_category_id')
                ->nullable()
                ->constrained('property_attribute_categories')
                ->nullOnDelete();
            $table->string('admin_icon')->default('heroicon-o-check-circle');
        });

        DB::table('property_attributes')->update([
            'admin_icon' => DB::raw('icon'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('property_attributes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('property_attribute_category_id');
            $table->dropColumn('admin_icon');
        });

        Schema::dropIfExists('property_attribute_categories');
    }
};
