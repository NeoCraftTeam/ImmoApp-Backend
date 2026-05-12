<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the old constraint that doesn't include 'declined'
        DB::statement('ALTER TABLE "ad" DROP CONSTRAINT IF EXISTS "ad_status_check"');
        // Add updated constraint with all valid AdStatus enum values
        DB::statement(
            'ALTER TABLE "ad" ADD CONSTRAINT "ad_status_check" '
            .'CHECK ("status"::text = ANY(ARRAY['
            ."'available','reserved','rent','pending','sold','declined'"
            .']::text[]))'
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE "ad" DROP CONSTRAINT IF EXISTS "ad_status_check"');
        DB::statement(
            'ALTER TABLE "ad" ADD CONSTRAINT "ad_status_check" '
            .'CHECK ("status"::text = ANY(ARRAY['
            ."'available','reserved','rent','pending','sold'"
            .']::text[]))'
        );
    }
};
