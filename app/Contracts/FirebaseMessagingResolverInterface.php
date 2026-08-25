<?php

declare(strict_types=1);

namespace App\Contracts;

use Kreait\Firebase\Contract\Messaging;

interface FirebaseMessagingResolverInterface
{
    /**
     * Resolve the shared Firebase Cloud Messaging client, or null when
     * credentials are unavailable (callers skip sending gracefully).
     */
    public function make(): ?Messaging;
}
