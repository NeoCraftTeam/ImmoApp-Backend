<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class AccountInactiveException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Compte désactivé. Contactez l\'administrateur.');
    }
}
