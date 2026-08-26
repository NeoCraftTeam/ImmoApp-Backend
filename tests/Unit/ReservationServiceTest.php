<?php

declare(strict_types=1);

use App\Contracts\ViewingScheduleServiceInterface;
use App\Enums\CancelledBy;
use App\Enums\ReservationStatus;
use App\Exceptions\Viewing\ScheduleHasActiveReservationsException;
use App\Models\Ad;
use App\Models\TentativeReservation;
use App\Models\User;
use App\Models\Zap\Schedule;
use App\Services\Rental\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Notification::fake();
    Event::fake();
});

// ---------------------------------------------------------------------------
// Helper
// ---------------------------------------------------------------------------

function svcAd(User $owner): Ad
{
    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
        $ad = Ad::factory()->create(['user_id' => $owner->id]);
    });

    return $ad;
}

/**
 * Insert an availability schedule the owner manages — distinct from the
 * per-reservation appointment schedule created by the factory.
 *
 * @param  array<string, mixed>  $extra
 */
function svcAvailabilitySchedule(Ad $ad, array $extra = []): Schedule
{
    $id = fake()->uuid();
    DB::table('schedules')->insert(array_merge([
        'id' => $id,
        'schedulable_type' => Ad::class,
        'schedulable_id' => $ad->id,
        'name' => 'Disponibilités',
        'start_date' => now()->toDateString(),
        'is_active' => true,
        'is_recurring' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ], $extra));

    return Schedule::query()->findOrFail($id);
}

// ===========================================================================
// TC-SVC-01 — cancel(): sets CancelledBy::Client when client is the actor
// ===========================================================================

it('sets cancelled_by Client when the client cancels', function (): void {
    $viewingScheduleService = Mockery::mock(ViewingScheduleServiceInterface::class);
    $viewingScheduleService->shouldReceive('releaseSlot')->once();

    $service = new ReservationService($viewingScheduleService);

    $owner = User::factory()->create();
    $client = User::factory()->create();
    $ad = svcAd($owner);

    $reservation = TentativeReservation::factory()->pending()->create([
        'ad_id' => $ad->id,
        'client_id' => $client->id,
    ]);

    $result = $service->cancel($reservation, $client);

    expect($result->cancelled_by)->toBe(CancelledBy::Client)
        ->and($result->status)->toBe(ReservationStatus::Cancelled);
});

// ===========================================================================
// TC-SVC-02 — cancel(): sets CancelledBy::Landlord when landlord cancels
// ===========================================================================

it('sets cancelled_by Landlord when the landlord cancels', function (): void {
    $viewingScheduleService = Mockery::mock(ViewingScheduleServiceInterface::class);
    $viewingScheduleService->shouldReceive('releaseSlot')->once();

    $service = new ReservationService($viewingScheduleService);

    $owner = User::factory()->create();
    $client = User::factory()->create();
    $ad = svcAd($owner);

    $reservation = TentativeReservation::factory()->pending()->create([
        'ad_id' => $ad->id,
        'client_id' => $client->id,
    ]);

    $result = $service->cancel($reservation, $owner, 'Indisponible ce jour.');

    expect($result->cancelled_by)->toBe(CancelledBy::Landlord)
        ->and($result->cancellation_reason)->toBe('Indisponible ce jour.');
});

// ===========================================================================
// TC-SVC-03 — cancel(): releases the Zap appointment schedule
// ===========================================================================

it('releases the appointment schedule when cancelling a reservation', function (): void {
    $viewingScheduleService = Mockery::mock(ViewingScheduleServiceInterface::class);
    $viewingScheduleService->shouldReceive('releaseSlot')
        ->once()
        ->withArgs(fn ($arg): bool => $arg instanceof Schedule);

    $service = new ReservationService($viewingScheduleService);

    $owner = User::factory()->create();
    $client = User::factory()->create();
    $ad = svcAd($owner);

    $reservation = TentativeReservation::factory()->pending()->create([
        'ad_id' => $ad->id,
        'client_id' => $client->id,
    ]);

    $service->cancel($reservation, $client);
});

// ===========================================================================
// TC-SVC-04 — expireStale(): marks stale pending reservations as Expired
// ===========================================================================

it('marks stale pending reservations as Expired', function (): void {
    $viewingScheduleService = Mockery::mock(ViewingScheduleServiceInterface::class);
    $viewingScheduleService->shouldReceive('releaseSlot')->times(2);

    $service = new ReservationService($viewingScheduleService);

    $owner = User::factory()->create();
    $client = User::factory()->create();
    $clientOther = User::factory()->create();
    $ad = svcAd($owner);

    TentativeReservation::factory()->stale()->create(['ad_id' => $ad->id, 'client_id' => $client->id]);
    TentativeReservation::factory()->stale()->create(['ad_id' => $ad->id, 'client_id' => $clientOther->id, 'slot_starts_at' => '11:00:00', 'slot_ends_at' => '11:30:00']);

    $count = $service->expireStale();

    expect($count)->toBe(2);

    $this->assertDatabaseCount(
        'tentative_reservations',
        TentativeReservation::withTrashed()->count()
    );

    expect(
        TentativeReservation::query()
            ->where('status', ReservationStatus::Expired)
            ->count()
    )->toBe(2);
});

// ===========================================================================
// TC-SVC-05 — expireStale(): leaves fresh pending reservations untouched
// ===========================================================================

it('does not expire pending reservations whose TTL has not elapsed', function (): void {
    $viewingScheduleService = Mockery::mock(ViewingScheduleServiceInterface::class);
    $viewingScheduleService->shouldReceive('releaseSlot')->never();

    $service = new ReservationService($viewingScheduleService);

    $owner = User::factory()->create();
    $client = User::factory()->create();
    $ad = svcAd($owner);

    TentativeReservation::factory()->pending()->create(['ad_id' => $ad->id, 'client_id' => $client->id]);

    $count = $service->expireStale();

    expect($count)->toBe(0);
    expect(
        TentativeReservation::query()
            ->where('status', ReservationStatus::Pending)
            ->count()
    )->toBe(1);
});

// ===========================================================================
// TC-SVC-06 — expireStale(): returns zero when nothing is stale
// ===========================================================================

it('returns 0 from expireStale when there are no stale reservations', function (): void {
    $viewingScheduleService = Mockery::mock(ViewingScheduleServiceInterface::class);
    $service = new ReservationService($viewingScheduleService);

    $count = $service->expireStale();

    expect($count)->toBe(0);
});

// ===========================================================================
// TC-SVC-07 — assertNoActiveReservationsForSchedule(): throws on active record
// ===========================================================================

it('throws ScheduleHasActiveReservationsException when a schedule has active reservations', function (): void {
    $viewingScheduleService = Mockery::mock(ViewingScheduleServiceInterface::class);
    $service = new ReservationService($viewingScheduleService);

    $owner = User::factory()->create();
    $client = User::factory()->create();
    $ad = svcAd($owner);

    $reservation = TentativeReservation::factory()->pending()->create([
        'ad_id' => $ad->id,
        'client_id' => $client->id,
    ]);

    $schedule = $reservation->appointmentSchedule;

    expect(fn () => $service->assertNoActiveReservationsForSchedule($schedule))
        ->toThrow(ScheduleHasActiveReservationsException::class);
});

// ===========================================================================
// TC-SVC-08 — assertNoActiveReservationsForSchedule(): passes with no active
// ===========================================================================

it('does not throw when no active reservations exist for a schedule', function (): void {
    $viewingScheduleService = Mockery::mock(ViewingScheduleServiceInterface::class);
    $service = new ReservationService($viewingScheduleService);

    $owner = User::factory()->create();
    $client = User::factory()->create();
    $ad = svcAd($owner);

    $reservation = TentativeReservation::factory()->cancelled()->create([
        'ad_id' => $ad->id,
        'client_id' => $client->id,
    ]);

    $schedule = $reservation->appointmentSchedule;

    // Must not throw.
    $service->assertNoActiveReservationsForSchedule($schedule);
    expect(true)->toBeTrue();
});

// ===========================================================================
// TC-SVC-09 — guard detects reservations covered by an AVAILABILITY schedule
// (reservations never carry the availability schedule id — only their own
//  appointment schedule id — so the guard must match on ad + coverage window)
// ===========================================================================

it('throws when an availability schedule covers an active reservation', function (): void {
    $viewingScheduleService = Mockery::mock(ViewingScheduleServiceInterface::class);
    $service = new ReservationService($viewingScheduleService);

    $owner = User::factory()->create();
    $client = User::factory()->create();
    $ad = svcAd($owner);

    $availability = svcAvailabilitySchedule($ad, [
        'start_date' => now()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
    ]);

    // Active reservation whose slot falls inside the availability window. Its
    // appointment_schedule_id points at its OWN appointment schedule, never at
    // $availability.
    TentativeReservation::factory()->pending()->create([
        'ad_id' => $ad->id,
        'client_id' => $client->id,
        'slot_date' => now()->addDay()->toDateString(),
    ]);

    expect(fn () => $service->assertNoActiveReservationsForSchedule($availability))
        ->toThrow(ScheduleHasActiveReservationsException::class);
});

// ===========================================================================
// TC-SVC-10 — guard ignores reservations whose slot is outside the window
// ===========================================================================

it('does not throw when the reservation slot falls outside the schedule window', function (): void {
    $viewingScheduleService = Mockery::mock(ViewingScheduleServiceInterface::class);
    $service = new ReservationService($viewingScheduleService);

    $owner = User::factory()->create();
    $client = User::factory()->create();
    $ad = svcAd($owner);

    // Window starts next week; the reservation below is for tomorrow.
    $availability = svcAvailabilitySchedule($ad, [
        'start_date' => now()->addWeek()->toDateString(),
        'end_date' => now()->addWeeks(2)->toDateString(),
    ]);

    TentativeReservation::factory()->pending()->create([
        'ad_id' => $ad->id,
        'client_id' => $client->id,
        'slot_date' => now()->addDay()->toDateString(),
    ]);

    $service->assertNoActiveReservationsForSchedule($availability);
    expect(true)->toBeTrue();
});
