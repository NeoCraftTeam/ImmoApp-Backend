<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds the missing 'draft' value to the `ad_status_check` PostgreSQL check
 * constraint.
 *
 * The `AdStatus::DRAFT` enum case is referenced by the entire owner draft flow
 * (`AdStatusController::publish`, `::autosave`, `AdController::store` with
 * `is_draft=1`, `CreateAd::execute(isDraft: true)`, etc.) but the database
 * constraint installed by `2026_03_08_154604_update_ad_status_check_constraint`
 * never listed `'draft'`. Result: every attempt to persist a draft hit
 * `SQLSTATE[23514]: Check violation: ad_status_check` and was rolled back —
 * the draft mode was effectively non-functional whenever the constraint was
 * actually enforced (CI / fresh DB).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE "ad" DROP CONSTRAINT IF EXISTS "ad_status_check"');
        DB::statement(
            'ALTER TABLE "ad" ADD CONSTRAINT "ad_status_check" '
            .'CHECK ("status"::text = ANY(ARRAY['
            ."'draft','available','reserved','rent','pending','sold','declined'"
            .']::text[]))'
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE "ad" DROP CONSTRAINT IF EXISTS "ad_status_check"');
        DB::statement(
            'ALTER TABLE "ad" ADD CONSTRAINT "ad_status_check" '
            .'CHECK ("status"::text = ANY(ARRAY['
            ."'available','reserved','rent','pending','sold','declined'"
            .']::text[]))'
        );
    }
};
