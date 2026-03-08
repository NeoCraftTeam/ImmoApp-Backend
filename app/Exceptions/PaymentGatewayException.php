<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class PaymentGatewayException extends RuntimeException
{
    public function __construct(string $message = 'Erreur du gateway de paiement.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
