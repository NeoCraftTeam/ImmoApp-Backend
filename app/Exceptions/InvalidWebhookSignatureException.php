<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class InvalidWebhookSignatureException extends RuntimeException
{
    public function __construct(string $message = 'Signature de webhook invalide.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
