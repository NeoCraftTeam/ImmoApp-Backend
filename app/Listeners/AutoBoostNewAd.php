<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\AdCreated;
use App\Services\AdBoostService;

class AutoBoostNewAd
{
    public function __construct(private AdBoostService $adBoostService) {}

    public function handle(AdCreated $event): void
    {
        $this->adBoostService->autoBoostIfEligible($event->ad);
    }
}
