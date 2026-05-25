<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Lightweight feature flag service.
 *
 * Checks runtime overrides (from the settings table) first,
 * then falls back to config/features.php (env-driven).
 */
final class FeatureFlagService
{
    /**
     * Check if a feature is enabled.
     */
    public function isEnabled(string $feature): bool
    {
        $override = $this->getRuntimeOverride($feature);

        if ($override !== null) {
            return $override;
        }

        return (bool) config("features.{$feature}", false);
    }

    /**
     * Get all feature flags with their current status.
     *
     * @return array<string, bool>
     */
    public function all(): array
    {
        $flags = config('features', []);
        $result = [];

        foreach ($flags as $key => $default) {
            $override = $this->getRuntimeOverride($key);
            $result[$key] = $override ?? (bool) $default;
        }

        return $result;
    }

    /**
     * Enable a feature at runtime (persists to settings table).
     */
    public function enable(string $feature): void
    {
        $this->setRuntimeOverride($feature, true);
    }

    /**
     * Disable a feature at runtime (persists to settings table).
     */
    public function disable(string $feature): void
    {
        $this->setRuntimeOverride($feature, false);
    }

    /**
     * Remove runtime override so config default takes effect.
     */
    public function reset(string $feature): void
    {
        Setting::query()
            ->where('key', "feature_flag:{$feature}")
            ->delete();

        Cache::forget("feature_flag:{$feature}");
    }

    /**
     * Get runtime override from settings table (cached 5 min).
     */
    private function getRuntimeOverride(string $feature): ?bool
    {
        $value = Cache::remember(
            "feature_flag:{$feature}",
            300,
            fn () => Setting::query()
                ->where('key', "feature_flag:{$feature}")
                ->value('value')
        );

        if ($value === null) {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Set runtime override in settings table and bust cache.
     */
    private function setRuntimeOverride(string $feature, bool $enabled): void
    {
        Setting::query()->updateOrCreate(
            ['key' => "feature_flag:{$feature}"],
            ['value' => $enabled ? '1' : '0'],
        );

        Cache::forget("feature_flag:{$feature}");
    }
}
