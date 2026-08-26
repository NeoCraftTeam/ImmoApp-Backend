<?php

declare(strict_types=1);

use App\Models\Ad;
use App\Models\AdInteraction;
use App\Models\AdType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // The index() endpoint caches pages 1-3 per (page, per_page, type).
    // Each test starts from a clean cache so behaviour is deterministic.
    Cache::flush();
});

// ── /api/v1/ads (offset, paginate) ────────────────────────────────────────────

describe('GET /api/v1/ads — offset pagination', function (): void {
    it('returns paginated ads with meta + links', function (): void {
        Ad::withoutSyncingToSearch(fn () => Ad::factory(5)->create([
            'status' => 'available',
            'is_visible' => true,
        ]));

        $this->getJson('/api/v1/ads?per_page=3&page=1')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                'links' => ['first', 'last', 'prev', 'next'],
            ]);
    });

    it('caches page 1 — second call hits the cache, not the database', function (): void {
        Ad::withoutSyncingToSearch(fn () => Ad::factory(2)->create([
            'status' => 'available',
            'is_visible' => true,
        ]));

        // Prime the cache.
        $first = $this->getJson('/api/v1/ads?per_page=20&page=1')->assertOk();

        // Mutate the DB silently — a cached response must NOT see this row.
        Ad::withoutSyncingToSearch(fn () => Ad::factory()->create([
            'status' => 'available',
            'is_visible' => true,
        ]));

        $second = $this->getJson('/api/v1/ads?per_page=20&page=1')->assertOk();

        expect($second->json('meta.total'))->toBe($first->json('meta.total'));
    });

    it('skips cache when exclude_ids is provided (per-user recommendation feed)', function (): void {
        $ads = null;
        Ad::withoutSyncingToSearch(function () use (&$ads): void {
            $ads = Ad::factory(3)->create([
                'status' => 'available',
                'is_visible' => true,
            ]);
        });

        $excluded = $ads->first()->id;

        $response = $this->getJson('/api/v1/ads?exclude_ids[]='.$excluded)->assertOk();

        $returnedIds = collect($response->json('data'))->pluck('id')->all();
        expect($returnedIds)->not->toContain($excluded);
    });

    it('skips cache for pages beyond 3', function (): void {
        Ad::withoutSyncingToSearch(fn () => Ad::factory(80)->create([
            'status' => 'available',
            'is_visible' => true,
        ]));

        $this->getJson('/api/v1/ads?per_page=20&page=4')
            ->assertOk()
            ->assertJsonPath('meta.current_page', 4);
    });

    it('caps per_page at 100', function (): void {
        Ad::withoutSyncingToSearch(fn () => Ad::factory(2)->create([
            'status' => 'available',
            'is_visible' => true,
        ]));

        // AdRequest validates per_page <= 200 (returns 422 above that). The
        // controller then clamps further to 100 to bound resource serialisation.
        $this->getJson('/api/v1/ads?per_page=150')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100);
    });
});

// ── /api/v1/ads/feed (cursor pagination) ──────────────────────────────────────

describe('GET /api/v1/ads/feed — cursor pagination', function (): void {
    it('returns the first page with a next_cursor and no offset/last_page', function (): void {
        Ad::withoutSyncingToSearch(fn () => Ad::factory(5)->create([
            'status' => 'available',
            'is_visible' => true,
        ]));

        $response = $this->getJson('/api/v1/ads/feed?per_page=3')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        // Cursor paginators expose next/prev cursors via `meta`.
        expect($response->json('meta.next_cursor'))->not->toBeNull();
        expect($response->json('meta'))->not->toHaveKey('last_page');
        expect($response->json('meta'))->not->toHaveKey('total');
    });

    it('walks the entire dataset via cursor without duplicates or gaps', function (): void {
        Ad::withoutSyncingToSearch(fn () => Ad::factory(7)->create([
            'status' => 'available',
            'is_visible' => true,
        ]));

        $seen = [];
        $cursor = null;

        // Defensive bound: should fit in 4 pages of 3, abort if cursor logic loops.
        for ($i = 0; $i < 6; $i++) {
            $url = '/api/v1/ads/feed?per_page=3'.($cursor !== null ? '&cursor='.$cursor : '');
            $response = $this->getJson($url)->assertOk();

            $ids = collect($response->json('data'))->pluck('id')->all();
            foreach ($ids as $id) {
                expect($seen)->not->toContain($id, "Cursor returned duplicate ad {$id}");
                $seen[] = $id;
            }

            $cursor = $response->json('meta.next_cursor');
            if ($cursor === null) {
                break;
            }
        }

        expect($seen)->toHaveCount(7);
    });

    it('caps per_page at 50', function (): void {
        Ad::withoutSyncingToSearch(fn () => Ad::factory(3)->create([
            'status' => 'available',
            'is_visible' => true,
        ]));

        $response = $this->getJson('/api/v1/ads/feed?per_page=999')->assertOk();

        // Cursor paginator exposes per_page in meta.
        expect((int) $response->json('meta.per_page'))->toBe(50);
    });

    it('excludes hidden and non-public ads', function (): void {
        Ad::withoutSyncingToSearch(function (): void {
            Ad::factory()->create(['status' => 'available', 'is_visible' => true]);
            Ad::factory()->create(['status' => 'available', 'is_visible' => false]);
            Ad::factory()->create(['status' => 'draft', 'is_visible' => true]);
        });

        $this->getJson('/api/v1/ads/feed?per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('respects exclude_ids', function (): void {
        $ads = null;
        Ad::withoutSyncingToSearch(function () use (&$ads): void {
            $ads = Ad::factory(3)->create([
                'status' => 'available',
                'is_visible' => true,
            ]);
        });

        $excluded = $ads->first()->id;

        $response = $this->getJson('/api/v1/ads/feed?per_page=10&exclude_ids[]='.$excluded)
            ->assertOk();

        expect(collect($response->json('data'))->pluck('id'))->not->toContain($excluded);
    });

    it('re-ranks feed by user profile when authenticated with interaction history', function (): void {
        $user = User::factory()->create();
        $type1 = AdType::factory()->create();
        $type2 = AdType::factory()->create();

        $type1Ads = $type2Ads = null;

        Ad::withoutSyncingToSearch(function () use ($type1, $type2, &$type1Ads, &$type2Ads): void {
            // type1 ads — older, would normally come later in date-DESC feed
            $type1Ads = Ad::factory(3)->create([
                'status' => 'available',
                'is_visible' => true,
                'type_id' => $type1->id,
                'created_at' => now()->subMinutes(10),
            ]);
            // type2 ads — newer
            $type2Ads = Ad::factory(3)->create([
                'status' => 'available',
                'is_visible' => true,
                'type_id' => $type2->id,
                'created_at' => now()->subMinutes(5),
            ]);
        });

        // Strong type1 preference signal so re-ranking reverses the date order
        $type1Ads->each(function (Ad $ad) use ($user): void {
            AdInteraction::create([
                'user_id' => $user->id,
                'ad_id' => $ad->id,
                'type' => AdInteraction::TYPE_FAVORITE,
                'created_at' => now(),
            ]);
        });

        $response = $this->actingAs($user)->getJson('/api/v1/ads/feed?per_page=10')->assertOk();

        $returnedIds = collect($response->json('data'))->pluck('id')->values()->all();
        $type1Ids = $type1Ads->pluck('id')->all();
        $type2Ids = $type2Ads->pluck('id')->all();

        // First returned ad must be a type1 ad (highest profile score)
        expect($type1Ids)->toContain($returnedIds[0]);

        // All type1 ads must appear before any type2 ad
        $firstType2Pos = collect($returnedIds)->search(fn ($id) => in_array($id, $type2Ids, true));
        $lastType1Pos = collect($returnedIds)->map(fn ($id, $idx) => in_array($id, $type1Ids, true) ? $idx : -1)->max();

        expect($lastType1Pos)->toBeLessThan($firstType2Pos);
    });

    it('includes total_approximate in the response', function (): void {
        Ad::withoutSyncingToSearch(fn () => Ad::factory(4)->create([
            'status' => 'available',
            'is_visible' => true,
        ]));

        $response = $this->getJson('/api/v1/ads/feed?per_page=2')->assertOk();

        expect($response->json('total_approximate'))->toBeInt()->toBeGreaterThanOrEqual(4);
    });

    it('renders when the cached total is a numeric string (Redis cache-hit)', function (): void {
        Ad::withoutSyncingToSearch(fn () => Ad::factory(3)->create([
            'status' => 'available',
            'is_visible' => true,
        ]));

        // Redis's cache store returns cached numeric values as *strings* on a
        // cache-hit (RedisStore stores numerics unserialized). The array store
        // used in tests returns whatever was put, so seeding a string here
        // reproduces the prod condition where AdFeedService receives `total`
        // as a string and the strict-typed AdFeedResult DTO rejected it.
        Cache::put('ads:feed:total:all', '167', 600);

        $this->getJson('/api/v1/ads/feed?per_page=2')
            ->assertOk()
            ->assertJsonPath('total_approximate', 167);
    });

    it('sorts by price ascending when sort=price_asc', function (): void {
        Ad::withoutSyncingToSearch(function (): void {
            Ad::factory()->create(['status' => 'available', 'is_visible' => true, 'price' => 300000]);
            Ad::factory()->create(['status' => 'available', 'is_visible' => true, 'price' => 100000]);
            Ad::factory()->create(['status' => 'available', 'is_visible' => true, 'price' => 200000]);
        });

        $response = $this->getJson('/api/v1/ads/feed?per_page=10&sort=price_asc')->assertOk();
        $prices = collect($response->json('data'))->pluck('price')->map(fn ($p) => (int) $p)->values()->all();

        expect($prices)->toBe(collect($prices)->sort()->values()->all());
    });

    it('sorts by price descending when sort=price_desc', function (): void {
        Ad::withoutSyncingToSearch(function (): void {
            Ad::factory()->create(['status' => 'available', 'is_visible' => true, 'price' => 100000]);
            Ad::factory()->create(['status' => 'available', 'is_visible' => true, 'price' => 300000]);
            Ad::factory()->create(['status' => 'available', 'is_visible' => true, 'price' => 200000]);
        });

        $response = $this->getJson('/api/v1/ads/feed?per_page=10&sort=price_desc')->assertOk();
        $prices = collect($response->json('data'))->pluck('price')->map(fn ($p) => (int) $p)->values()->all();

        expect($prices)->toBe(collect($prices)->sortDesc()->values()->all());
    });

    it('orders boosted ads before non-boosted ones (stable tiebreaker)', function (): void {
        $boosted = null;
        Ad::withoutSyncingToSearch(function () use (&$boosted): void {
            Ad::factory()->create([
                'status' => 'available',
                'is_visible' => true,
                'boost_score' => 0,
                'created_at' => now()->subDays(2),
            ]);
            $boosted = Ad::factory()->create([
                'status' => 'available',
                'is_visible' => true,
                'boost_score' => 100,
                'is_boosted' => true,
                'created_at' => now()->subDays(1),
            ]);
            Ad::factory()->create([
                'status' => 'available',
                'is_visible' => true,
                'boost_score' => 0,
                'created_at' => now(),
            ]);
        });

        $response = $this->getJson('/api/v1/ads/feed?per_page=10')->assertOk();
        $firstId = $response->json('data.0.id');

        // The boosted ad (highest boost_score) must come first regardless
        // of its created_at being older than the non-boosted ad.
        expect($firstId)->toBe($boosted->id);
    });
});
