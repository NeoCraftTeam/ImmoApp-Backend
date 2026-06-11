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
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->string('tier')->default('basic')->after('slug');
            $table->float('tier_multiplier')->default(2.0)->after('tier');
            $table->boolean('has_trial')->default(false)->after('is_active');
            $table->integer('trial_days')->default(0)->after('has_trial');
            $table->boolean('has_priority_support')->default(false)->after('trial_days');
            $table->boolean('has_analytics')->default(false)->after('has_priority_support');
            $table->boolean('has_api_access')->default(false)->after('has_analytics');

            $table->index('tier');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropIndex(['tier']);
            $table->dropIndex(['is_active']);

            $table->dropColumn([
                'tier',
                'tier_multiplier',
                'has_trial',
                'trial_days',
                'has_priority_support',
                'has_analytics',
                'has_api_access',
            ]);
        });
    }
};
