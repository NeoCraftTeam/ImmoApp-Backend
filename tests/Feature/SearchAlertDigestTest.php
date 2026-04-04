<?php

declare(strict_types=1);

use App\Console\Commands\SendSearchAlertDigests;
use App\Jobs\SendSearchAlertDigestJob;
use App\Models\Ad;
use App\Models\AdType;
use App\Models\Quarter;
use App\Models\SearchAlert;
use App\Models\SearchAlertMatch;
use App\Models\User;
use App\Notifications\SearchAlertDigestNotification;
use App\Services\AiDigestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

// ─── AiDigestService fallback ───────────────────────────────────────────────

it('produces a template summary when no AI provider is configured', function (): void {
    $service = new AiDigestService;

    $quarter = Quarter::factory()->create(['name' => 'Akwa']);
    $adType  = AdType::factory()->create();
    $client  = User::factory()->create();

    $alert = SearchAlert::create([
        'user_id'    => $client->id,
        'label'      => 'Studio Akwa',
        'is_active'  => true,
        'city_name'  => 'Douala',
        'type_name'  => 'Studio',
    ]);

    $ads = Ad::factory()->count(3)->create([
        'quarter_id' => $quarter->id,
        'type_id'    => $adType->id,
        'price'      => 80_000,
    ])->all();

    $summary = $service->summarize($alert, $ads);

    expect($summary)
        ->toContain('3')
        ->toContain('Studio Akwa');
});

it('returns empty string when ads array is empty', function (): void {
    $service = new AiDigestService;
    $alert   = SearchAlert::factory()->create(['label' => 'Test']);

    expect($service->summarize($alert, []))->toBe('');
});

it('template summary uses singular noun for one match', function (): void {
    $service = new AiDigestService;

    $quarter = Quarter::factory()->create();
    $adType  = AdType::factory()->create();
    $client  = User::factory()->create();

    $alert = SearchAlert::create([
        'user_id'   => $client->id,
        'label'     => 'Mon studio',
        'is_active' => true,
    ]);

    $ads = Ad::factory()->count(1)->create([
        'quarter_id' => $quarter->id,
        'type_id'    => $adType->id,
        'price'      => 95_000,
    ])->all();

    $summary = $service->summarize($alert, $ads);

    expect($summary)->toContain('1')->toContain('correspond');
});

// ─── SendSearchAlertDigestJob ────────────────────────────────────────────────

it('sends a digest notification and marks matches as sent', function (): void {
    Notification::fake();

    $quarter  = Quarter::factory()->create();
    $adType   = AdType::factory()->create();
    $landlord = User::factory()->create();
    $client   = User::factory()->create();

    $alert = SearchAlert::create([
        'user_id'   => $client->id,
        'city_id'   => $quarter->city_id,
        'type_id'   => $adType->id,
        'is_active' => true,
        'label'     => 'Yaoundé Studio',
    ]);

    $ads = Ad::factory()->count(2)->create([
        'user_id'    => $landlord->id,
        'quarter_id' => $quarter->id,
        'type_id'    => $adType->id,
        'price'      => 100_000,
    ]);

    foreach ($ads as $ad) {
        SearchAlertMatch::create([
            'search_alert_id' => $alert->id,
            'user_id'         => $client->id,
            'ad_id'           => $ad->id,
            'matched_at'      => now(),
            'digest_sent_at'  => null,
        ]);
    }

    $aiMock = Mockery::mock(AiDigestService::class);
    $aiMock->shouldReceive('summarize')
        ->once()
        ->andReturn('2 nouvelles annonces correspondent à votre alerte « Yaoundé Studio ».');

    app()->instance(AiDigestService::class, $aiMock);

    (new SendSearchAlertDigestJob($client))->handle(app(AiDigestService::class));

    Notification::assertSentTo($client, SearchAlertDigestNotification::class);

    // All matches are marked as sent.
    $this->assertDatabaseMissing('search_alert_matches', [
        'user_id'        => $client->id,
        'digest_sent_at' => null,
    ]);

    // Alert last_notified_at updated.
    expect($alert->fresh()->last_notified_at)->not->toBeNull();
});

it('skips users with no pending matches', function (): void {
    Notification::fake();

    $client = User::factory()->create();

    // No search_alert_matches rows at all.
    (new SendSearchAlertDigestJob($client))->handle(app(AiDigestService::class));

    Notification::assertNothingSent();
});

it('does not re-send already-dispatched matches', function (): void {
    Notification::fake();

    $quarter = Quarter::factory()->create();
    $adType  = AdType::factory()->create();
    $client  = User::factory()->create();

    $alert = SearchAlert::create([
        'user_id'   => $client->id,
        'city_id'   => $quarter->city_id,
        'type_id'   => $adType->id,
        'is_active' => true,
    ]);

    $ad = Ad::factory()->create([
        'quarter_id' => $quarter->id,
        'type_id'    => $adType->id,
    ]);

    // Already sent match.
    SearchAlertMatch::create([
        'search_alert_id' => $alert->id,
        'user_id'         => $client->id,
        'ad_id'           => $ad->id,
        'matched_at'      => now()->subHours(6),
        'digest_sent_at'  => now()->subHours(5),
    ]);

    (new SendSearchAlertDigestJob($client))->handle(app(AiDigestService::class));

    Notification::assertNothingSent();
});

// ─── SendSearchAlertDigests command ─────────────────────────────────────────

it('dispatches one digest job per user with pending matches', function (): void {
    Queue::fake();

    $quarter = Quarter::factory()->create();
    $adType  = AdType::factory()->create();

    $userA = User::factory()->create();
    $userB = User::factory()->create();

    foreach ([$userA, $userB] as $user) {
        $alert = SearchAlert::create([
            'user_id'   => $user->id,
            'city_id'   => $quarter->city_id,
            'type_id'   => $adType->id,
            'is_active' => true,
        ]);

        $ad = Ad::factory()->create([
            'quarter_id' => $quarter->id,
            'type_id'    => $adType->id,
        ]);

        SearchAlertMatch::create([
            'search_alert_id' => $alert->id,
            'user_id'         => $user->id,
            'ad_id'           => $ad->id,
            'matched_at'      => now(),
        ]);
    }

    $this->artisan(SendSearchAlertDigests::class)->assertSuccessful();

    Queue::assertPushed(SendSearchAlertDigestJob::class, 2);
});

it('does not dispatch jobs when no pending matches exist', function (): void {
    Queue::fake();

    $this->artisan(SendSearchAlertDigests::class)->assertSuccessful();

    Queue::assertNothingPushed();
});

it('dry-run mode prints users without dispatching jobs', function (): void {
    Queue::fake();

    $quarter = Quarter::factory()->create();
    $adType  = AdType::factory()->create();
    $client  = User::factory()->create();

    $alert = SearchAlert::create([
        'user_id'   => $client->id,
        'city_id'   => $quarter->city_id,
        'type_id'   => $adType->id,
        'is_active' => true,
    ]);

    $ad = Ad::factory()->create([
        'quarter_id' => $quarter->id,
        'type_id'    => $adType->id,
    ]);

    SearchAlertMatch::create([
        'search_alert_id' => $alert->id,
        'user_id'         => $client->id,
        'ad_id'           => $ad->id,
        'matched_at'      => now(),
    ]);

    $this->artisan(SendSearchAlertDigests::class, ['--dry-run' => true])
        ->expectsOutputToContain('dry-run')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});

// ─── SearchAlertDigestNotification payload ───────────────────────────────────

it('digest notification database payload has correct structure', function (): void {
    Notification::fake();

    $quarter = Quarter::factory()->create();
    $adType  = AdType::factory()->create();
    $client  = User::factory()->create();

    $alert = SearchAlert::create([
        'user_id'   => $client->id,
        'city_id'   => $quarter->city_id,
        'type_id'   => $adType->id,
        'is_active' => true,
        'label'     => 'Studio Douala',
    ]);

    $ads = Ad::factory()->count(2)->create([
        'quarter_id' => $quarter->id,
        'type_id'    => $adType->id,
        'price'      => 75_000,
    ])->all();

    $groups = [
        $alert->id => [
            'alert'   => $alert,
            'ads'     => $ads,
            'summary' => '2 annonces correspondent.',
        ],
    ];

    $notification = new \App\Notifications\SearchAlertDigestNotification($groups);
    $payload      = $notification->toArray($client);

    expect($payload['type'])->toBe('search_alert_digest')
        ->and($payload['total_ads'])->toBe(2)
        ->and($payload['group_count'])->toBe(1)
        ->and($payload['groups'])->toHaveCount(1)
        ->and($payload['groups'][0]['alert_label'])->toBe('Studio Douala')
        ->and($payload['groups'][0]['summary'])->toBe('2 annonces correspondent.')
        ->and($payload['groups'][0]['ad_count'])->toBe(2);
});
