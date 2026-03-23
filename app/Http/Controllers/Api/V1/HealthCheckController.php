<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use OpenApi\Annotations as OA;

/**
 * @OA\Get(
 *     path="/api/health",
 *     summary="Comprehensive system health check",
 *     tags={"System"},
 *
 *     @OA\Response(
 *         response=200,
 *         description="All systems healthy",
 *
 *         @OA\JsonContent(
 *
 *             @OA\Property(property="status", type="string", example="healthy"),
 *             @OA\Property(property="checks", type="object"),
 *             @OA\Property(property="timestamp", type="string", format="date-time"),
 *         )
 *     ),
 *
 *     @OA\Response(response=503, description="One or more systems degraded")
 * )
 */
final class HealthCheckController
{
    public function __invoke(): JsonResponse
    {
        $checks = [];
        $healthy = true;

        $checks['database'] = $this->checkDatabase();
        $checks['redis'] = $this->checkRedis();
        $checks['queue'] = $this->checkQueue();
        $checks['storage'] = $this->checkStorage();
        $checks['meilisearch'] = $this->checkMeilisearch();

        foreach ($checks as $check) {
            if ($check['status'] !== 'ok') {
                $healthy = false;
            }
        }

        $status = $healthy ? 'healthy' : 'degraded';
        $httpCode = $healthy ? 200 : 503;

        return response()->json([
            'status' => $status,
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ], $httpCode);
    }

    /**
     * @return array{status: string, latency_ms?: float, error?: string}
     */
    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $latency = round((microtime(true) - $start) * 1000, 2);

            return ['status' => 'ok', 'latency_ms' => $latency];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array{status: string, latency_ms?: float, error?: string}
     */
    private function checkRedis(): array
    {
        try {
            $start = microtime(true);
            Cache::store('redis')->put('health_check', true, 10);
            $latency = round((microtime(true) - $start) * 1000, 2);

            return ['status' => 'ok', 'latency_ms' => $latency];
        } catch (\Throwable) {
            return ['status' => 'unavailable', 'error' => 'Redis not configured or unreachable'];
        }
    }

    /**
     * @return array{status: string, pending?: int, failed?: int, error?: string}
     */
    private function checkQueue(): array
    {
        try {
            $pending = DB::table('jobs')->count();
            $failed = DB::table('failed_jobs')->count();

            $status = $failed > 100 ? 'degraded' : 'ok';

            return ['status' => $status, 'pending' => $pending, 'failed' => $failed];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array{status: string, writable?: bool, free_mb?: float, error?: string}
     */
    private function checkStorage(): array
    {
        try {
            $storagePath = storage_path();
            $writable = is_writable($storagePath);
            $freeMb = round(disk_free_space($storagePath) / 1024 / 1024, 2);

            $status = ($writable && $freeMb > 100) ? 'ok' : 'degraded';

            return ['status' => $status, 'writable' => $writable, 'free_mb' => $freeMb];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array{status: string, latency_ms?: float, error?: string}
     */
    private function checkMeilisearch(): array
    {
        try {
            $host = config('scout.meilisearch.host');
            if (!$host) {
                return ['status' => 'unconfigured'];
            }

            $start = microtime(true);
            $ch = curl_init("{$host}/health");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 3,
                CURLOPT_CONNECTTIMEOUT => 2,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $latency = round((microtime(true) - $start) * 1000, 2);

            if ($httpCode === 200) {
                return ['status' => 'ok', 'latency_ms' => $latency];
            }

            return ['status' => 'degraded', 'error' => "HTTP {$httpCode}"];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }
}
