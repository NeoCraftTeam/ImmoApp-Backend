<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Services\HealthCheckService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
final readonly class HealthCheckController
{
    public function __construct(
        private HealthCheckService $health,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        // ── Optional static-token gate ────────────────────────────────
        // Set HEALTH_CHECK_TOKEN in .env to protect this endpoint.
        // Leave empty for open access (e.g. internal load-balancer probes).
        $requiredToken = config('services.health.token');
        if ($requiredToken && $request->bearerToken() !== $requiredToken) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $result = $this->health->run(force: $request->boolean('force'));
        $httpStatus = $result['status'] === 'unhealthy' ? 503 : 200;

        // Report unhealthy state to Sentry so on-call engineers are alerted.
        if ($result['status'] === 'unhealthy') {
            report(new \RuntimeException('Health check: system is UNHEALTHY'));
        }

        return response()->json($result, $httpStatus);
    }
}
