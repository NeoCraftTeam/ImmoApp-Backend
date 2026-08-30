<?php

declare(strict_types=1);

use App\Services\Geo\OsmPlaceSynchronizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The `osm_import` schema is created by osm2pgsql at import time, not by a
 * Laravel migration, so the tests build a minimal fixture that mirrors the
 * columns {@see OsmPlaceSynchronizer} reads.
 */
function seedOsmImportSchema(): void
{
    DB::statement('DROP SCHEMA IF EXISTS osm_import CASCADE');
    DB::statement('CREATE SCHEMA osm_import');

    DB::statement(<<<'SQL'
        CREATE TABLE osm_import.places (
            osm_type     text NOT NULL,
            osm_id       bigint NOT NULL,
            name         text NOT NULL,
            display_name text,
            place_type   text,
            country_code text,
            location     geometry(Point, 4326),
            boundary     geometry(MultiPolygon, 4326)
        )
    SQL);

    DB::statement(<<<'SQL'
        CREATE TABLE osm_import.admin_boundaries (
            osm_type     text,
            osm_id       bigint,
            admin_level  int,
            name         text,
            country_code text,
            boundary     geometry(MultiPolygon, 4326)
        )
    SQL);
}

/**
 * Fixture geography (all in Cameroon, country_code CM):
 *  - "Grande"  city    centroid (11.5, 4.0), boundary = square lon 11..12 / lat 3.5..4.5
 *  - "Petite"  town    centroid (11.9, 3.6), NO boundary
 * Quarters:
 *  - "Bastos"     (11.85, 3.62) — inside Grande's boundary, but ~6km from Petite's
 *                 centroid vs ~57km from Grande's → containment must win over centroid.
 *  - "Proche"     (12.2, 3.6)   — outside every boundary, ~33km from Petite → fallback.
 *  - "Orphelin"   (15.0, 8.0)   — outside every boundary, >75km from all → unmatched.
 */
function seedCameroonPlaces(): void
{
    DB::statement(<<<'SQL'
        INSERT INTO osm_import.places
            (osm_type, osm_id, name, display_name, place_type, country_code, location, boundary)
        VALUES
            ('relation', 1, 'Grande', 'Grande', 'city', 'CM',
                ST_SetSRID(ST_MakePoint(11.5, 4.0), 4326),
                ST_Multi(ST_GeomFromText('POLYGON((11 3.5, 12 3.5, 12 4.5, 11 4.5, 11 3.5))', 4326))),
            ('node', 2, 'Petite', 'Petite', 'town', 'CM',
                ST_SetSRID(ST_MakePoint(11.9, 3.6), 4326), NULL),
            ('node', 3, 'Bastos', 'Bastos', 'suburb', 'CM',
                ST_SetSRID(ST_MakePoint(11.85, 3.62), 4326), NULL),
            ('node', 4, 'Proche', 'Proche', 'quarter', 'CM',
                ST_SetSRID(ST_MakePoint(12.2, 3.6), 4326), NULL),
            ('node', 5, 'Orphelin', 'Orphelin', 'neighbourhood', 'CM',
                ST_SetSRID(ST_MakePoint(15.0, 8.0), 4326), NULL)
    SQL);
}

it('attaches a quarter to the city whose boundary contains it, even when another city centroid is nearer', function (): void {
    seedOsmImportSchema();
    seedCameroonPlaces();

    $result = app(OsmPlaceSynchronizer::class)->sync('CM');

    $grande = DB::table('city')->where('name', 'Grande')->first();
    $petite = DB::table('city')->where('name', 'Petite')->first();

    expect($grande)->not->toBeNull()
        ->and($grande->boundary)->not->toBeNull()
        ->and($petite)->not->toBeNull();

    $bastos = DB::table('quarter')->where('name', 'Bastos')->first();

    expect($bastos)->not->toBeNull()
        ->and($bastos->city_id)->toBe($grande->id);
});

it('falls back to the nearest city centroid within the distance cap when no boundary contains the quarter', function (): void {
    seedOsmImportSchema();
    seedCameroonPlaces();

    app(OsmPlaceSynchronizer::class)->sync('CM');

    $petite = DB::table('city')->where('name', 'Petite')->first();
    $proche = DB::table('quarter')->where('name', 'Proche')->first();

    expect($proche)->not->toBeNull()
        ->and($proche->city_id)->toBe($petite->id);
});

it('logs unmatched quarters instead of silently dropping them', function (): void {
    seedOsmImportSchema();
    seedCameroonPlaces();
    Log::spy();

    $result = app(OsmPlaceSynchronizer::class)->sync('CM');

    expect(DB::table('quarter')->where('name', 'Orphelin')->exists())->toBeFalse()
        ->and($result['quarters'])->toBe(2)
        ->and($result['unmatched_quarters'])->toBe(1);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context = []): bool => $message === 'geo.osm.quarters_unmatched'
            && ($context['count'] ?? null) === 1
            && in_array('Orphelin', $context['sample'] ?? [], true));
});
