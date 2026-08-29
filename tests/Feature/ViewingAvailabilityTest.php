<?php

declare(strict_types=1);

use App\Contracts\ReservationServiceInterface;
use App\Contracts\ViewingScheduleServiceInterface;
use App\Events\Reservation\ReservationStatusChanged;
use App\Exceptions\Viewing\ScheduleHasActiveReservationsException;
use App\Models\Ad;
use App\Models\TentativeReservation;
use App\Models\User;
use App\Models\Zap\Schedule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Notification::fake();
});

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function availAd(User $owner): Ad
{
    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
        $ad = Ad::factory()->create(['user_id' => $owner->id, 'status' => 'available']);
    });

    return $ad;
}

function insertSchedule(Ad $ad, array $extra = []): Schedule
{
    $id = fake()->uuid();
    DB::table('schedules')->insert(array_merge([
        'id' => $id,
        'schedulable_type' => Ad::class,
        'schedulable_id' => $ad->id,
        'name' => 'Disponibilités',
        'start_date' => now()->addDay()->toDateString(),
        'is_active' => true,
        'is_recurring' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ], $extra));

    return Schedule::query()->findOrFail($id);
}

// ===========================================================================
// TC-AVA-01 — GET availability: owner sees their schedules
// ===========================================================================

it('returns the owner\'s availability schedules', function (): void {
    $owner = User::factory()->create();
    $ad = availAd($owner);
    insertSchedule($ad);

    Sanctum::actingAs($owner);

    $this->getJson("/api/v1/ads/{$ad->id}/availability")
        ->assertOk()
        ->assertJsonStructure(['data']);
});

// ===========================================================================
// TC-AVA-02 — GET availability: non-owner is forbidden
// ===========================================================================

it('denies access to availability list for a non-owner', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $ad = availAd($owner);

    Sanctum::actingAs($other);

    $this->getJson("/api/v1/ads/{$ad->id}/availability")
        ->assertForbidden();
});

// ===========================================================================
// TC-AVA-03 — GET availability: unauthenticated → 401
// ===========================================================================

it('requires authentication to list availability schedules', function (): void {
    $owner = User::factory()->create();
    $ad = availAd($owner);

    $this->getJson("/api/v1/ads/{$ad->id}/availability")
        ->assertUnauthorized();
});

// ===========================================================================
// TC-AVA-04 — POST availability: missing name → 422
// ===========================================================================

it('validates that name is required when creating an availability schedule', function (): void {
    $owner = User::factory()->create();
    $ad = availAd($owner);

    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/ads/{$ad->id}/availability", [
        'starts_on' => now()->addDay()->toDateString(),
        'periods' => [['starts_at' => '09:00', 'ends_at' => '12:00']],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

// ===========================================================================
// TC-AVA-05 — POST availability: past starts_on → 422
// ===========================================================================

it('rejects an availability schedule with a start date in the past', function (): void {
    $owner = User::factory()->create();
    $ad = availAd($owner);

    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/ads/{$ad->id}/availability", [
        'name' => 'Test',
        'starts_on' => now()->subDay()->toDateString(),
        'periods' => [['starts_at' => '09:00', 'ends_at' => '12:00']],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['starts_on']);
});

// ===========================================================================
// TC-AVA-06 — POST availability: slot_duration below minimum → 422
// ===========================================================================

it('rejects a slot_duration below the 15-minute minimum', function (): void {
    $owner = User::factory()->create();
    $ad = availAd($owner);

    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/ads/{$ad->id}/availability", [
        'name' => 'Test',
        'starts_on' => now()->addDay()->toDateString(),
        'periods' => [['starts_at' => '09:00', 'ends_at' => '12:00']],
        'slot_duration' => 5,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['slot_duration']);
});

// ===========================================================================
// TC-AVA-07 — POST availability: owner creates → 201
// ===========================================================================

it('allows an owner to create an availability schedule', function (): void {
    $owner = User::factory()->create();
    $ad = availAd($owner);
    $schedule = insertSchedule($ad);

    $this->mock(ViewingScheduleServiceInterface::class)
        ->shouldReceive('createAvailability')
        ->once()
        ->andReturn($schedule);

    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/ads/{$ad->id}/availability", [
        'name' => 'Visites le matin',
        'starts_on' => now()->addDay()->toDateString(),
        'periods' => [['starts_at' => '09:00', 'ends_at' => '12:00']],
        'slot_duration' => 30,
    ])->assertCreated()
        ->assertJsonStructure(['data', 'message']);
});

// ===========================================================================
// TC-AVA-08 — POST availability: non-owner → 403
// ===========================================================================

it('denies availability creation to a non-owner', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $ad = availAd($owner);

    Sanctum::actingAs($other);

    $this->postJson("/api/v1/ads/{$ad->id}/availability", [
        'name' => 'Hacked',
        'starts_on' => now()->addDay()->toDateString(),
        'periods' => [['starts_at' => '09:00', 'ends_at' => '12:00']],
    ])->assertForbidden();
});

// ===========================================================================
// TC-AVA-09 — PUT update: blocked when active reservations exist → 409
// ===========================================================================

it('blocks a schedule update when active reservations are attached', function (): void {
    $owner = User::factory()->create();
    $client = User::factory()->create();
    $ad = availAd($owner);
    $schedule = insertSchedule($ad);

    // Create an active reservation on this schedule.
    TentativeReservation::factory()->pending()->create([
        'ad_id' => $ad->id,
        'client_id' => $client->id,
        'appointment_schedule_id' => $schedule->id,
    ]);

    $activeReservations = TentativeReservation::query()->active()->get();

    $this->mock(ReservationServiceInterface::class)
        ->shouldReceive('assertNoActiveReservationsForSchedule')
        ->once()
        ->andThrow(new ScheduleHasActiveReservationsException($activeReservations));

    $this->mock(ViewingScheduleServiceInterface::class);

    Sanctum::actingAs($owner);

    $this->putJson("/api/v1/ads/{$ad->id}/availability/{$schedule->id}", [
        'name' => 'Updated',
        'periods' => [['starts_at' => '10:00', 'ends_at' => '13:00']],
    ])->assertConflict()
        ->assertJsonPath('code', 'SCHEDULE_HAS_ACTIVE_RESERVATIONS');
});

// ===========================================================================
// TC-AVA-10 — PUT update: owner updates a schedule with no active reservations
// ===========================================================================

it('allows an owner to update a schedule that has no active reservations', function (): void {
    $owner = User::factory()->create();
    $ad = availAd($owner);
    $schedule = insertSchedule($ad);

    $this->mock(ReservationServiceInterface::class)
        ->shouldReceive('assertNoActiveReservationsForSchedule')
        ->once();

    $this->mock(ViewingScheduleServiceInterface::class)
        ->shouldReceive('updateAvailability')
        ->once()
        ->andReturn($schedule);

    Sanctum::actingAs($owner);

    $this->putJson("/api/v1/ads/{$ad->id}/availability/{$schedule->id}", [
        'name' => 'Updated Name',
        'periods' => [['starts_at' => '10:00', 'ends_at' => '13:00']],
    ])->assertOk()
        ->assertJsonPath('message', 'Planning de disponibilité mis à jour.');
});

// ===========================================================================
// TC-AVA-11 — PUT update: non-owner → 403
// ===========================================================================

it('denies schedule update to a non-owner', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $ad = availAd($owner);
    $schedule = insertSchedule($ad);

    Sanctum::actingAs($other);

    $this->putJson("/api/v1/ads/{$ad->id}/availability/{$schedule->id}", [
        'name' => 'Hacked',
    ])->assertForbidden();
});

// ===========================================================================
// TC-AVA-12 — DELETE schedule: cascades cancellation of active reservations
// ===========================================================================

it('cancels all active reservations when a schedule is deleted', function (): void {
    $owner = User::factory()->create();
    $client = User::factory()->create();
    $ad = availAd($owner);
    $schedule = insertSchedule($ad);

    $reservation = TentativeReservation::factory()->pending()->create([
        'ad_id' => $ad->id,
        'client_id' => $client->id,
        'appointment_schedule_id' => $schedule->id,
    ]);

    $this->mock(ReservationServiceInterface::class, function ($mock) use ($reservation): void {
        $mock->shouldReceive('activeReservationsCoveredBySchedule')
            ->once()
            ->andReturn(new Collection([$reservation]));
        $mock->shouldReceive('cancel')
            ->once()
            ->withAnyArgs()
            ->andReturn($reservation);
    });

    Sanctum::actingAs($owner);

    $this->deleteJson("/api/v1/ads/{$ad->id}/availability/{$schedule->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('schedules', ['id' => $schedule->id]);
});

// ===========================================================================
// TC-AVA-13 — DELETE schedule: non-owner → 403
// ===========================================================================

it('denies schedule deletion to a non-owner', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $ad = availAd($owner);
    $schedule = insertSchedule($ad);

    Sanctum::actingAs($other);

    $this->deleteJson("/api/v1/ads/{$ad->id}/availability/{$schedule->id}")
        ->assertForbidden();
});

// ===========================================================================
// TC-AVA-14 — GET calendar: owner gets slot+status overlay
// ===========================================================================

it('returns the slot calendar for the owner with reservation overlays', function (): void {
    $owner = User::factory()->create();
    $ad = availAd($owner);
    $tomorrow = now()->addDay()->toDateString();

    $this->mock(ViewingScheduleServiceInterface::class)
        ->shouldReceive('getBookableSlotsForRange')
        ->once()
        ->andReturn([$tomorrow => [['start_time' => '10:00', 'end_time' => '10:30']]]);

    Sanctum::actingAs($owner);

    $this->getJson("/api/v1/ads/{$ad->id}/availability/calendar?from={$tomorrow}&to={$tomorrow}")
        ->assertOk()
        ->assertJsonStructure(['data']);
});

// ===========================================================================
// TC-AVA-14b — GET calendar: client overlay must not trigger an N+1 query
// ===========================================================================

it('eager-loads reservation clients so the calendar overlay avoids N+1 queries', function (): void {
    $owner = User::factory()->create();
    $ad = availAd($owner);
    $day = now()->addDay()->toDateString();

    $times = ['09:00', '10:00', '11:00', '13:00'];

    foreach ($times as $time) {
        TentativeReservation::factory()->pending()->create([
            'ad_id' => $ad->id,
            'client_id' => User::factory()->create()->id,
            'slot_date' => $day,
            'slot_starts_at' => "{$time}:00",
            'slot_ends_at' => "{$time}:00",
        ]);
    }

    $this->mock(ViewingScheduleServiceInterface::class)
        ->shouldReceive('getBookableSlotsForRange')
        ->once()
        ->andReturn([
            $day => array_map(
                fn (string $time): array => ['start_time' => $time, 'end_time' => $time],
                $times,
            ),
        ]);

    Sanctum::actingAs($owner);

    DB::enableQueryLog();
    DB::flushQueryLog();

    $this->getJson("/api/v1/ads/{$ad->id}/availability/calendar?from={$day}&to={$day}")
        ->assertOk();

    // All four slots are overlaid with a reserved client. Without eager-loading
    // the `client` relation, each reservation fires its own
    // `select … from "users"` (classic N+1). The eager load collapses them
    // into a single batched query.
    $userQueries = collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => str_contains((string) $query['query'], 'from "users"'))
        ->count();

    expect($userQueries)->toBeLessThanOrEqual(1);
});

// ===========================================================================
// TC-AVA-15 — GET calendar: non-owner → 403
// ===========================================================================

it('denies calendar access to a non-owner', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $ad = availAd($owner);

    Sanctum::actingAs($other);

    $this->getJson("/api/v1/ads/{$ad->id}/availability/calendar")
        ->assertForbidden();
});

// ===========================================================================
// TC-AVA-16 — GET reservations: landlord lists reservations for their ad
// ===========================================================================

it('returns a paginated list of reservations for the landlord', function (): void {
    $owner = User::factory()->create();
    $clientA = User::factory()->create();
    $clientB = User::factory()->create();
    $ad = availAd($owner);

    TentativeReservation::factory()->pending()->create(['ad_id' => $ad->id, 'client_id' => $clientA->id]);
    TentativeReservation::factory()->confirmed()->create([
        'ad_id' => $ad->id,
        'client_id' => $clientB->id,
        'slot_starts_at' => '11:00:00',
        'slot_ends_at' => '11:30:00',
    ]);

    Sanctum::actingAs($owner);

    $this->getJson("/api/v1/ads/{$ad->id}/reservations")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

// ===========================================================================
// TC-AVA-17 — GET reservations: non-owner → 403
// ===========================================================================

it('denies reservation listing for a non-owner', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $ad = availAd($owner);

    Sanctum::actingAs($other);

    $this->getJson("/api/v1/ads/{$ad->id}/reservations")
        ->assertForbidden();
});

// ===========================================================================
// TC-AVA-18 — PUT update: 409 when a reservation falls in the schedule window
// (integration — no service mock; guards against the appointment/availability
//  schedule id-space mismatch that made the guard a silent no-op)
// ===========================================================================

it('blocks a schedule update when a reservation falls within its coverage window', function (): void {
    $owner = User::factory()->create();
    $client = User::factory()->create();
    $ad = availAd($owner);
    $schedule = insertSchedule($ad, [
        'start_date' => now()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
    ]);

    // Active reservation on the ad, inside the window. Its appointment_schedule_id
    // points at its own appointment schedule, never at $schedule.
    TentativeReservation::factory()->pending()->create([
        'ad_id' => $ad->id,
        'client_id' => $client->id,
        'slot_date' => now()->addDay()->toDateString(),
    ]);

    Sanctum::actingAs($owner);

    $this->putJson("/api/v1/ads/{$ad->id}/availability/{$schedule->id}", [
        'name' => 'Updated',
        'periods' => [['starts_at' => '10:00', 'ends_at' => '13:00']],
    ])->assertConflict()
        ->assertJsonPath('code', 'SCHEDULE_HAS_ACTIVE_RESERVATIONS');
});

// ===========================================================================
// TC-AVA-19 — DELETE schedule: cascades cancellation of covered reservations
// (integration — no service mock)
// ===========================================================================

it('cancels reservations covered by a deleted availability schedule', function (): void {
    Event::fake();

    $owner = User::factory()->create();
    $client = User::factory()->create();
    $ad = availAd($owner);
    $schedule = insertSchedule($ad, [
        'start_date' => now()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
    ]);

    $reservation = TentativeReservation::factory()->pending()->create([
        'ad_id' => $ad->id,
        'client_id' => $client->id,
        'slot_date' => now()->addDay()->toDateString(),
    ]);

    Sanctum::actingAs($owner);

    $this->deleteJson("/api/v1/ads/{$ad->id}/availability/{$schedule->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('schedules', ['id' => $schedule->id]);

    // cancel() fires the status-change event, then releaseSlot() deletes the
    // reservation's appointment schedule; the appointment_schedule_id FK cascade
    // then removes the reservation row. Net effect: the cancellation event was
    // dispatched and no active reservation for the ad survives.
    Event::assertDispatched(ReservationStatusChanged::class);
    expect(TentativeReservation::query()->where('ad_id', $ad->id)->active()->exists())
        ->toBeFalse();
});
