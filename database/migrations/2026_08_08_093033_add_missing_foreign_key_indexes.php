<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Performance audit: PostgreSQL does NOT auto-index foreign keys.
     * These indexes cover frequently queried FK columns identified
     * via Eloquent scope / controller / service analysis.
     */
    public function up(): void
    {
        // ad: intentionally NOT indexing quarter_id / type_id on their own.
        // PostgreSQL can serve single-column lookups from the leading column of
        // an existing composite, and both are already covered:
        //   quarter_id -> ad_quarter_status_idx (quarter_id, status)
        //   type_id    -> ad_type_status_price_idx (type_id, status, price)
        // Adding standalone indexes there would only add write cost on the
        // hottest table in the schema. agency_id is already indexed too.

        // users: role+created_at composite for admin metrics filtering.
        // city_id already indexed (users_city_id_index exists).
        Schema::table('users', function (Blueprint $table) {
            $table->index(['role', 'created_at']);
        });

        // lease_contracts: ad_id and tenant_id are FK columns used
        // in landlord dashboard (baux par annonce) and tenant space.
        Schema::table('lease_contracts', function (Blueprint $table) {
            $table->index('ad_id');
            $table->index('tenant_id');
        });

        // reviews: existing composite [ad_id, user_id] doesn't serve
        // queries on user_id alone ("Mes avis laissés").
        Schema::table('reviews', function (Blueprint $table) {
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });
        Schema::table('lease_contracts', function (Blueprint $table) {
            $table->dropIndex(['tenant_id']);
            $table->dropIndex(['ad_id']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role', 'created_at']);
        });
    }
};
