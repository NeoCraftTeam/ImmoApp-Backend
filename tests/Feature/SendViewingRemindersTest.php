<?php

declare(strict_types=1);

use App\Console\Commands\SendViewingReminders;
use App\Models\Ad;
use App\Models\TentativeReservation;
use App\Models\User;
use App\Notifications\ViewingReminderNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

afterEach(function (): void {
    Carbon::setTestNow(null);
});

it('sends J-1 reminder to client for tomorrow pending reservations', function (): void {
    Notification::fake();
    Carbon::setTestNow(Carbon::parse('2026-06-01 08:00:00'));

    $owner = User::factory()->create();
    $client = User::factory()->create();

    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
        $ad = Ad::factory()->create(['user_id' => $owner->id, 'status' => 'available']);
    });

    $reservation = TentativeReservation::factory()->pending()->create([
        'ad_id' => $ad->id,
        'client_id' => $client->id,
        'slot_date' => '2026-06-02',
        'notified_at' => null,
    ]);

    $this->artisan(SendViewingReminders::class)->assertSuccessful();

    Notification::assertSentTo($client, ViewingReminderNotification::class);
    expect($reservation->fresh()->notified_at)->not->toBeNull();
});

it('sends J-1 reminder for confirmed reservations too', function (): void {
    Notification::fake();
    Carbon::setTestNow(Carbon::parse('2026-06-01 08:00:00'));

    $owner = User::factory()->create();
    $client = User::factory()->create();

    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
        $ad = Ad::factory()->create(['user_id' => $owner->id, 'status' => 'available']);
    });

    TentativeReservation::factory()->confirmed()->create([
        'ad_id' => $ad->id,
        'client_id' => $client->id,
        'slot_date' => '2026-06-02',
        'notified_at' => null,
    ]);

    $this->artisan(SendViewingReminders::class)->assertSuccessful();

    Notification::assertSentTo($client, ViewingReminderNotification::class);
});

it('does not send a reminder if already notified', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-06-01 08:00:00'));

    $owner = User::factory()->create();
    $client = User::factory()->create();

    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
        $ad = Ad::factory()->create(['user_id' => $owner->id, 'status' => 'available']);
    });

    TentativeReservation::factory()->pending()->create([
        'ad_id' => $ad->id,
        'client_id' => $client->id,
        'slot_date' => '2026-06-02',
        'notified_at' => now()->subHour(),
    ]);

    // Fake AFTER factory setup so observer notifications are not intercepted
    Notification::fake();

    $this->artisan(SendViewingReminders::class)->assertSuccessful();

    Notification::assertNothingSent();
});

it('does not send a reminder for cancelled or expired reservations', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-06-01 08:00:00'));

    $owner = User::factory()->create();

    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
        $ad = Ad::factory()->create(['user_id' => $owner->id, 'status' => 'available']);
    });

    // Use distinct clients to avoid unique constraint on (ad_id, client_id) for active reservations
    TentativeReservation::factory()->cancelled()->create([
        'ad_id' => $ad->id,
        'client_id' => User::factory()->create()->id,
        'slot_date' => '2026-06-02',
        'notified_at' => null,
    ]);

    TentativeReservation::factory()->expired()->create([
        'ad_id' => $ad->id,
        'client_id' => User::factory()->create()->id,
        'slot_date' => '2026-06-02',
        'notified_at' => null,
    ]);

    // Fake AFTER factory setup so observer notifications are not intercepted
    Notification::fake();

    $this->artisan(SendViewingReminders::class)->assertSuccessful();

    Notification::assertNothingSent();
});

it('does not send a reminder for reservations not scheduled for tomorrow', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-06-01 08:00:00'));

    $owner = User::factory()->create();

    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
        $ad = Ad::factory()->create(['user_id' => $owner->id, 'status' => 'available']);
    });

    // Today — not tomorrow; use distinct clients to avoid unique constraint
    TentativeReservation::factory()->pending()->create([
        'ad_id' => $ad->id,
        'client_id' => User::factory()->create()->id,
        'slot_date' => '2026-06-01',
        'notified_at' => null,
    ]);

    // Day after tomorrow
    TentativeReservation::factory()->pending()->create([
        'ad_id' => $ad->id,
        'client_id' => User::factory()->create()->id,
        'slot_date' => '2026-06-03',
        'notified_at' => null,
    ]);

    // Fake AFTER factory setup so observer notifications are not intercepted
    Notification::fake();

    $this->artisan(SendViewingReminders::class)->assertSuccessful();

    Notification::assertNothingSent();
});

it('dry-run does not send notifications or update notified_at', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-06-01 08:00:00'));

    $owner = User::factory()->create();
    $client = User::factory()->create();

    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
        $ad = Ad::factory()->create(['user_id' => $owner->id, 'status' => 'available']);
    });

    $reservation = TentativeReservation::factory()->pending()->create([
        'ad_id' => $ad->id,
        'client_id' => $client->id,
        'slot_date' => '2026-06-02',
        'notified_at' => null,
    ]);

    // Fake AFTER factory setup so observer notifications are not intercepted
    Notification::fake();

    $this->artisan(SendViewingReminders::class, ['--dry-run' => true])->assertSuccessful();

    Notification::assertNothingSent();
    expect($reservation->fresh()->notified_at)->toBeNull();
});
