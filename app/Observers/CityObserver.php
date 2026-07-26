<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\City;
use App\Services\Ai\AiSearchService;

/**
 * City observer — invalidates AI search context cache when cities change.
 */
final class CityObserver
{
    public function created(City $city): void
    {
        AiSearchService::invalidateContextCache();
    }

    public function updated(City $city): void
    {
        if ($city->wasChanged('name')) {
            AiSearchService::invalidateContextCache();
        }
    }

    public function deleted(City $city): void
    {
        AiSearchService::invalidateContextCache();
    }
}
