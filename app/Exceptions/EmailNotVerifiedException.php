<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class EmailNotVerifiedException extends RuntimeException
{
    public function __construct(
        public readonly string $email = '',
        public readonly string $role = '',
    ) {
        parent::__construct('Veuillez vérifier votre adresse email avant de vous connecter.');
    }
}
