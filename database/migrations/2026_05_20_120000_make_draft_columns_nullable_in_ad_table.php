<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Make columns nullable that must be absent on a DRAFT ad.
 *
 * A draft is created with only the title; all other required fields
 * (quarter, type, description, adresse, surface, bedrooms, bathrooms)
 * are filled in later. The original CREATE TABLE defined all of these
 * as NOT NULL, which breaks the autosave INSERT when only the title
 * is present.
 *
 * This migration drops the NOT NULL constraint on the relevant columns
 * using raw DDL so it is independent of doctrine/dbal.
 *
 * Publish-time completeness is enforced at the application layer
 * (AdRequest validation, AdStatusController::publish()).
 */
return new class extends Migration
{
    private const array COLUMNS = [
        'description',
        'adresse',
        'surface_area',
        'bedrooms',
        'bathrooms',
        'quarter_id',
        'type_id',
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $column) {
            DB::statement("ALTER TABLE ad ALTER COLUMN \"{$column}\" DROP NOT NULL");
        }
    }

    public function down(): void
    {
        // Re-add NOT NULL only when every existing row already has a value.
        // On a fresh install (no live data) this is safe; on a production DB
        // with drafts present, run this only after backfilling or deleting drafts.
        foreach (self::COLUMNS as $column) {
            DB::statement("ALTER TABLE ad ALTER COLUMN \"{$column}\" SET NOT NULL");
        }
    }
};
