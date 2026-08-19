<?php

declare(strict_types=1);

namespace App\Exceptions\Viewing;

use App\Models\TentativeReservation;
use App\Support\SafeApiMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class ScheduleHasActiveReservationsException extends \RuntimeException
{
    /** @param Collection<int, TentativeReservation> $reservations */
    public function __construct(private readonly Collection $reservations)
    {
        parent::__construct('Ce planning a des réservations provisoires actives.');
    }

    public function render(): JsonResponse
    {
        $extra = [
            'reservations' => $this->reservations->map(fn (TentativeReservation $r): array => [
                'id' => $r->id,
                'slot_date' => $r->slot_date->toDateString(),
                'slot_starts_at' => $r->slot_starts_at,
                'slot_ends_at' => $r->slot_ends_at,
                'status' => $r->status->value,
            ])->values(),
        ];
        $payload = SafeApiMessage::envelope(
            $this->getMessage(),
            'SCHEDULE_HAS_ACTIVE_RESERVATIONS',
            Response::HTTP_CONFLICT,
            'Annulez ou attendez l\'expiration des réservations actives avant de modifier ce planning.',
            null,
            $extra,
        );
        $payload['error'] = [
            'code' => $payload['code'],
            'message' => $payload['message'],
            'hint' => $payload['hint'] ?? null,
            'reservations' => $payload['reservations'],
        ];

        return response()->json($payload, Response::HTTP_CONFLICT);
    }
}
