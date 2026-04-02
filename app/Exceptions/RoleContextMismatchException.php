<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class RoleContextMismatchException extends RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}
