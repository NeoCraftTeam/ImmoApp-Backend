<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class AmountMismatchException extends RuntimeException
{
    public function __construct(float $expected, float $actual)
    {
        parent::__construct(
            "Montant incohérent : attendu {$expected}, reçu {$actual}."
        );
    }
}
