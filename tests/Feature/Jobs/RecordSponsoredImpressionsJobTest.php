<?php

declare(strict_types=1);

use App\Enums\SponsorshipTier;
use App\Jobs\RecordSponsoredImpressionsJob;
use App\Models\Ad;
use App\Models\SponsoredImpression;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

/**
 * Build a single (ad_id, tier, slot) impression tuple, matching the flat
 * payload shape the queue worker deserialises.
 *
 * @return array{ad_id: string, tier: string, slot: int}
 */
function impressionRow(string $adId, int $slot = 0, SponsorshipTier $tier = SponsorshipTier::PREMIUM): array
{
    return ['ad_id' => $adId, 'tier' => $tier->value, 'slot' => $slot];
}

it('does not double-count when the same batch is redelivered', function (): void {
    $ads = Ad::factory()->count(2)->create(['impression_count' => 0]);
    $rows = $ads->values()
        ->map(fn (Ad $ad, int $index): array => impressionRow((string) $ad->id, $index))
        ->all();

    $job = new RecordSponsoredImpressionsJob($rows, null, 'batch-redelivered');

    // First delivery records the batch; the second (an at-least-once
    // redelivery of the same serialised instance) must be a no-op.
    $job->handle();
    $job->handle();

    foreach ($ads as $ad) {
        expect($ad->refresh()->impression_count)->toBe(1);
    }
    expect(SponsoredImpression::query()->count())->toBe(2);
});

it('rolls back the counter increment when the impression insert fails', function (): void {
    $ad = Ad::factory()->create(['impression_count' => 0]);

    // The second row references a non-existent ad → FK violation on INSERT,
    // after the counter UPDATE has already run within the same handle().
    $rows = [
        impressionRow((string) $ad->id, 0),
        impressionRow((string) Str::uuid(), 1),
    ];

    $job = new RecordSponsoredImpressionsJob($rows, null, 'batch-fk-violation');

    expect(fn () => $job->handle())->toThrow(QueryException::class);

    // Atomicity: the increment must not survive the failed insert.
    expect($ad->refresh()->impression_count)->toBe(0)
        ->and(SponsoredImpression::query()->count())->toBe(0);
});

it('re-runs a rolled-back batch on retry after releasing the idempotency guard', function (): void {
    $ad = Ad::factory()->create(['impression_count' => 0]);
    $goodRows = [impressionRow((string) $ad->id, 0)];
    $badRows = [...$goodRows, impressionRow((string) Str::uuid(), 1)];

    // Attempt 1 fails (FK violation): the transaction rolls back and the
    // idempotency guard for this batch must be released.
    try {
        new RecordSponsoredImpressionsJob($badRows, null, 'batch-retry')->handle();
    } catch (QueryException) {
        // expected — the batch rolled back and freed its guard.
    }

    // Attempt 2 (same batchId, corrected rows) must write exactly once. If the
    // guard were not released, this would short-circuit and record nothing.
    new RecordSponsoredImpressionsJob($goodRows, null, 'batch-retry')->handle();

    expect($ad->refresh()->impression_count)->toBe(1)
        ->and(SponsoredImpression::query()->count())->toBe(1);
});
