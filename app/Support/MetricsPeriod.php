<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\Carbon;

/**
 * Value object that resolves a period string to a Carbon date boundary.
 */
final class MetricsPeriod
{
    /**
     * Resolve a period string (e.g. "30d", "7d", "90d", "year") to a Carbon instance
     * representing the start of that period.
     */
    public static function toDate(string $period): Carbon
    {
        return match ($period) {
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            '90d' => now()->subDays(90),
            'year' => now()->startOfYear(),
            default => now()->subDays(30),
        };
    }
}
