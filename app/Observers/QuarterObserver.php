<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Quarter;
use App\Services\Ai\AiSearchService;

/**
 * Quarter observer — invalidates AI search context cache when quarters change.
 */
final class QuarterObserver
{
    public function created(Quarter $quarter): void
    {
        AiSearchService::invalidateContextCache();
    }

    public function updated(Quarter $quarter): void
    {
        if ($quarter->wasChanged('name')) {
            AiSearchService::invalidateContextCache();
        }
    }

    public function deleted(Quarter $quarter): void
    {
        AiSearchService::invalidateContextCache();
    }
}
