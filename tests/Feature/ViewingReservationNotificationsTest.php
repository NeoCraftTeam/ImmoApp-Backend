<?php

declare(strict_types=1);

use App\Events\Reservation\ReservationStatusChanged;
use App\Events\Reservation\SlotAvailabilityChanged;
use App\Models\Ad;
use App\Models\UnlockedAd;
use App\Models\User;
use App\Notifications\ReservationCreatedClientNotification;
use App\Notifications\ReservationCreatedLandlordNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Event::fake([ReservationStatusChanged::class, SlotAvailabilityChanged::class]);
});

afterEach(function (): void {
    Carbon::setTestNow(null);
});

it('notifies the landlord and client by mail and database after a successful reservation', function (): void {
    Notification::fake();

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
        'name' => 'Planning notifications test',
        'starts_on' => '2026-05-13',
        'ends_on' => '2026-06-13',
        'periods' => [['starts_at' => '09:00', 'ends_at' => '12:00']],
        'recurrence' => 'daily',
        'slot_duration' => 30,
        'buffer_minutes' => 10,
    ])->assertCreated();

    $day = '2026-05-13';

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

    Notification::assertSentTo($owner, ReservationCreatedLandlordNotification::class, fn ($notification, array $channels): bool => in_array('mail', $channels, true) && in_array('database', $channels, true));

    Notification::assertSentTo($client, ReservationCreatedClientNotification::class, fn ($notification, array $channels): bool => in_array('mail', $channels, true) && in_array('database', $channels, true));

    Notification::assertSentToTimes($owner, ReservationCreatedLandlordNotification::class, 1);
    Notification::assertSentToTimes($client, ReservationCreatedClientNotification::class, 1);
});
