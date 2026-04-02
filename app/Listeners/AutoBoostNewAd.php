<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\AdCreated;
use App\Services\AdBoostService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class AutoBoostNewAd implements ShouldQueue
{
    public string $queue = 'notifications';

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(private readonly AdBoostService $adBoostService) {}

    public function handle(AdCreated $event): void
    {
        $this->adBoostService->autoBoostIfEligible($event->ad);
    }

    /**
     * Handle a listener failure.
     */
    public function failed(mixed $event, Throwable $exception): void
    {
        Log::error('AutoBoostNewAd listener failed', [
            'exception' => $exception->getMessage(),
        ]);
    }
}
