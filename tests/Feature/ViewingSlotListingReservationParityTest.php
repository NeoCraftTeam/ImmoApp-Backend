<?php

declare(strict_types=1);

use App\Models\Ad;
use App\Models\UnlockedAd;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

afterEach(function (): void {
    Carbon::setTestNow(null);
});

/**
 * Regression: Zap Ad::isBookableAtTime() called getBookableSlots($date) with default 60m duration
 * while GET /slots uses schedule metadata (e.g. 30m + 10m buffer). The grids disagreed
 * (e.g. 09:40–10:10 listed but POST returned SLOT_NOT_AVAILABLE).
 */
it('lists a slot via GET and accepts the same window on POST with non-default slot duration', function (): void {
    config(['app.timezone' => 'Africa/Douala']);
    Carbon::setTestNow(Carbon::parse('2026-05-12 14:00:00', 'Africa/Douala'));

    $owner = User::factory()->create();
    $client = User::factory()->create();

    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
        $ad = Ad::factory()->create([
            'user_id' => $owner->id,
            'status' => 'available',
        ]);
    });

    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/ads/{$ad->id}/availability", [
        'name' => 'Planning test parité',
        'starts_on' => '2026-05-13',
        'ends_on' => '2026-06-13',
        'periods' => [['starts_at' => '09:00', 'ends_at' => '12:00']],
        'recurrence' => 'daily',
        'slot_duration' => 30,
        'buffer_minutes' => 10,
    ])->assertCreated();

    $day = '2026-05-13';

    $slotsResponse = $this->getJson("/api/v1/ads/{$ad->id}/slots?date={$day}");
    $slotsResponse->assertOk();
    $daySlots = $slotsResponse->json('data.slots_by_date.'.$day);
    expect($daySlots)->toBeArray()->not->toBeEmpty();

    $target = collect($daySlots)->first(
        fn (array $s): bool => ($s['starts_at'] ?? '') === '09:40'
            && ($s['ends_at'] ?? '') === '10:10'
    );
    expect($target)->not->toBeNull()
        ->and($target['is_available'] ?? false)->toBeTrue();

    UnlockedAd::factory()->create([
        'user_id' => $client->id,
        'ad_id' => $ad->id,
        'payment_id' => null,
    ]);

    $this->actingAs($client)
        ->postJson("/api/v1/ads/{$ad->id}/reservations", [
            'slot_date' => $day,
            'slot_starts_at' => '09:40',
            'slot_ends_at' => '10:10',
        ])
        ->assertCreated();

    $slotsAfterFirst = $this->getJson("/api/v1/ads/{$ad->id}/slots?date={$day}");
    $slotsAfterFirst->assertOk();
    $daySlotsAfter = $slotsAfterFirst->json('data.slots_by_date.'.$day);
    $nextAvailable = collect($daySlotsAfter)->first(
        fn (array $s): bool => ($s['is_available'] ?? false) === true
            && (($s['starts_at'] ?? '') !== '09:40' || ($s['ends_at'] ?? '') !== '10:10')
    );
    expect($nextAvailable)->not->toBeNull();

    $this->actingAs($client)
        ->postJson("/api/v1/ads/{$ad->id}/reservations", [
            'slot_date' => $day,
            'slot_starts_at' => $nextAvailable['starts_at'],
            'slot_ends_at' => $nextAvailable['ends_at'],
        ])
        ->assertConflict()
        ->assertJsonPath('error.code', 'CLIENT_ACTIVE_RESERVATION_EXISTS');
});
