<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\AdType;
use App\Services\Ai\AiSearchService;

/**
 * AdType observer — invalidates AI search context cache when ad types change.
 */
final class AdTypeObserver
{
    public function created(AdType $adType): void
    {
        AiSearchService::invalidateContextCache();
    }

    public function updated(AdType $adType): void
    {
        if ($adType->wasChanged('name')) {
            AiSearchService::invalidateContextCache();
        }
    }

    public function deleted(AdType $adType): void
    {
        AiSearchService::invalidateContextCache();
    }
}
