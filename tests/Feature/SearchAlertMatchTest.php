<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Jobs\MatchSearchAlertsForAdJob;
use App\Jobs\SendSearchAlertInstantNotificationJob;
use App\Models\Ad;
use App\Models\AdType;
use App\Models\City;
use App\Models\Quarter;
use App\Models\SearchAlert;
use App\Models\SearchAlertMatch;
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

it('buffers a match and sends an instant notification when criteria match', function (): void {
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
        'notify_email' => true,
        'notify_push' => true,
    ]);

    $ad = Ad::factory()->create([
        'user_id' => $landlord->id,
        'quarter_id' => $quarter->id,
        'type_id' => $adType->id,
        'status' => AdStatus::AVAILABLE,
        'price' => 100_000,
    ]);

    new MatchSearchAlertsForAdJob($ad)->handle();

    Notification::assertSentTo($client, SearchAlertMatchNotification::class);

    $this->assertDatabaseHas('search_alert_matches', [
        'search_alert_id' => $alert->id,
        'user_id' => $client->id,
        'ad_id' => $ad->id,
    ]);

    expect(SearchAlertMatch::query()->where('search_alert_id', $alert->id)->whereNotNull('digest_sent_at')->exists())->toBeTrue();
});

it('sends the instant match notification over the realtime broadcast channel', function (): void {
    Notification::fake();

    $quarter = Quarter::factory()->create();
    $adType = AdType::factory()->create();
    $landlord = User::factory()->create();
    $client = User::factory()->create();

    SearchAlert::create([
        'user_id' => $client->id,
        'city_id' => $quarter->city_id,
        'type_id' => $adType->id,
        'is_active' => true,
        'notify_email' => true,
        'notify_push' => true,
    ]);

    $ad = Ad::factory()->create([
        'user_id' => $landlord->id,
        'quarter_id' => $quarter->id,
        'type_id' => $adType->id,
        'status' => AdStatus::AVAILABLE,
        'price' => 100_000,
    ]);

    new MatchSearchAlertsForAdJob($ad)->handle();

    Notification::assertSentTo(
        $client,
        SearchAlertMatchNotification::class,
        fn (SearchAlertMatchNotification $notification, array $channels): bool => in_array('broadcast', $channels, true)
            && in_array('database', $channels, true)
            && $notification->broadcastAs() === 'search_alert.match'
            && $notification->broadcastType() === 'search_alert_match'
    );
});

it('routes realtime notifications to the user.{id} private channel', function (): void {
    $user = User::factory()->create();

    expect($user->receivesBroadcastNotificationsOn())->toBe("user.{$user->id}");
});

it('matches alert by city_name when city_id is absent (case-insensitive)', function (): void {
    Notification::fake();

    $city = City::factory()->create(['name' => 'Douala Centre']);
    $quarter = Quarter::factory()->create(['city_id' => $city->id]);
    $adType = AdType::factory()->create();
    $landlord = User::factory()->create();
    $client = User::factory()->create();

    SearchAlert::create([
        'user_id' => $client->id,
        'city_id' => null,
        'city_name' => 'douala centre',
        'type_id' => $adType->id,
        'is_active' => true,
        'notify_email' => true,
    ]);

    $ad = Ad::factory()->create([
        'user_id' => $landlord->id,
        'quarter_id' => $quarter->id,
        'type_id' => $adType->id,
        'status' => AdStatus::AVAILABLE,
    ]);

    new MatchSearchAlertsForAdJob($ad)->handle();

    Notification::assertSentTo($client, SearchAlertMatchNotification::class);
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
    Notification::fake();

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

    new MatchSearchAlertsForAdJob($ad)->handle();
    new MatchSearchAlertsForAdJob($ad)->handle();

    $this->assertDatabaseCount('search_alert_matches', 1);

    Notification::assertSentToTimes($client, SearchAlertMatchNotification::class, 1);
});

it('dispatches instant notification job only once per new match', function (): void {
    Queue::fake();

    $quarter = Quarter::factory()->create();
    $adType = AdType::factory()->create();
    $landlord = User::factory()->create();
    $client = User::factory()->create();

    SearchAlert::create([
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
    ]);

    new MatchSearchAlertsForAdJob($ad)->handle();
    new MatchSearchAlertsForAdJob($ad)->handle();

    Queue::assertPushed(SendSearchAlertInstantNotificationJob::class, 1);
});
