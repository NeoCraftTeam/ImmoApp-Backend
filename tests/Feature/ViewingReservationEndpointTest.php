<?php

declare(strict_types=1);

use App\Enums\ReservationStatus;
use App\Models\Ad;
use App\Models\TentativeReservation;
use App\Models\UnlockedAd;
use App\Models\User;
use App\Notifications\ReservationConfirmedClientNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function reservationAd(User $owner): Ad
{
    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
        $ad = Ad::factory()->create(['user_id' => $owner->id]);
    });

    return $ad;
}

function unlockAdFor(Ad $ad, User $client): void
{
    UnlockedAd::factory()->create([
        'ad_id' => $ad->id,
        'user_id' => $client->id,
    ]);
}

function validSlotPayload(?string $date = null): array
{
    return [
        'slot_date' => $date ?? now()->addDays(3)->toDateString(),
        'slot_starts_at' => '10:00',
        'slot_ends_at' => '10:30',
    ];
}

// ===========================================================================
// STORE — POST /api/v1/ads/{ad}/reservations
// ===========================================================================

// TC-VR-01 — Unauthenticated → 401
it('returns 401 when unauthenticated user tries to create a reservation', function (): void {
    $owner = User::factory()->create();
    $ad = reservationAd($owner);

    $this->postJson("/api/v1/ads/{$ad->id}/reservations", validSlotPayload())
        ->assertUnauthorized();
});

// TC-VR-02 — Client without unlock → 403
it('returns 403 when client has not unlocked the ad', function (): void {
    $owner = User::factory()->create();
    $ad = reservationAd($owner);
    $client = User::factory()->create();

    $this->actingAs($client)
        ->postJson("/api/v1/ads/{$ad->id}/reservations", validSlotPayload())
        ->assertForbidden();
});

// TC-VR-03 — Owner cannot self-reserve their own ad (SelfReservationException → 403)
it('returns 403 when the ad owner tries to reserve their own property', function (): void {
    $owner = User::factory()->create();
    $ad = reservationAd($owner);

    $this->actingAs($owner)
        ->postJson("/api/v1/ads/{$ad->id}/reservations", validSlotPayload())
        ->assertForbidden()
        ->assertJsonPath('error.code', 'SELF_RESERVATION_NOT_ALLOWED');
});

// TC-VR-04 — Unlocked client passes the auth gate (403 vs 410 distinction)
// Without a configured viewing schedule the slot is unavailable (410),
// but the request is NOT rejected at the unlock/auth layer (which would be 403).
it('passes the unlock gate and reaches slot validation when client has unlocked the ad', function (): void {
    $owner = User::factory()->create();
    $ad = reservationAd($owner);
    $client = User::factory()->create();
    unlockAdFor($ad, $client);

    $response = $this->actingAs($client)
        ->postJson("/api/v1/ads/{$ad->id}/reservations", validSlotPayload());

    // 403 would mean the unlock gate blocked the request.
    // 410 means it passed the gate and reached slot validation (no schedule configured).
    $response->assertStatus(410);
});

// TC-VR-05 — Missing slot_date → 422
it('returns 422 when slot_date is missing', function (): void {
    $owner = User::factory()->create();
    $ad = reservationAd($owner);
    $client = User::factory()->create();
    unlockAdFor($ad, $client);

    $this->actingAs($client)
        ->postJson("/api/v1/ads/{$ad->id}/reservations", [
            'slot_starts_at' => '10:00',
            'slot_ends_at' => '10:30',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['slot_date']);
});

// ===========================================================================
// CONFIRM — POST /api/v1/reservations/{reservation}/confirm
// ===========================================================================

// TC-VR-06 — Unauthenticated → 401
it('returns 401 when unauthenticated user tries to confirm a reservation', function (): void {
    $owner = User::factory()->create();
    $ad = reservationAd($owner);
    $reservation = TentativeReservation::factory()->pending()->create(['ad_id' => $ad->id]);

    $this->postJson("/api/v1/reservations/{$reservation->id}/confirm")
        ->assertUnauthorized();
});

// TC-VR-07 — Non-landlord (random user) → 403
it('returns 403 when a non-landlord tries to confirm a reservation', function (): void {
    $owner = User::factory()->create();
    $ad = reservationAd($owner);
    $client = User::factory()->create();
    $reservation = TentativeReservation::factory()->pending()->create([
        'ad_id' => $ad->id,
        'client_id' => $client->id,
    ]);

    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->postJson("/api/v1/reservations/{$reservation->id}/confirm")
        ->assertForbidden();
});

// TC-VR-08 — Already confirmed → 422
it('returns 422 when landlord tries to confirm an already confirmed reservation', function (): void {
    $owner = User::factory()->create();
    $ad = reservationAd($owner);
    $client = User::factory()->create();
    $reservation = TentativeReservation::factory()->confirmed()->create([
        'ad_id' => $ad->id,
        'client_id' => $client->id,
    ]);

    $this->actingAs($owner)
        ->postJson("/api/v1/reservations/{$reservation->id}/confirm")
        ->assertUnprocessable();
});

// TC-VR-09 — Landlord confirms pending → 200, notification sent
it('confirms a pending reservation and notifies the client', function (): void {
    Notification::fake();
    $owner = User::factory()->create();
    $ad = reservationAd($owner);
    $client = User::factory()->create();
    $reservation = TentativeReservation::factory()->pending()->create([
        'ad_id' => $ad->id,
        'client_id' => $client->id,
    ]);

    $response = $this->actingAs($owner)
        ->postJson("/api/v1/reservations/{$reservation->id}/confirm");

    $response->assertOk()
        ->assertJsonPath('data.status', ReservationStatus::Confirmed->value);

    $this->assertDatabaseHas('tentative_reservations', [
        'id' => $reservation->id,
        'status' => ReservationStatus::Confirmed->value,
    ]);

    Notification::assertSentTo($client, ReservationConfirmedClientNotification::class);
});

// ===========================================================================
// CANCEL — DELETE /api/v1/reservations/{reservation}
// ===========================================================================

// TC-VR-10 — Unauthenticated → 401
it('returns 401 when unauthenticated user tries to cancel a reservation', function (): void {
    $owner = User::factory()->create();
    $ad = reservationAd($owner);
    $reservation = TentativeReservation::factory()->pending()->create(['ad_id' => $ad->id]);

    $this->deleteJson("/api/v1/reservations/{$reservation->id}")
        ->assertUnauthorized();
});

// TC-VR-11 — Client cancels their own reservation → 200
it('allows the client to cancel their own reservation', function (): void {
    Notification::fake();
    $owner = User::factory()->create();
    $ad = reservationAd($owner);
    $client = User::factory()->create();
    $reservation = TentativeReservation::factory()->pending()->create([
        'ad_id' => $ad->id,
        'client_id' => $client->id,
    ]);

    $response = $this->actingAs($client)
        ->deleteJson("/api/v1/reservations/{$reservation->id}");

    $response->assertOk()
        ->assertJsonPath('data.status', ReservationStatus::Cancelled->value);
});

// TC-VR-12 — Landlord cancels reservation on their ad → 200
it('allows the landlord to cancel a reservation on their ad', function (): void {
    Notification::fake();
    $owner = User::factory()->create();
    $ad = reservationAd($owner);
    $client = User::factory()->create();
    $reservation = TentativeReservation::factory()->pending()->create([
        'ad_id' => $ad->id,
        'client_id' => $client->id,
    ]);

    $response = $this->actingAs($owner)
        ->deleteJson("/api/v1/reservations/{$reservation->id}");

    $response->assertOk()
        ->assertJsonPath('data.status', ReservationStatus::Cancelled->value);
});

// TC-VR-13 — Stranger cannot cancel another user's reservation → 403
it('returns 403 when a stranger tries to cancel someone elses reservation', function (): void {
    $owner = User::factory()->create();
    $ad = reservationAd($owner);
    $client = User::factory()->create();
    $reservation = TentativeReservation::factory()->pending()->create([
        'ad_id' => $ad->id,
        'client_id' => $client->id,
    ]);

    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->deleteJson("/api/v1/reservations/{$reservation->id}")
        ->assertForbidden();
});

// ===========================================================================
// MY RESERVATIONS — GET /api/v1/my/reservations
// ===========================================================================

// TC-VR-14 — Unauthenticated → 401
it('returns 401 when unauthenticated user requests their reservation list', function (): void {
    $this->getJson('/api/v1/my/reservations')->assertUnauthorized();
});

// TC-VR-15 — Returns only the authenticated user's reservations
it('returns only the authenticated client reservations and not others', function (): void {
    $owner = User::factory()->create();
    $clientA = User::factory()->create();
    $clientB = User::factory()->create();

    $adA = reservationAd($owner);
    $adB = reservationAd($owner);

    TentativeReservation::factory()->pending()->create(['ad_id' => $adA->id, 'client_id' => $clientA->id, 'slot_starts_at' => '09:00:00', 'slot_ends_at' => '09:30:00']);
    TentativeReservation::factory()->pending()->create(['ad_id' => $adA->id, 'client_id' => $clientA->id, 'slot_starts_at' => '10:00:00', 'slot_ends_at' => '10:30:00']);
    TentativeReservation::factory()->pending()->create(['ad_id' => $adB->id, 'client_id' => $clientB->id, 'slot_starts_at' => '11:00:00', 'slot_ends_at' => '11:30:00']);

    $response = $this->actingAs($clientA)->getJson('/api/v1/my/reservations');

    $response->assertOk();
    $content = $response->getContent();
    expect($content)->not->toContain((string) $clientB->id);
});
