<?php

declare(strict_types=1);

use App\Models\Ad;
use App\Models\AdInteraction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

function insertBoostPackAndBoost(Ad $ad, User $owner, Carbon $startedAt, int $durationDays, string $status = 'active'): string
{
    $packId = Str::uuid()->toString();
    DB::table('boost_packs')->insert([
        'id' => $packId,
        'name' => 'ROI Test Pack',
        'slug' => 'roi-test-pack-' . Str::random(6),
        'boost_score' => 5,
        'duration_days' => $durationDays,
        'price_credits' => 10,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('ad_boosts')->insert([
        'id' => Str::uuid()->toString(),
        'ad_id' => $ad->id,
        'user_id' => $owner->id,
        'boost_pack_id' => $packId,
        'credits_spent' => 10,
        'boost_score' => 5,
        'duration_days' => $durationDays,
        'started_at' => $startedAt,
        'expires_at' => $startedAt->copy()->addDays($durationDays),
        'status' => $status,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $packId;
}

it('returns 404 when the ad has no boost', function (): void {
    $owner = User::factory()->agents()->create();
    Sanctum::actingAs($owner);

    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
        $ad = Ad::factory()->create(['user_id' => $owner->id, 'status' => 'available']);
    });

    $this->getJson("/api/v1/my/ads/{$ad->id}/boost/roi")->assertNotFound();
});

it('returns 403 for non-owner', function (): void {
    $owner = User::factory()->agents()->create();
    $other = User::factory()->agents()->create();
    Sanctum::actingAs($other);

    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
        $ad = Ad::factory()->create(['user_id' => $owner->id, 'status' => 'available']);
    });

    $this->getJson("/api/v1/my/ads/{$ad->id}/boost/roi")->assertForbidden();
});

it('returns before/during windows and correct delta for an active boost', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00'));

    $owner = User::factory()->agents()->create();
    Sanctum::actingAs($owner);

    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
        $ad = Ad::factory()->create(['user_id' => $owner->id, 'status' => 'available']);
    });

    // Boost started 3 days ago, duration = 7 days (still active)
    $startedAt = Carbon::parse('2026-06-12 00:00:00');
    insertBoostPackAndBoost($ad, $owner, $startedAt, 7, 'active');

    // 2 views BEFORE boost (in the 7-day window before boost start)
    $insertView = fn (string $date) => DB::table('ad_interactions')->insert([
        'id' => Str::uuid()->toString(),
        'ad_id' => $ad->id,
        'user_id' => $owner->id,
        'type' => AdInteraction::TYPE_VIEW,
        'created_at' => $date,
    ]);
    $insertView('2026-06-08');
    $insertView('2026-06-08');

    // 5 views DURING boost
    $insertView('2026-06-13');
    $insertView('2026-06-13');
    $insertView('2026-06-13');
    $insertView('2026-06-13');
    $insertView('2026-06-13');

    $data = $this->getJson("/api/v1/my/ads/{$ad->id}/boost/roi")
        ->assertOk()
        ->json('data');

    expect($data['windows']['before']['views'])->toBe(2)
        ->and($data['windows']['during']['views'])->toBe(5)
        ->and($data['windows']['after'])->toBeNull()
        ->and($data['delta']['views']['absolute'])->toBe(3)
        ->and($data['delta']['views']['percent'])->toEqual(150);
});

it('returns after window when boost has expired', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-07-01 12:00:00'));

    $owner = User::factory()->agents()->create();
    Sanctum::actingAs($owner);

    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
        $ad = Ad::factory()->create(['user_id' => $owner->id, 'status' => 'available']);
    });

    // Boost ran from June 1–7 (expired)
    $startedAt = Carbon::parse('2026-06-01 00:00:00');
    insertBoostPackAndBoost($ad, $owner, $startedAt, 7, 'expired');

    // 1 view in "after" window (June 8–14)
    DB::table('ad_interactions')->insert([
        'id' => Str::uuid()->toString(),
        'ad_id' => $ad->id,
        'user_id' => $owner->id,
        'type' => AdInteraction::TYPE_VIEW,
        'created_at' => '2026-06-10',
    ]);

    $data = $this->getJson("/api/v1/my/ads/{$ad->id}/boost/roi")
        ->assertOk()
        ->json('data');

    expect($data['windows']['after'])->not->toBeNull()
        ->and($data['windows']['after']['views'])->toBe(1);
});

it('returns null percent delta when before count is zero', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00'));

    $owner = User::factory()->agents()->create();
    Sanctum::actingAs($owner);

    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
        $ad = Ad::factory()->create(['user_id' => $owner->id, 'status' => 'available']);
    });

    $startedAt = Carbon::parse('2026-06-12 00:00:00');
    insertBoostPackAndBoost($ad, $owner, $startedAt, 7, 'active');

    // No interactions before — 3 during
    foreach (range(1, 3) as $_) {
        DB::table('ad_interactions')->insert([
            'id' => Str::uuid()->toString(),
            'ad_id' => $ad->id,
            'user_id' => $owner->id,
            'type' => AdInteraction::TYPE_IMPRESSION,
            'created_at' => '2026-06-13',
        ]);
    }

    $data = $this->getJson("/api/v1/my/ads/{$ad->id}/boost/roi")
        ->assertOk()
        ->json('data');

    expect($data['delta']['impressions']['absolute'])->toBe(3)
        ->and($data['delta']['impressions']['percent'])->toBeNull();
});
