<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surveys', function (Blueprint $table): void {
            $table->string('slug')->unique()->nullable()->after('title');
            $table->boolean('is_public')->default(true)->after('is_active');
        });

        // Back-fill slugs for existing surveys.
        foreach (\DB::table('surveys')->whereNull('slug')->cursor() as $survey) {
            \DB::table('surveys')->where('id', $survey->id)->update([
                'slug' => Str::slug($survey->title).'-'.Str::lower(Str::random(5)),
            ]);
        }

        Schema::table('surveys', function (Blueprint $table): void {
            $table->string('slug')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('surveys', function (Blueprint $table): void {
            $table->dropColumn(['slug', 'is_public']);
        });
    }
};
