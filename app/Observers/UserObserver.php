<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;
use App\Services\UtmAttributionService;

final readonly class UserObserver
{
    public function __construct(private UtmAttributionService $utmAttribution) {}

    public function created(User $user): void
    {
        $this->utmAttribution->tryAttributeFromRecentVisitByIp($user);
    }
}
