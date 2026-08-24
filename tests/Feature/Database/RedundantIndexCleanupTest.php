<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * @return list<string>
 */
function indexNamesFor(string $table): array
{
    return array_map(
        static fn (object $row): string => $row->indexname,
        DB::select('SELECT indexname FROM pg_indexes WHERE tablename = ?', [$table]),
    );
}

it('drops the redundant duplicate indexes but keeps the composite supersets', function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Index deduplication is Postgres-only.');
    }

    $payments = indexNamesFor('payments');
    $ad = indexNamesFor('ad');

    // The three `(user_id, status)` duplicates are gone; the wider superset
    // `(user_id, status, created_at)` serves those lookups via its prefix.
    expect($payments)
        ->not->toContain('payments_user_id_status_index')
        ->not->toContain('payments_user_status_idx')
        ->not->toContain('payments_user_status_index')
        ->toContain('payments_user_status_created_idx');

    // `ad_status_created_at_idx` was an exact duplicate of `ad_status_created_idx`;
    // `ad_user_status_idx` is the prefix of `ad_owner_listing_idx`.
    expect($ad)
        ->not->toContain('ad_status_created_at_idx')
        ->not->toContain('ad_user_status_idx')
        ->toContain('ad_status_created_idx')
        ->toContain('ad_owner_listing_idx');
});
