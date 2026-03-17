<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Jobs\MatchSearchAlertsForAdJob;
use App\Models\Ad;
use App\Models\AdType;
use App\Models\Quarter;
use App\Models\SearchAlert;
use App\Models\User;
use App\Notifications\SearchAlertMatchNotification;
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

it('matches alert criteria and notifies the user', function (): void {
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
        'price' => 100000,
    ]);

    new MatchSearchAlertsForAdJob($ad)->handle();

    Notification::assertSentTo($client, SearchAlertMatchNotification::class);
    expect($alert->fresh()->last_notified_at)->not->toBeNull();
});

it('does not notify the ad owner about their own ad', function (): void {
    Notification::fake();

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

    Notification::assertNothingSent();
});

it('does not notify when alert criteria do not match', function (): void {
    Notification::fake();

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

    Notification::assertNothingSent();
});

it('respects the 1-hour cooldown between notifications per alert', function (): void {
    Notification::fake();

    $quarter = Quarter::factory()->create();
    $adType = AdType::factory()->create();
    $client = User::factory()->create();

    SearchAlert::create([
        'user_id' => $client->id,
        'type_id' => $adType->id,
        'is_active' => true,
        'last_notified_at' => now()->subMinutes(30),
    ]);

    $ad = Ad::factory()->create([
        'quarter_id' => $quarter->id,
        'type_id' => $adType->id,
        'status' => AdStatus::AVAILABLE,
    ]);

    new MatchSearchAlertsForAdJob($ad)->handle();

    Notification::assertNothingSent();
});
