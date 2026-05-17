<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Lightweight per-provider circuit breaker backed by the Laravel cache.
 *
 * When a provider accumulates `$failureThreshold` consecutive failures, the
 * circuit is opened for `$openTtlSeconds`. Callers should check `isOpen()`
 * before dispatching requests, call `recordFailure()` on error, and
 * `reset()` on success.
 *
 * Prefix the `$namespace` parameter to scope circuits to a specific feature
 * (e.g. 'ai_search', 'recommendations') without key collisions.
 */
final readonly class AiCircuitBreaker
{
    public function __construct(
        private string $namespace,
        private int $failureThreshold = 3,
        private int $openTtlSeconds = 300,
        private int $failureTtlSeconds = 21600,
    ) {}

    /** Returns true when the circuit is open and the provider should be skipped. */
    public function isOpen(string $provider): bool
    {
        return Cache::has($this->circuitKey($provider));
    }

    /**
     * Increment the failure counter for a provider. Opens the circuit when the
     * threshold is reached.
     */
    public function recordFailure(string $provider): void
    {
        $key = $this->failureKey($provider);
        $failures = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $failures, $this->failureTtlSeconds);

        if ($failures >= $this->failureThreshold) {
            Cache::put($this->circuitKey($provider), true, $this->openTtlSeconds);
            Log::warning("{$this->namespace}: circuit opened for [{$provider}] after {$failures} consecutive failures.");
        }
    }

    /** Reset failure count and close the circuit after a successful call. */
    public function reset(string $provider): void
    {
        Cache::forget($this->failureKey($provider));
        Cache::forget($this->circuitKey($provider));
    }

    private function circuitKey(string $provider): string
    {
        return "{$this->namespace}:circuit:{$provider}";
    }

    private function failureKey(string $provider): string
    {
        return "{$this->namespace}:failures:{$provider}";
    }
}
