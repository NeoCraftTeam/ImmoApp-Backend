<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drafts are intentionally incomplete: the API (`AdRequest::rules()`) and the
 * frontend « Save as draft » flow only require the title, then progressively
 * collect the rest. The original `create_ad` migration declared
 * `description / surface_area / bedrooms / bathrooms` as `NOT NULL`, which
 * crashes Postgres with `23502` whenever a user saves a partial draft.
 *
 * Precedent: `2026_04_08_191618_make_adresse_nullable_on_ad_table` already
 * nullified `adresse` for the same reason. This migration finishes the job
 * for the remaining mandatory columns surfaced by Nightwatch error #74.
 *
 * Validation on PUBLISH (status moving from `draft` → `pending`/`available`)
 * happens at the `AdRequest` layer with the non-draft rule set, so making
 * the columns nullable in DB does NOT weaken any product invariant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad', function (Blueprint $table): void {
            $table->text('description')->nullable()->change();
            $table->decimal('surface_area', 12, 2)->nullable()->change();
            $table->integer('bedrooms')->nullable()->change();
            $table->integer('bathrooms')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Intentionally left blank: reverting would re-introduce the
        // production crash (existing draft rows already contain NULLs).
    }
};
