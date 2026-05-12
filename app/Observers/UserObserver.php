<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\PointTransactionType;
use App\Models\Setting;
use App\Models\User;
use App\Services\PointService;
use App\Services\UtmAttributionService;

final readonly class UserObserver
{
    public function __construct(
        private UtmAttributionService $utmAttribution,
        private PointService $pointService,
    ) {}

    public function created(User $user): void
    {
        $this->utmAttribution->tryAttributeFromRecentVisitByIp($user);

        // Welcome bonus — moved here from `User::booted()` so the model layer
        // doesn't reach into the application service layer (was a service-locator
        // anti-pattern: `app(PointService::class)`).
        $bonus = (int) Setting::get('welcome_bonus_points', 5);
        if ($bonus > 0) {
            $this->pointService->credit(
                $user,
                $bonus,
                PointTransactionType::BONUS,
                'Bonus de bienvenue',
            );
        }
    }
}
