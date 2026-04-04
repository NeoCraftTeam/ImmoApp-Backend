<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Jobs\MatchSearchAlertsForAdJob;
use App\Models\Ad;
use App\Models\AdType;
use App\Models\Quarter;
use App\Models\SearchAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('dispatches MatchSearchAlertsForAdJob when ad becomes available', function (): void {
    Queue::fake();

    $ad = Ad::factory()->create(['status' => AdStatus::PENDING]);
    $ad->update(['status' => AdStatus::AVAILABLE]);

    Queue::assertPushed(MatchSearchAlertsForAdJob::class, fn ($job) => $job->ad->id === $ad->id);
});

it('does not dispatch job for non-available status changes', function (): void {
    Queue::fake();

    $ad = Ad::factory()->create(['status' => AdStatus::RESERVED]);
    $ad->update(['status' => AdStatus::SOLD]);

    Queue::assertNotPushed(MatchSearchAlertsForAdJob::class);
});

it('buffers a match into search_alert_matches when criteria match', function (): void {
    Notification::fake();

    $quarter = Quarter::factory()->create();
    $adType = AdType::factory()->create();
    $landlord = User::factory()->create();
    $client = User::factory()->create();

    $alert = SearchAlert::create([
        'user_id' => $client->id,
        'city_id' => $quarter->city_id,
        'type_id' => $adType->id,
        'is_active' => true,
    ]);

    $ad = Ad::factory()->create([
        'user_id' => $landlord->id,
        'quarter_id' => $quarter->id,
        'type_id' => $adType->id,
        'status' => AdStatus::AVAILABLE,
        'price' => 100_000,
    ]);

    new MatchSearchAlertsForAdJob($ad)->handle();

    // No immediate notification — match is buffered for digest.
    Notification::assertNothingSent();

    $this->assertDatabaseHas('search_alert_matches', [
        'search_alert_id' => $alert->id,
        'user_id' => $client->id,
        'ad_id' => $ad->id,
        'digest_sent_at' => null,
    ]);
});

it('does not buffer a match for the ad owner', function (): void {
    $quarter = Quarter::factory()->create();
    $adType = AdType::factory()->create();
    $owner = User::factory()->create();

    SearchAlert::create([
        'user_id' => $owner->id,
        'city_id' => $quarter->city_id,
        'type_id' => $adType->id,
        'is_active' => true,
    ]);

    $ad = Ad::factory()->create([
        'user_id' => $owner->id,
        'quarter_id' => $quarter->id,
        'type_id' => $adType->id,
        'status' => AdStatus::AVAILABLE,
    ]);

    new MatchSearchAlertsForAdJob($ad)->handle();

    $this->assertDatabaseCount('search_alert_matches', 0);
});

it('does not buffer when alert criteria do not match', function (): void {
    $quarter = Quarter::factory()->create();
    $adType1 = AdType::factory()->create();
    $adType2 = AdType::factory()->create();
    $client = User::factory()->create();

    SearchAlert::create([
        'user_id' => $client->id,
        'type_id' => $adType1->id,
        'is_active' => true,
    ]);

    $ad = Ad::factory()->create([
        'quarter_id' => $quarter->id,
        'type_id' => $adType2->id,
        'status' => AdStatus::AVAILABLE,
    ]);

    new MatchSearchAlertsForAdJob($ad)->handle();

    $this->assertDatabaseCount('search_alert_matches', 0);
});

it('does not create duplicate match records for the same ad and alert', function (): void {
    $quarter = Quarter::factory()->create();
    $adType = AdType::factory()->create();
    $client = User::factory()->create();

    SearchAlert::create([
        'user_id' => $client->id,
        'city_id' => $quarter->city_id,
        'type_id' => $adType->id,
        'is_active' => true,
    ]);

    $ad = Ad::factory()->create([
        'quarter_id' => $quarter->id,
        'type_id' => $adType->id,
        'status' => AdStatus::AVAILABLE,
        'price' => 120_000,
    ]);

    // Run the job twice (e.g. retry scenario).
    new MatchSearchAlertsForAdJob($ad)->handle();
    new MatchSearchAlertsForAdJob($ad)->handle();

    // Still only one row thanks to the unique constraint + insertOrIgnore.
    $this->assertDatabaseCount('search_alert_matches', 1);
});
