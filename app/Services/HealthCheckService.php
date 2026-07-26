<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Enterprise health-check service.
 *
 * Checks: Database · Redis · Queue · Storage · Meilisearch · Kpay
 *
 * Status tiers
 * ─────────────
 *  healthy   — all checks pass
 *  degraded  — non-critical check failed (Redis, Queue, Meilisearch, Kpay)
 *  unhealthy — critical check failed (Database or Storage)
 *
 * Results are cached for CACHE_TTL seconds so monitoring bursts don't
 * hammer the database. Pass `force: true` to bypass the cache.
 */
final readonly class HealthCheckService
{
    private const string CACHE_KEY = 'system.health_check';

    private const int CACHE_TTL = 30; // seconds

    /** Non-critical: warn threshold for failed jobs */
    private const int FAILED_JOBS_WARN = 50;

    /** Non-critical: critical threshold for failed jobs (returns degraded, not unhealthy) */
    private const int FAILED_JOBS_CRITICAL = 500;

    /** Critical: free disk space warning threshold (MB) */
    private const int DISK_WARN_MB = 1_024;

    /** Critical: free disk space hard-fail threshold (MB) */
    private const int DISK_CRITICAL_MB = 256;

    /** ─────────────────────────────────────────────────────────────── */
    public function run(bool $force = false): array
    {
        if ($force) {
            Cache::forget(self::CACHE_KEY);
        }

        return Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL,
            fn () => $this->execute()
        );
    }

    // ── Private ──────────────────────────────────────────────────────

    private function execute(): array
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'queue' => $this->checkQueue(),
            'storage' => $this->checkStorage(),
            'meilisearch' => $this->checkMeilisearch(),
            'kpay' => $this->checkKpay(),
        ];

        $status = $this->resolveOverallStatus($checks);

        return [
            'status' => $status,
            'timestamp' => now()->toIso8601String(),
            'version' => config('app.version', '1.0.0'),
            'environment' => app()->environment(),
            'checks' => $checks,
        ];
    }

    /**
     * Database and Storage are critical — their failure makes the whole
     * system unhealthy. Everything else is non-critical (degraded).
     */
    private function resolveOverallStatus(array $checks): string
    {
        $critical = ['database', 'storage'];

        foreach ($critical as $name) {
            if (($checks[$name]['status'] ?? 'healthy') === 'unhealthy') {
                return 'unhealthy';
            }
        }

        foreach ($checks as $check) {
            if (in_array($check['status'], ['degraded', 'unhealthy'], true)) {
                return 'degraded';
            }
        }

        return 'healthy';
    }

    // ── Individual checks ─────────────────────────────────────────────

    private function checkDatabase(): array
    {
        $start = hrtime(true);

        try {
            DB::select('SELECT 1');

            return [
                'status' => 'healthy',
                'latency_ms' => $this->ms($start),
                'message' => 'Connected',
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'unhealthy',
                'latency_ms' => null,
                'message' => 'Connection failed: '.$e->getMessage(),
            ];
        }
    }

    private function checkRedis(): array
    {
        $start = hrtime(true);
        $key = 'health.probe.'.time();

        try {
            Cache::store('redis')->put($key, 1, 15);
            Cache::store('redis')->forget($key);

            return [
                'status' => 'healthy',
                'latency_ms' => $this->ms($start),
                'message' => 'Connected',
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'degraded',
                'latency_ms' => null,
                'message' => 'Unavailable — cache falls back to database driver: '.$e->getMessage(),
            ];
        }
    }

    private function checkQueue(): array
    {
        try {
            $pending = DB::table('jobs')->count();
            $failed = DB::table('failed_jobs')->count();

            $status = 'healthy';
            $message = "{$pending} pending, {$failed} failed";

            if ($failed >= self::FAILED_JOBS_CRITICAL) {
                $status = 'degraded';
                $message = "Very high failed job count: {$failed}";
            } elseif ($failed >= self::FAILED_JOBS_WARN) {
                $status = 'degraded';
                $message = "Elevated failed job count: {$failed}";
            }

            return [
                'status' => $status,
                'latency_ms' => null,
                'message' => $message,
                'details' => ['pending' => $pending, 'failed' => $failed],
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'degraded',
                'latency_ms' => null,
                'message' => 'Unable to query queue tables: '.$e->getMessage(),
            ];
        }
    }

    private function checkStorage(): array
    {
        $path = storage_path();

        if (!is_writable($path)) {
            return [
                'status' => 'unhealthy',
                'latency_ms' => null,
                'message' => 'Storage directory is not writable',
            ];
        }

        $freeMb = round(disk_free_space($path) / 1_048_576, 2);
        $totalMb = round(disk_total_space($path) / 1_048_576, 2);
        $usedPct = $totalMb > 0 ? round((1 - $freeMb / $totalMb) * 100, 1) : 0;

        $status = match (true) {
            $freeMb < self::DISK_CRITICAL_MB => 'unhealthy',
            $freeMb < self::DISK_WARN_MB => 'degraded',
            default => 'healthy',
        };

        return [
            'status' => $status,
            'latency_ms' => null,
            'message' => "Writable — {$freeMb} MB free ({$usedPct}% used)",
            'details' => [
                'free_mb' => $freeMb,
                'total_mb' => $totalMb,
                'used_percent' => $usedPct,
            ],
        ];
    }

    private function checkMeilisearch(): array
    {
        $host = config('scout.meilisearch.host');

        if (!$host) {
            return [
                'status' => 'healthy',
                'latency_ms' => null,
                'message' => 'Not configured (Scout driver is not Meilisearch)',
            ];
        }

        $start = hrtime(true);

        try {
            $response = Http::timeout(3)->connectTimeout(2)->get("{$host}/health");
            $latency = $this->ms($start);

            if ($response->successful()) {
                return [
                    'status' => 'healthy',
                    'latency_ms' => $latency,
                    'message' => 'Healthy',
                ];
            }

            return [
                'status' => 'degraded',
                'latency_ms' => $latency,
                'message' => "HTTP {$response->status()}",
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'degraded',
                'latency_ms' => null,
                'message' => 'Unreachable: '.$e->getMessage(),
            ];
        }
    }

    private function checkKpay(): array
    {
        $start = hrtime(true);
        $baseUrl = rtrim((string) config('payment.gateways.kpay.base_url', 'https://admin.kpay.site'), '/');

        try {
            $response = Http::timeout(5)
                ->connectTimeout(3)
                ->withHeaders([
                    'X-API-Key' => (string) config('payment.gateways.kpay.api_key', ''),
                    'X-Secret-Key' => (string) config('payment.gateways.kpay.api_secret', ''),
                ])
                ->get($baseUrl.'/api/v1/payments/me');

            $latency = $this->ms($start);

            if ($response->successful()) {
                return [
                    'status' => 'healthy',
                    'latency_ms' => $latency,
                    'message' => 'Reachable',
                ];
            }

            return [
                'status' => 'degraded',
                'latency_ms' => $latency,
                'message' => "Gateway error HTTP {$response->status()}",
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'degraded',
                'latency_ms' => null,
                'message' => 'Unreachable: '.$e->getMessage(),
            ];
        }
    }

    // ── Utility ───────────────────────────────────────────────────────

    /** Convert hrtime(true) start to milliseconds (2 decimal places). */
    private function ms(int $start): float
    {
        return round((hrtime(true) - $start) / 1_000_000, 2);
    }
}
