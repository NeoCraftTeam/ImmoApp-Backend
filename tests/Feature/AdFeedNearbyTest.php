<?php

declare(strict_types=1);

use App\Models\Ad;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
});

/**
 * Reference point (Douala) shared by the geo fixtures below.
 */
const NEARBY_REF_LAT = 4.0500;
const NEARBY_REF_LNG = 9.7000;

/**
 * Seed four ads at increasing distances from the reference point plus one with
 * a NULL location, all publicly listed. Titles encode the expected proximity
 * order so assertions read intent, not coordinates.
 */
function seedNearbyAds(): void
{
    Ad::withoutSyncingToSearch(function (): void {
        Ad::factory()->create([
            'title' => 'NEARBY-1-onspot',
            'status' => 'available',
            'is_visible' => true,
            'location' => Point::makeGeodetic(NEARBY_REF_LAT, NEARBY_REF_LNG),
        ]);
        Ad::factory()->create([
            'title' => 'NEARBY-2-close',
            'status' => 'available',
            'is_visible' => true,
            'location' => Point::makeGeodetic(4.0600, NEARBY_REF_LNG), // ~1.1 km
        ]);
        Ad::factory()->create([
            'title' => 'NEARBY-3-far',
            'status' => 'available',
            'is_visible' => true,
            'location' => Point::makeGeodetic(4.2000, NEARBY_REF_LNG), // ~16.6 km
        ]);
        Ad::factory()->create([
            'title' => 'NEARBY-4-nogeo',
            'status' => 'available',
            'is_visible' => true,
            'location' => null,
        ]);
    });
}

it('orders the feed by proximity when sort=nearby with coordinates', function (): void {
    seedNearbyAds();

    $response = $this->getJson(sprintf(
        '/api/v1/ads/feed?sort=nearby&latitude=%s&longitude=%s&per_page=10',
        NEARBY_REF_LAT,
        NEARBY_REF_LNG,
    ))->assertSuccessful();

    $titles = collect($response->json('data'))->pluck('title')->all();

    // Nearest first; the NULL-location ad is never surfaced by a geo sort.
    expect($titles)->toBe(['NEARBY-1-onspot', 'NEARBY-2-close', 'NEARBY-3-far'])
        ->and($titles)->not->toContain('NEARBY-4-nogeo');
});

it('keeps proximity order across a cursor page boundary', function (): void {
    seedNearbyAds();

    $first = $this->getJson(sprintf(
        '/api/v1/ads/feed?sort=nearby&latitude=%s&longitude=%s&per_page=2',
        NEARBY_REF_LAT,
        NEARBY_REF_LNG,
    ))->assertSuccessful();

    expect(collect($first->json('data'))->pluck('title')->all())
        ->toBe(['NEARBY-1-onspot', 'NEARBY-2-close']);

    $cursor = $first->json('meta.next_cursor');
    expect($cursor)->not->toBeNull();

    $second = $this->getJson(sprintf(
        '/api/v1/ads/feed?sort=nearby&latitude=%s&longitude=%s&per_page=2&cursor=%s',
        NEARBY_REF_LAT,
        NEARBY_REF_LNG,
        urlencode((string) $cursor),
    ))->assertSuccessful();

    $secondTitles = collect($second->json('data'))->pluck('title')->all();

    // Page 2 continues the distance order with no overlap and no SQL error.
    expect($secondTitles)->toBe(['NEARBY-3-far'])
        ->and($secondTitles)->not->toContain('NEARBY-1-onspot')
        ->and($secondTitles)->not->toContain('NEARBY-2-close');
});

it('respects the radius filter when sorting by proximity', function (): void {
    seedNearbyAds();

    $response = $this->getJson(sprintf(
        '/api/v1/ads/feed?sort=nearby&latitude=%s&longitude=%s&radius=2000&per_page=10',
        NEARBY_REF_LAT,
        NEARBY_REF_LNG,
    ))->assertSuccessful();

    $titles = collect($response->json('data'))->pluck('title')->all();

    // 2 km radius keeps the on-spot + close ads, drops the ~16 km one.
    expect($titles)->toBe(['NEARBY-1-onspot', 'NEARBY-2-close']);
});

it('falls back to the default ranking when sort=nearby has no coordinates', function (): void {
    seedNearbyAds();

    $response = $this->getJson('/api/v1/ads/feed?sort=nearby&per_page=10')
        ->assertSuccessful();

    // Without coordinates the geo sort degrades gracefully: no error, and the
    // NULL-location ad is still eligible under the default ranking.
    $titles = collect($response->json('data'))->pluck('title')->all();

    expect($titles)->toContain('NEARBY-4-nogeo')
        ->and(count($titles))->toBe(4);
});
