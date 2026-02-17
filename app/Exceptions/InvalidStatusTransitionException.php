<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\AdStatus;
use RuntimeException;

class InvalidStatusTransitionException extends RuntimeException
{
    public function __construct(AdStatus $from, AdStatus $to)
    {
        parent::__construct(
            "Transition de statut invalide : {$from->getLabel()} → {$to->getLabel()}."
        );
    }
}
