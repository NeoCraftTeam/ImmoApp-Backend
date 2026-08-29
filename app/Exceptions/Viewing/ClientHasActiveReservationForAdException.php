<?php

declare(strict_types=1);

namespace App\Exceptions\Viewing;

use App\Support\SafeApiMessage;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ClientHasActiveReservationForAdException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Vous avez déjà une demande ou une visite active pour cette annonce.');
    }

    public function render(): JsonResponse
    {
        $payload = SafeApiMessage::envelope(
            $this->getMessage(),
            'CLIENT_ACTIVE_RESERVATION_EXISTS',
            Response::HTTP_CONFLICT,
            'Annulez votre réservation en cours ou attendez la confirmation avant d\'en proposer une autre.',
        );

        return response()->json($payload, Response::HTTP_CONFLICT);
    }
}
