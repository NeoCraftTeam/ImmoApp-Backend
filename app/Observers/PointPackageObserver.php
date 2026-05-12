<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\PointPackage;
use Illuminate\Support\Facades\Cache;

/**
 * Invalidate the cached `credits/packages` list whenever a PointPackage is
 * created, updated, or deleted from Filament admin (or anywhere else).
 */
final class PointPackageObserver
{
    public function saved(PointPackage $package): void
    {
        $this->forget();
    }

    public function deleted(PointPackage $package): void
    {
        $this->forget();
    }

    private function forget(): void
    {
        Cache::forget('credits:packages:active');
    }
}
