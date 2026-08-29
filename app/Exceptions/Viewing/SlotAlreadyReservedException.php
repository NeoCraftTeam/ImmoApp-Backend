<?php

declare(strict_types=1);

namespace App\Exceptions\Viewing;

use App\Support\SafeApiMessage;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class SlotAlreadyReservedException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Ce créneau vient d\'être réservé par un autre utilisateur.');
    }

    public function render(): JsonResponse
    {
        $payload = SafeApiMessage::envelope(
            $this->getMessage(),
            'SLOT_ALREADY_RESERVED',
            Response::HTTP_CONFLICT,
            'Veuillez sélectionner un autre créneau disponible.',
        );

        return response()->json($payload, Response::HTTP_CONFLICT);
    }
}
