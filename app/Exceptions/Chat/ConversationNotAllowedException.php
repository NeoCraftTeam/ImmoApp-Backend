<?php

declare(strict_types=1);

namespace App\Exceptions\Chat;

use RuntimeException;

/**
 * Thrown when a conversation cannot be started because the ad has not been unlocked.
 */
final class ConversationNotAllowedException extends RuntimeException
{
    public function __construct(string $message = 'You must unlock this ad before starting a conversation.')
    {
        parent::__construct($message);
    }
}
