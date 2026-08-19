<?php

declare(strict_types=1);

namespace App\Exceptions\Viewing;

use App\Support\SafeApiMessage;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class SelfReservationException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Vous ne pouvez pas réserver votre propre bien.');
    }

    public function render(): JsonResponse
    {
        $payload = SafeApiMessage::envelope(
            $this->getMessage(),
            'SELF_RESERVATION_NOT_ALLOWED',
            Response::HTTP_FORBIDDEN,
        );
        $payload['error'] = [
            'code' => $payload['code'],
            'message' => $payload['message'],
        ];

        return response()->json($payload, Response::HTTP_FORBIDDEN);
    }
}
