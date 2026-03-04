<?php

declare(strict_types=1);

use App\Enums\ReservationStatus;
use App\Models\Ad;
use App\Models\TentativeReservation;
use App\Models\User;
use App\Notifications\ReservationCancelledNotification;
use App\Notifications\ReservationConfirmedClientNotification;
use App\Notifications\ReservationCreatedClientNotification;
use App\Notifications\ReservationCreatedLandlordNotification;
use App\Notifications\ReservationExpiredNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Helper
// ---------------------------------------------------------------------------

function observerAd(User $owner): Ad
{
    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
        $ad = Ad::factory()->create(['user_id' => $owner->id]);
    });

    return $ad;
}

// ===========================================================================
// TC-OBS-01 — Created: landlord receives ReservationCreatedLandlordNotification
// ===========================================================================

it('sends ReservationCreatedLandlordNotification to the owner when a reservation is created', function (): void {
    Notification::fake();

    $owner = User::factory()->create();
    $client = User::factory()->create();
    $ad = observerAd($owner);

    TentativeReservation::factory()->create([
        'ad_id' => $ad->id,
        'client_id' => $client->id,
    ]);

    Notification::assertSentTo($owner, ReservationCreatedLandlordNotification::class);
});

// ===========================================================================
// TC-OBS-02 — Created: client receives ReservationCreatedClientNotification
// ===========================================================================

it('sends ReservationCreatedClientNotification to the client when a reservation is created', function (): void {
    Notification::fake();

    $owner = User::factory()->create();
    $client = User::factory()->create();
    $ad = observerAd($owner);

    TentativeReservation::factory()->create([
        'ad_id' => $ad->id,
        'client_id' => $client->id,
    ]);

    Notification::assertSentTo($client, ReservationCreatedClientNotification::class);
});

// ===========================================================================
// TC-OBS-03 — Confirmed: client receives ReservationConfirmedClientNotification
// ===========================================================================

it('sends ReservationConfirmedClientNotification to the client when a reservation is confirmed', function (): void {
    Notification::fake();

    $owner = User::factory()->create();
    $client = User::factory()->create();
    $ad = observerAd($owner);

    $reservation = TentativeReservation::factory()->pending()->create([
        'ad_id' => $ad->id,
        'client_id' => $client->id,
    ]);

    // Simulate the landlord confirming the reservation.
    $reservation->update(['status' => ReservationStatus::Confirmed]);

    Notification::assertSentTo($client, ReservationConfirmedClientNotification::class);
});

// ===========================================================================
// TC-OBS-04 — Cancelled: both parties receive ReservationCancelledNotification
// ===========================================================================

it('sends ReservationCancelledNotification to both owner and client when a reservation is cancelled', function (): void {
    Notification::fake();

    $owner = User::factory()->create();
    $client = User::factory()->create();
    $ad = observerAd($owner);

    $reservation = TentativeReservation::factory()->pending()->create([
        'ad_id' => $ad->id,
        'client_id' => $client->id,
    ]);

    $reservation->update(['status' => ReservationStatus::Cancelled]);

    Notification::assertSentTo($client, ReservationCancelledNotification::class);
    Notification::assertSentTo($owner, ReservationCancelledNotification::class);
});

// ===========================================================================
// TC-OBS-05 — Expired: client receives ReservationExpiredNotification
// ===========================================================================

it('sends ReservationExpiredNotification to the client when a reservation expires', function (): void {
    Notification::fake();

    $owner = User::factory()->create();
    $client = User::factory()->create();
    $ad = observerAd($owner);

    $reservation = TentativeReservation::factory()->pending()->create([
        'ad_id' => $ad->id,
        'client_id' => $client->id,
    ]);

    $reservation->update(['status' => ReservationStatus::Expired]);

    Notification::assertSentTo($client, ReservationExpiredNotification::class);
});

// ===========================================================================
// TC-OBS-06 — No extra notifications when an irrelevant field is updated
// ===========================================================================

it('does not dispatch confirmation notifications on unrelated field updates', function (): void {
    Notification::fake();

    $owner = User::factory()->create();
    $client = User::factory()->create();
    $ad = observerAd($owner);

    $reservation = TentativeReservation::factory()->pending()->create([
        'ad_id' => $ad->id,
        'client_id' => $client->id,
    ]);

    // Fake: only clear notifications sent by the 'created' event.
    Notification::fake();

    // Update a non-status field (landlord_notes).
    $reservation->update(['landlord_notes' => 'Prévoir visite le matin.']);

    Notification::assertNothingSent();
});
