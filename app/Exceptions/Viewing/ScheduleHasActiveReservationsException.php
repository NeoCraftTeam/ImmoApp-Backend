<?php

declare(strict_types=1);

namespace App\Exceptions\Viewing;

use App\Models\TentativeReservation;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class ScheduleHasActiveReservationsException extends \RuntimeException
{
    /** @param Collection<int, TentativeReservation> $reservations */
    public function __construct(private readonly Collection $reservations)
    {
        parent::__construct('Ce planning a des réservations provisoires actives.');
    }

    public function render(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'SCHEDULE_HAS_ACTIVE_RESERVATIONS',
                'message' => $this->getMessage(),
                'hint' => 'Annulez ou attendez l\'expiration des réservations actives avant de modifier ce planning.',
                'reservations' => $this->reservations->map(fn (TentativeReservation $r): array => [
                    'id' => $r->id,
                    'slot_date' => $r->slot_date->toDateString(),
                    'slot_starts_at' => $r->slot_starts_at,
                    'slot_ends_at' => $r->slot_ends_at,
                    'status' => $r->status->value,
                ])->values(),
            ],
        ], Response::HTTP_CONFLICT);
    }
}
