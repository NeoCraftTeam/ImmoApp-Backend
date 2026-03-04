<?php

declare(strict_types=1);

namespace App\Exceptions\Viewing;

use Symfony\Component\HttpFoundation\Response;

class SlotNotAvailableException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Ce créneau n\'est pas disponible pour la date demandée.');
    }

    public function render(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'SLOT_NOT_AVAILABLE',
                'message' => $this->getMessage(),
                'hint' => 'Ce créneau n\'existe pas ou la date est passée.',
            ],
        ], Response::HTTP_GONE);
    }
}
