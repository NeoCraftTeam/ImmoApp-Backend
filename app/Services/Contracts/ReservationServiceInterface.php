<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Models\Ad;
use App\Models\TentativeReservation;
use App\Models\User;
use App\Models\Zap\Schedule;
use Illuminate\Pagination\LengthAwarePaginator;

interface ReservationServiceInterface
{
    /**
     * Tentatively reserve a time slot on a property for a client.
     *
     * @param  array<string, mixed>  $data
     */
    public function reserve(Ad $ad, User $client, array $data): TentativeReservation;

    /**
     * Cancel a tentative reservation (by client or landlord).
     */
    public function cancel(TentativeReservation $reservation, User $actor, ?string $reason = null): TentativeReservation;

    /**
     * Expire all pending reservations whose TTL has elapsed.
     */
    public function expireStale(): int;

    /**
     * Guard against availability schedule modifications when active reservations exist.
     */
    public function assertNoActiveReservationsForSchedule(Schedule $schedule): void;

    /**
     * List paginated reservations for a property (landlord view).
     *
     * @param  array<string, mixed>  $filters
     */
    public function listForAd(Ad $ad, array $filters = []): LengthAwarePaginator;

    /**
     * List paginated reservations for a client.
     *
     * @param  array<string, mixed>  $filters
     */
    public function listForClient(User $client, array $filters = []): LengthAwarePaginator;
}
