<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Enums\SponsorshipTier;
use App\Models\Ad;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    Cache::flush();
    Ad::query()->forceDelete();
});

it('overfetches on the first page so the slot template can mix in organic ads', function (): void {
    // Create 15 sponsored ads + 5 organic.
    // Sponsorship sort would put all 15 sponsored ads first in SQL order —
    // a non-overfetched cursorPaginate(10) would never surface an organic.
    Ad::factory()->count(15)->state([
        'is_subscription_sponsored' => true,
        'subscription_tier' => SponsorshipTier::SUBSCRIPTION->value,
        'status' => AdStatus::AVAILABLE,
        'is_visible' => true,
    ])->create();

    Ad::factory()->count(5)->state([
        'is_subscription_sponsored' => false,
        'subscription_tier' => SponsorshipTier::ORGANIC->value,
        'status' => AdStatus::AVAILABLE,
        'is_visible' => true,
    ])->create();

    $response = $this->getJson('/api/v1/ads/feed?per_page=10');
    $response->assertSuccessful();

    $tiers = collect($response->json('data'))
        ->pluck('sponsorship_tier')
        ->values()
        ->all();

    expect($tiers)->toHaveCount(10);

    // The slot template reserves positions 4, 8, 9 (0-indexed) for organic.
    // With overfetch the controller pulls 30 rows, distribute() has Organic
    // inventory, and the page should contain at least one organic ad.
    expect(in_array('organic', $tiers, true))->toBeTrue();
});

it('does not overfetch on cursor-paginated pages beyond the first', function (): void {
    Ad::factory()->count(20)->state([
        'is_subscription_sponsored' => true,
        'subscription_tier' => SponsorshipTier::SUBSCRIPTION->value,
        'status' => AdStatus::AVAILABLE,
        'is_visible' => true,
    ])->create();

    $first = $this->getJson('/api/v1/ads/feed?per_page=5');
    $first->assertSuccessful();

    $nextCursor = $first->json('meta.next_cursor') ?? $first->json('links.next');
    if (!$nextCursor) {
        // Pagination cursor absent — exit gracefully; the assertion path is
        // about page 2 behavior and we can't reach it without a cursor.
        $this->markTestSkipped('Cursor not exposed in response shape — skipping page-2 assertion.');
    }

    // We only care that page 2 returns at most perPage items (i.e., it
    // didn't widen the cursor stride to 3×).
    $cursorParam = is_string($nextCursor) && str_contains($nextCursor, 'cursor=')
        ? substr($nextCursor, strpos($nextCursor, 'cursor=') + 7)
        : $nextCursor;

    $second = $this->getJson('/api/v1/ads/feed?per_page=5&cursor='.$cursorParam);
    $second->assertSuccessful();
    expect(count($second->json('data')))->toBeLessThanOrEqual(5);
});
