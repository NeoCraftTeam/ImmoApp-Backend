<?php

declare(strict_types=1);

namespace App\Exceptions\Viewing;

use Symfony\Component\HttpFoundation\Response;

class SlotAlreadyReservedException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Ce créneau vient d\'être réservé par un autre utilisateur.');
    }

    public function render(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'SLOT_ALREADY_RESERVED',
                'message' => $this->getMessage(),
                'hint' => 'Veuillez sélectionner un autre créneau disponible.',
            ],
        ], Response::HTTP_CONFLICT);
    }
}
