<?php

declare(strict_types=1);

namespace App\Exceptions\Viewing;

use Symfony\Component\HttpFoundation\Response;

class SelfReservationException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Vous ne pouvez pas réserver votre propre bien.');
    }

    public function render(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'SELF_RESERVATION_NOT_ALLOWED',
                'message' => $this->getMessage(),
            ],
        ], Response::HTTP_FORBIDDEN);
    }
}
