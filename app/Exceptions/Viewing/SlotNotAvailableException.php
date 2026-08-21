<?php

declare(strict_types=1);

namespace App\Exceptions\Viewing;

use App\Support\SafeApiMessage;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class SlotNotAvailableException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Ce créneau n\'est pas disponible pour la date demandée.');
    }

    public function render(): JsonResponse
    {
        $payload = SafeApiMessage::envelope(
            $this->getMessage(),
            'SLOT_NOT_AVAILABLE',
            Response::HTTP_GONE,
            'Ce créneau n\'existe pas ou la date est passée.',
        );
        // Compat: keep legacy {error:{}} envelope until frontend migrates to top-level code/message.
        $payload['error'] = [
            'code' => $payload['code'],
            'message' => $payload['message'],
            'hint' => $payload['hint'] ?? null,
        ];

        return response()->json($payload, Response::HTTP_GONE);
    }
}
