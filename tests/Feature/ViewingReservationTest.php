<?php

declare(strict_types=1);

use App\Enums\ReservationStatus;
use App\Exceptions\Viewing\SelfReservationException;
use App\Exceptions\Viewing\SlotAlreadyReservedException;
use App\Exceptions\Viewing\SlotNotAvailableException;
use App\Models\Ad;
use App\Models\TentativeReservation;
use App\Models\UnlockedAd;
use App\Models\User;
use App\Services\Contracts\ReservationServiceInterface;
use App\Services\Contracts\ViewingScheduleServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Shared helpers
// ---------------------------------------------------------------------------

/**
 * Create an Ad via MeiliSearch-safe wrapper.
 *
 * @param  array<string, mixed>  $attributes
 */
function makeAd(User $owner, array $attributes = []): Ad
{
    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $owner, $attributes): void {
        $ad = Ad::factory()->create(array_merge(['user_id' => $owner->id, 'status' => 'available'], $attributes));
    });

    return $ad;
}

// ===========================================================================
// TC-RES-01 — GET /api/v1/ads/{ad}/slots (public)
// ===========================================================================

it('returns available slots for an ad without authentication', function (): void {
    $owner = User::factory()->create();
    $ad = makeAd($owner);

    $this->mock(ViewingScheduleServiceInterface::class)
        ->shouldReceive('getBookableSlotsForRange')
        ->once()
        ->andReturn([
            now()->addDay()->toDateString() => [
                ['start_time' => '10:00', 'end_time' => '10:30'],
                ['start_time' => '10:30', 'end_time' => '11:00'],
            ],
        ])
        ->shouldReceive('getSlotDuration')
        ->once()
        ->andReturn(30);

    $this->getJson("/api/v1/ads/{$ad->id}/slots")
        ->assertOk()
        ->assertJsonPath('data.ad_id', $ad->id)
        ->assertJsonPath('data.slot_duration_minutes', 30)
        ->assertJsonStructure(['data' => ['ad_id', 'slot_duration_minutes', 'slots_by_date']]);
});

// ===========================================================================
// TC-RES-02 — Slots: an active reservation marks the slot unavailable
// ===========================================================================

it('marks an already-reserved slot as unavailable in the slots response', function (): void {
    $owner = User::factory()->create();
    $client = User::factory()->create();
    $ad = makeAd($owner);
    $tomorrow = now()->addDay()->toDateString();

    TentativeReservation::factory()->create([
        'ad_id' => $ad->id,
        'client_id' => $client->id,
        'slot_date' => $tomorrow,
        'slot_starts_at' => '10:00:00',
        'slot_ends_at' => '10:30:00',
        'status' => ReservationStatus::Pending,
    ]);

    $this->mock(ViewingScheduleServiceInterface::class)
        ->shouldReceive('getBookableSlotsForRange')
        ->once()
        ->andReturn([$tomorrow => [['start_time' => '10:00', 'end_time' => '10:30']]])
        ->shouldReceive('getSlotDuration')
        ->once()
        ->andReturn(30);

    $response = $this->getJson("/api/v1/ads/{$ad->id}/slots?from={$tomorrow}&to={$tomorrow}");

    $response->assertOk();
    $slots = $response->json("data.slots_by_date.{$tomorrow}");
    expect($slots[0]['is_available'])->toBeFalse();
});

// ===========================================================================
// TC-RES-03 — POST reservation: unauthenticated → 401
// ===========================================================================

it('rejects an unauthenticated reservation request with 401', function (): void {
    $owner = User::factory()->create();
    $ad = makeAd($owner);

    $this->postJson("/api/v1/ads/{$ad->id}/reservations", [
        'slot_date' => now()->addDay()->toDateString(),
        'slot_starts_at' => '10:00',
        'slot_ends_at' => '10:30',
    ])->assertUnauthorized();
});

// ===========================================================================
// TC-RES-04 — POST reservation: missing slot_date → 422
// ===========================================================================

it('validates that slot_date is required', function (): void {
    $owner = User::factory()->create();
    $client = User::factory()->create();
    $ad = makeAd($owner);
    Sanctum::actingAs($client);

    $this->postJson("/api/v1/ads/{$ad->id}/reservations", [
        'slot_starts_at' => '10:00',
        'slot_ends_at' => '10:30',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['slot_date']);
});

// ===========================================================================
// TC-RES-05 — POST reservation: past date → 422
// ===========================================================================

it('rejects reservations for a date in the past', function (): void {
    $owner = User::factory()->create();
    $client = User::factory()->create();
    $ad = makeAd($owner);
    Sanctum::actingAs($client);

    $this->postJson("/api/v1/ads/{$ad->id}/reservations", [
        'slot_date' => now()->subDay()->toDateString(),
        'slot_starts_at' => '10:00',
        'slot_ends_at' => '10:30',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['slot_date']);
});

// ===========================================================================
// TC-RES-06 — POST reservation: slot_ends_at <= slot_starts_at → 422
// ===========================================================================

it('rejects a reservation where end time is not after start time', function (): void {
    $owner = User::factory()->create();
    $client = User::factory()->create();
    $ad = makeAd($owner);
    Sanctum::actingAs($client);

    $this->postJson("/api/v1/ads/{$ad->id}/reservations", [
        'slot_date' => now()->addDay()->toDateString(),
        'slot_starts_at' => '11:00',
        'slot_ends_at' => '10:00',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['slot_ends_at']);
});

// ===========================================================================
// TC-RES-07 — POST reservation: client_message > 500 chars → 422
// ===========================================================================

it('rejects a client_message exceeding 500 characters', function (): void {
    $owner = User::factory()->create();
    $client = User::factory()->create();
    $ad = makeAd($owner);
    Sanctum::actingAs($client);

    $this->postJson("/api/v1/ads/{$ad->id}/reservations", [
        'slot_date' => now()->addDay()->toDateString(),
        'slot_starts_at' => '10:00',
        'slot_ends_at' => '10:30',
        'client_message' => str_repeat('x', 501),
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['client_message']);
});

// ===========================================================================
// TC-RES-08 — POST reservation: ad not unlocked → 403
// ===========================================================================

it('returns 403 when the client has not unlocked the ad', function (): void {
    $owner = User::factory()->create();
    $client = User::factory()->create();
    $ad = makeAd($owner);
    // No UnlockedAd record created for $client

    Sanctum::actingAs($client);

    $this->postJson("/api/v1/ads/{$ad->id}/reservations", [
        'slot_date' => now()->addDay()->toDateString(),
        'slot_starts_at' => '10:00',
        'slot_ends_at' => '10:30',
    ])->assertForbidden();
});

// ===========================================================================
// TC-RES-09 — POST reservation: happy path → 201
// ===========================================================================

it('creates a tentative reservation successfully and returns 201', function (): void {
    $owner = User::factory()->create();
    $client = User::factory()->create();
    $ad = makeAd($owner);
    UnlockedAd::factory()->create(['user_id' => $client->id, 'ad_id' => $ad->id, 'payment_id' => null]);

    $reservation = TentativeReservation::factory()->create([
        'ad_id' => $ad->id,
        'client_id' => $client->id,
    ]);

    $this->mock(ReservationServiceInterface::class)
        ->shouldReceive('reserve')
        ->once()
        ->andReturn($reservation->load('ad'));

    Sanctum::actingAs($client);

    $this->postJson("/api/v1/ads/{$ad->id}/reservations", [
        'slot_date' => now()->addDay()->toDateString(),
        'slot_starts_at' => '10:00',
        'slot_ends_at' => '10:30',
        'client_message' => 'Je serai disponible.',
    ])->assertCreated()
        ->assertJsonPath('data.id', $reservation->id)
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('message', 'Votre réservation provisoire a bien été enregistrée.');
});

// ===========================================================================
// TC-RES-09 — POST reservation: owner reserves own ad → 403
// ===========================================================================

it('prevents a landlord from reserving their own property', function (): void {
    $owner = User::factory()->create();
    $ad = makeAd($owner);

    $this->mock(ReservationServiceInterface::class)
        ->shouldReceive('reserve')
        ->once()
        ->andThrow(new SelfReservationException);

    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/ads/{$ad->id}/reservations", [
        'slot_date' => now()->addDay()->toDateString(),
        'slot_starts_at' => '10:00',
        'slot_ends_at' => '10:30',
    ])->assertForbidden()
        ->assertJsonPath('error.code', 'SELF_RESERVATION_NOT_ALLOWED');
});

// ===========================================================================
// TC-RES-10 — POST reservation: slot outside schedule → 410
// ===========================================================================

it('returns 410 Gone when the slot is not offered by the availability schedule', function (): void {
    $owner = User::factory()->create();
    $client = User::factory()->create();
    $ad = makeAd($owner);
    UnlockedAd::factory()->create(['user_id' => $client->id, 'ad_id' => $ad->id, 'payment_id' => null]);

    $this->mock(ReservationServiceInterface::class)
        ->shouldReceive('reserve')
        ->once()
        ->andThrow(new SlotNotAvailableException);

    Sanctum::actingAs($client);

    $this->postJson("/api/v1/ads/{$ad->id}/reservations", [
        'slot_date' => now()->addDay()->toDateString(),
        'slot_starts_at' => '23:00',
        'slot_ends_at' => '23:30',
    ])->assertStatus(410)
        ->assertJsonPath('error.code', 'SLOT_NOT_AVAILABLE');
});

// ===========================================================================
// TC-RES-11 — POST reservation: concurrent booking → 409
// ===========================================================================

it('returns 409 Conflict when the slot is taken by a concurrent booking', function (): void {
    $owner = User::factory()->create();
    $client = User::factory()->create();
    $ad = makeAd($owner);
    UnlockedAd::factory()->create(['user_id' => $client->id, 'ad_id' => $ad->id, 'payment_id' => null]);

    $this->mock(ReservationServiceInterface::class)
        ->shouldReceive('reserve')
        ->once()
        ->andThrow(new SlotAlreadyReservedException);

    Sanctum::actingAs($client);

    $this->postJson("/api/v1/ads/{$ad->id}/reservations", [
        'slot_date' => now()->addDay()->toDateString(),
        'slot_starts_at' => '10:00',
        'slot_ends_at' => '10:30',
    ])->assertConflict()
        ->assertJsonPath('error.code', 'SLOT_ALREADY_RESERVED');
});

// ===========================================================================
// TC-RES-12 — GET /my/reservations: unauthenticated → 401
// ===========================================================================

it('requires authentication to list personal reservations', function (): void {
    $this->getJson('/api/v1/my/reservations')
        ->assertUnauthorized();
});

// ===========================================================================
// TC-RES-13 — GET /my/reservations: returns only the client's own records
// ===========================================================================

it('returns only the authenticated client\'s reservations', function (): void {
    $owner = User::factory()->create();
    $clientA = User::factory()->create();
    $clientB = User::factory()->create();
    $ad = makeAd($owner);

    TentativeReservation::factory()->create(['ad_id' => $ad->id, 'client_id' => $clientA->id]);
    TentativeReservation::factory()->create(['ad_id' => $ad->id, 'client_id' => $clientA->id, 'slot_starts_at' => '11:00:00', 'slot_ends_at' => '11:30:00']);
    TentativeReservation::factory()->create(['ad_id' => $ad->id, 'client_id' => $clientB->id, 'slot_starts_at' => '12:00:00', 'slot_ends_at' => '12:30:00']);

    Sanctum::actingAs($clientA);

    $this->getJson('/api/v1/my/reservations')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

// ===========================================================================
// TC-RES-14 — GET /my/reservations: status filter
// ===========================================================================

it('filters personal reservations by status query parameter', function (): void {
    $owner = User::factory()->create();
    $client = User::factory()->create();
    $ad = makeAd($owner);

    TentativeReservation::factory()->pending()->create(['ad_id' => $ad->id, 'client_id' => $client->id]);
    TentativeReservation::factory()->confirmed()->create(['ad_id' => $ad->id, 'client_id' => $client->id, 'slot_starts_at' => '11:00:00', 'slot_ends_at' => '11:30:00']);
    TentativeReservation::factory()->cancelled()->create(['ad_id' => $ad->id, 'client_id' => $client->id, 'slot_starts_at' => '12:00:00', 'slot_ends_at' => '12:30:00']);

    Sanctum::actingAs($client);

    $this->getJson('/api/v1/my/reservations?status=pending')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

// ===========================================================================
// TC-RES-15 — DELETE reservation: client cancels own → 200
// ===========================================================================

it('allows a client to cancel their own pending reservation', function (): void {
    $owner = User::factory()->create();
    $client = User::factory()->create();
    $ad = makeAd($owner);
    $reservation = TentativeReservation::factory()->pending()->create([
        'ad_id' => $ad->id,
        'client_id' => $client->id,
    ]);

    $cancelledReservation = $reservation->fresh();
    $cancelledReservation->status = ReservationStatus::Cancelled;

    $this->mock(ReservationServiceInterface::class)
        ->shouldReceive('cancel')
        ->once()
        ->andReturn($cancelledReservation->load('ad'));

    Sanctum::actingAs($client);

    $this->deleteJson("/api/v1/reservations/{$reservation->id}")
        ->assertOk()
        ->assertJsonPath('message', 'Réservation provisoire annulée.');
});

// ===========================================================================
// TC-RES-16 — DELETE reservation: landlord cancels reservation on own ad
// ===========================================================================

it('allows a landlord to cancel a reservation on their own property', function (): void {
    $owner = User::factory()->create();
    $client = User::factory()->create();
    $ad = makeAd($owner);
    $reservation = TentativeReservation::factory()->pending()->create([
        'ad_id' => $ad->id,
        'client_id' => $client->id,
    ]);

    $cancelledReservation = $reservation->fresh();
    $cancelledReservation->status = ReservationStatus::Cancelled;

    $this->mock(ReservationServiceInterface::class)
        ->shouldReceive('cancel')
        ->once()
        ->andReturn($cancelledReservation->load('ad'));

    Sanctum::actingAs($owner);

    $this->deleteJson("/api/v1/reservations/{$reservation->id}")
        ->assertOk();
});

// ===========================================================================
// TC-RES-17 — DELETE reservation: unrelated user is forbidden
// ===========================================================================

it('prevents an unrelated user from cancelling another person\'s reservation', function (): void {
    $owner = User::factory()->create();
    $client = User::factory()->create();
    $stranger = User::factory()->create();
    $ad = makeAd($owner);
    $reservation = TentativeReservation::factory()->pending()->create([
        'ad_id' => $ad->id,
        'client_id' => $client->id,
    ]);

    Sanctum::actingAs($stranger);

    $this->deleteJson("/api/v1/reservations/{$reservation->id}")
        ->assertForbidden();
});

// ===========================================================================
// TC-RES-18 — DELETE reservation: unauthenticated → 401
// ===========================================================================

it('requires authentication to cancel a reservation', function (): void {
    $owner = User::factory()->create();
    $client = User::factory()->create();
    $ad = makeAd($owner);
    $reservation = TentativeReservation::factory()->pending()->create([
        'ad_id' => $ad->id,
        'client_id' => $client->id,
    ]);

    $this->deleteJson("/api/v1/reservations/{$reservation->id}")
        ->assertUnauthorized();
});
