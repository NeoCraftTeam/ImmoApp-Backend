<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Support\AuthError;
use RuntimeException;

final class RoleContextMismatchException extends RuntimeException
{
    public function __construct(
        public readonly string $authCode = AuthError::CODE_PANEL_ACCESS_DENIED,
    ) {
        parent::__construct(AuthError::LOGIN_FAILURE_MESSAGE);
    }
}
