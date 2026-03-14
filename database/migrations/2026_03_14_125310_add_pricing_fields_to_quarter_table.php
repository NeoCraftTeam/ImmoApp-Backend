<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quarter', function (Blueprint $table): void {
            $table->decimal('avg_price', 12, 2)->nullable()->after('city_id');
            $table->decimal('avg_price_per_sqm', 12, 2)->nullable()->after('avg_price');
            $table->integer('active_ads_count')->default(0)->after('avg_price_per_sqm');
            $table->timestamp('pricing_updated_at')->nullable()->after('active_ads_count');
        });
    }

    public function down(): void
    {
        Schema::table('quarter', function (Blueprint $table): void {
            $table->dropColumn(['avg_price', 'avg_price_per_sqm', 'active_ads_count', 'pricing_updated_at']);
        });
    }
};
