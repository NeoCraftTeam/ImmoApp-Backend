<?php

declare(strict_types=1);

namespace App\Services\Rental;

use App\Contracts\ReservationServiceInterface;
use App\Contracts\ViewingScheduleServiceInterface;
use App\Enums\CancelledBy;
use App\Enums\ReservationStatus;
use App\Events\Reservation\ReservationStatusChanged;
use App\Events\Reservation\SlotAvailabilityChanged;
use App\Exceptions\Viewing\ClientHasActiveReservationForAdException;
use App\Exceptions\Viewing\ScheduleHasActiveReservationsException;
use App\Exceptions\Viewing\SelfReservationException;
use App\Exceptions\Viewing\SlotAlreadyReservedException;
use App\Exceptions\Viewing\SlotNotAvailableException;
use App\Models\Ad;
use App\Models\TentativeReservation;
use App\Models\User;
use App\Models\Zap\Schedule;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class ReservationService implements ReservationServiceInterface
{
    public function __construct(
        private ViewingScheduleServiceInterface $viewingScheduleService,
    ) {}

    /**
     * Tentatively reserve a time slot on a property for a client.
     *
     * @param array{
     *   slot_date: string,
     *   slot_starts_at: string,
     *   slot_ends_at: string,
     *   client_message: string|null,
     *   prescreening_answers: array<int, string|null>|null,
     * } $data
     *
     * @throws SelfReservationException
     * @throws SlotNotAvailableException
     * @throws SlotAlreadyReservedException
     * @throws ClientHasActiveReservationForAdException
     */
    public function reserve(Ad $ad, User $client, array $data): TentativeReservation
    {
        if ($ad->user_id === $client->id) {
            throw new SelfReservationException;
        }

        $this->assertSlotIsAvailable($ad, $client, $data);

        try {
            $reservation = DB::transaction(function () use ($ad, $client, $data): TentativeReservation {
                // Re-verify inside the transaction to guard against race conditions.
                $this->assertSlotIsAvailable($ad, $client, $data);

                // Create the exclusive Zap appointment schedule.
                $appointmentSchedule = $this->viewingScheduleService->reserveSlot($ad, [
                    'date' => $data['slot_date'],
                    'starts_at' => $data['slot_starts_at'],
                    'ends_at' => $data['slot_ends_at'],
                    'metadata' => [
                        'reserved_by' => $client->id,
                        'reserved_at' => now()->toIso8601String(),
                        'client_name' => $client->firstname.' '.$client->lastname,
                    ],
                ]);

                return TentativeReservation::query()->create([
                    'ad_id' => $ad->id,
                    'client_id' => $client->id,
                    'appointment_schedule_id' => $appointmentSchedule->id,
                    'slot_date' => $data['slot_date'],
                    'slot_starts_at' => $data['slot_starts_at'],
                    'slot_ends_at' => $data['slot_ends_at'],
                    'status' => ReservationStatus::Pending,
                    'client_message' => $data['client_message'] ?? null,
                    'prescreening_answers' => $data['prescreening_answers'] ?? null,
                    'expires_at' => now()->addHours((int) config('viewings.reservation_ttl_hours', 72)),
                ]);
            });
        } catch (UniqueConstraintViolationException $e) {
            if (str_contains($e->getMessage(), 'tr_unique_client_ad_active')) {
                throw new ClientHasActiveReservationForAdException;
            }

            throw new SlotAlreadyReservedException;
        }

        SlotAvailabilityChanged::dispatch(
            $ad->id,
            $data['slot_date'],
            $data['slot_starts_at'],
            false,
        );

        return $reservation;
    }

    /**
     * Cancel a tentative reservation (by client or landlord).
     */
    public function cancel(TentativeReservation $reservation, User $actor, ?string $reason = null): TentativeReservation
    {
        $cancelledBy = $reservation->isOwnedByClient($actor)
            ? CancelledBy::Client
            : CancelledBy::Landlord;

        $previous = $reservation->status->value;
        $adId = $reservation->ad_id;
        $slotDate = $reservation->slot_date->toDateString();
        $slotStartsAt = $reservation->slot_starts_at;

        DB::transaction(function () use ($reservation, $cancelledBy, $reason): void {
            $reservation->update([
                'status' => ReservationStatus::Cancelled,
                'cancelled_by' => $cancelledBy,
                'cancellation_reason' => $reason,
            ]);

            /** @phpstan-ignore-next-line if.alwaysTrue */
            if ($reservation->appointmentSchedule) {
                $this->viewingScheduleService->releaseSlot($reservation->appointmentSchedule);
            }
        });

        $fresh = $reservation->fresh(['ad']) ?? $reservation;

        ReservationStatusChanged::dispatch($fresh, $previous);
        SlotAvailabilityChanged::dispatch($adId, $slotDate, $slotStartsAt, true);

        return $fresh;
    }

    /**
     * Expire all pending reservations whose TTL has elapsed.
     * Called by the scheduled job.
     */
    public function expireStale(): int
    {
        $stale = TentativeReservation::query()
            ->expiredAndPending()
            ->with(['appointmentSchedule', 'client', 'ad'])
            ->get();

        DB::transaction(function () use ($stale): void {
            foreach ($stale as $reservation) {
                $reservation->update([
                    'status' => ReservationStatus::Expired,
                    'cancelled_by' => CancelledBy::System,
                ]);

                /** @phpstan-ignore-next-line if.alwaysTrue */
                if ($reservation->appointmentSchedule) {
                    $this->viewingScheduleService->releaseSlot($reservation->appointmentSchedule);
                }

                ReservationStatusChanged::dispatch($reservation, ReservationStatus::Pending->value);
                SlotAvailabilityChanged::dispatch(
                    $reservation->ad_id,
                    $reservation->slot_date->toDateString(),
                    $reservation->slot_starts_at,
                    true,
                );
            }
        });

        return $stale->count();
    }

    /**
     * Mark a confirmed reservation as completed (called after slot_ends_at has passed).
     */
    public function complete(TentativeReservation $reservation): TentativeReservation
    {
        $previous = $reservation->status->value;

        $reservation->update(['status' => ReservationStatus::Completed]);

        ReservationStatusChanged::dispatch($reservation->load('ad'), $previous);

        return $reservation->fresh() ?? $reservation;
    }

    /**
     * Mark a confirmed reservation as no-show (landlord action).
     */
    public function markNoShow(TentativeReservation $reservation): TentativeReservation
    {
        $previous = $reservation->status->value;

        $reservation->update(['status' => ReservationStatus::NoShow]);

        ReservationStatusChanged::dispatch($reservation->load('ad'), $previous);

        return $reservation->fresh() ?? $reservation;
    }

    /**
     * Guard against availability schedule modifications when active reservations exist.
     *
     * @throws ScheduleHasActiveReservationsException
     */
    public function assertNoActiveReservationsForSchedule(Schedule $schedule): void
    {
        $active = $this->activeReservationsCoveredBySchedule($schedule);

        if ($active->isNotEmpty()) {
            throw new ScheduleHasActiveReservationsException($active);
        }
    }

    /**
     * Active reservations whose slot falls within an availability schedule's
     * coverage window.
     *
     * Reservations only reference their own exclusive appointment schedule via
     * `appointment_schedule_id`; they never point at the availability schedule
     * the owner manages. Matching on that column therefore never finds anything,
     * so we match on the ad and the schedule's active date range instead.
     *
     * @return Collection<int, TentativeReservation>
     */
    public function activeReservationsCoveredBySchedule(Schedule $schedule): Collection
    {
        if (!$schedule->is_active) {
            return new Collection;
        }

        return TentativeReservation::query()
            ->where('ad_id', $schedule->schedulable_id)
            ->active()
            ->whereDate('slot_date', '>=', $schedule->start_date->toDateString())
            ->when(
                $schedule->end_date !== null,
                fn (Builder $query): Builder => $query->whereDate('slot_date', '<=', $schedule->end_date->toDateString()),
            )
            ->get();
    }

    /**
     * List paginated reservations for a property (landlord view).
     */
    public function listForAd(Ad $ad, array $filters = []): LengthAwarePaginator
    {
        $query = TentativeReservation::query()
            ->where('ad_id', $ad->id)
            ->with(['client', 'ad'])
            ->orderByDesc('slot_date')
            ->orderBy('slot_starts_at');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['from'])) {
            $query->whereDate('slot_date', '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $query->whereDate('slot_date', '<=', $filters['to']);
        }

        return $query->paginate(15);
    }

    /**
     * List paginated reservations for a client.
     */
    public function listForClient(User $client, array $filters = []): LengthAwarePaginator
    {
        $query = TentativeReservation::query()
            ->where('client_id', $client->id)
            ->with(['ad.quarter.city', 'ad.media', 'ad.user', 'ad.user.agency', 'ad.agency'])
            ->orderByDesc('slot_date')
            ->orderBy('slot_starts_at');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['ad_id'])) {
            $query->where('ad_id', $filters['ad_id']);
        }

        return $query->paginate(15);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * @throws SlotNotAvailableException
     * @throws SlotAlreadyReservedException
     * @throws ClientHasActiveReservationForAdException
     */
    private function assertSlotIsAvailable(Ad $ad, User $client, array $data): void
    {
        // Full slot instant must not be in the past (same calendar as app TZ).
        $slotStartsAt = Carbon::parse($data['slot_date'].' '.$data['slot_starts_at']);

        Log::debug('[SLOT_DEBUG] assertSlotIsAvailable', [
            'ad_id' => $ad->id,
            'slot_date' => $data['slot_date'],
            'slot_starts_at' => $data['slot_starts_at'],
            'slot_ends_at' => $data['slot_ends_at'],
            'parsed_start' => $slotStartsAt->toIso8601String(),
            'now' => now()->toIso8601String(),
            'is_past' => $slotStartsAt->isPast(),
            'app_tz' => config('app.timezone'),
        ]);

        if ($slotStartsAt->isPast()) {
            Log::debug('[SLOT_DEBUG] REJECTED: isPast');
            throw new SlotNotAvailableException;
        }

        $rawSlots = $this->viewingScheduleService->getBookableSlotsForDate($ad, $data['slot_date']);
        $offered = $this->viewingScheduleService->isOfferedBookableSlot(
            $ad,
            $data['slot_date'],
            $data['slot_starts_at'],
            $data['slot_ends_at'],
        );

        Log::debug('[SLOT_DEBUG] isOfferedBookableSlot', [
            'offered' => $offered,
            'raw_slots' => $rawSlots,
        ]);

        // Must match GET /slots: use schedule metadata (duration + buffer), not Zap defaults.
        if (!$offered) {
            Log::debug('[SLOT_DEBUG] REJECTED: not offered by schedule');
            throw new SlotNotAvailableException;
        }

        // One active (pending or confirmed) reservation per client per ad — avoids duplicate requests / multi-slot confusion.
        $clientHasActiveForAd = TentativeReservation::query()
            ->where('ad_id', $ad->id)
            ->where('client_id', $client->id)
            ->active()
            ->exists();

        if ($clientHasActiveForAd) {
            throw new ClientHasActiveReservationForAdException;
        }

        // Check our own DB has no active reservation for this exact slot.
        $alreadyReserved = TentativeReservation::query()
            ->where('ad_id', $ad->id)
            ->whereDate('slot_date', $data['slot_date'])
            ->where('slot_starts_at', $data['slot_starts_at'])
            ->whereIn('status', [ReservationStatus::Pending, ReservationStatus::Confirmed])
            ->exists();

        if ($alreadyReserved) {
            throw new SlotAlreadyReservedException;
        }
    }
}
