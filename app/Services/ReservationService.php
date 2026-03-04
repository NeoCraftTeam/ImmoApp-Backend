<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CancelledBy;
use App\Enums\ReservationStatus;
use App\Exceptions\Viewing\ScheduleHasActiveReservationsException;
use App\Exceptions\Viewing\SelfReservationException;
use App\Exceptions\Viewing\SlotAlreadyReservedException;
use App\Exceptions\Viewing\SlotNotAvailableException;
use App\Models\Ad;
use App\Models\TentativeReservation;
use App\Models\User;
use App\Models\Zap\Schedule;
use App\Services\Contracts\ReservationServiceInterface;
use App\Services\Contracts\ViewingScheduleServiceInterface;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

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
     * } $data
     *
     * @throws SelfReservationException
     * @throws SlotNotAvailableException
     * @throws SlotAlreadyReservedException
     */
    public function reserve(Ad $ad, User $client, array $data): TentativeReservation
    {
        if ($ad->user_id === $client->id) {
            throw new SelfReservationException;
        }

        $this->assertSlotIsAvailable($ad, $data);

        try {
            return DB::transaction(function () use ($ad, $client, $data): TentativeReservation {
                // Re-verify inside the transaction to guard against race conditions.
                $this->assertSlotIsAvailable($ad, $data);

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
                    'expires_at' => now()->addHours(24),
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            throw new SlotAlreadyReservedException;
        }
    }

    /**
     * Cancel a tentative reservation (by client or landlord).
     */
    public function cancel(TentativeReservation $reservation, User $actor, ?string $reason = null): TentativeReservation
    {
        $cancelledBy = $reservation->isOwnedByClient($actor)
            ? CancelledBy::Client
            : CancelledBy::Landlord;

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

        return $reservation->fresh();
    }

    /**
     * Expire all pending reservations whose TTL has elapsed.
     * Called by the scheduled job.
     */
    public function expireStale(): int
    {
        $stale = TentativeReservation::query()
            ->expiredAndPending()
            ->with('appointmentSchedule')
            ->get();

        return DB::transaction(function () use ($stale): int {
            foreach ($stale as $reservation) {
                $reservation->update([
                    'status' => ReservationStatus::Expired,
                    'cancelled_by' => CancelledBy::System,
                ]);

                /** @phpstan-ignore-next-line if.alwaysTrue */
                if ($reservation->appointmentSchedule) {
                    $this->viewingScheduleService->releaseSlot($reservation->appointmentSchedule);
                }
            }

            return $stale->count();
        });
    }

    /**
     * Guard against availability schedule modifications when active reservations exist.
     *
     * @throws ScheduleHasActiveReservationsException
     */
    public function assertNoActiveReservationsForSchedule(Schedule $schedule): void
    {
        $active = TentativeReservation::query()
            ->where('appointment_schedule_id', $schedule->id)
            ->active()
            ->get();

        if ($active->isNotEmpty()) {
            throw new ScheduleHasActiveReservationsException($active);
        }
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
            ->with(['ad'])
            ->orderByDesc('slot_date')
            ->orderBy('slot_starts_at');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate(15);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * @throws SlotNotAvailableException
     */
    private function assertSlotIsAvailable(Ad $ad, array $data): void
    {
        // Check date is not in the past.
        if (Carbon::parse($data['slot_date'])->isPast() && !Carbon::parse($data['slot_date'])->isToday()) {
            throw new SlotNotAvailableException;
        }

        // Check Zap confirms the slot is bookable.
        $isBookable = $ad->isBookableAtTime(
            $data['slot_date'],
            $data['slot_starts_at'],
            $data['slot_ends_at']
        );

        if (!$isBookable) {
            throw new SlotNotAvailableException;
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
