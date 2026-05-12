<?php

declare(strict_types=1);

namespace App\Exceptions\Viewing;

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
        return response()->json([
            'error' => [
                'code' => 'CLIENT_ACTIVE_RESERVATION_EXISTS',
                'message' => $this->getMessage(),
                'hint' => 'Annulez votre réservation en cours ou attendez la confirmation avant d\'en proposer une autre.',
            ],
        ], Response::HTTP_CONFLICT);
    }
}
