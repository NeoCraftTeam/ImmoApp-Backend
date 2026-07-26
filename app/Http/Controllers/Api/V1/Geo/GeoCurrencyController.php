<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Geo;

use App\Services\Geo\GeoLocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/geo/currency
 *
 * Résout la devise d'affichage du visiteur à partir de son adresse IP
 * (MaxMind GeoLite2, repli en-tête edge puis XAF). Public : tout visiteur,
 * connecté ou non, doit pouvoir obtenir sa devise locale. Le client garde
 * la main — un choix manuel prime toujours sur cette détection.
 */
final readonly class GeoCurrencyController
{
    public function __construct(private GeoLocationService $geo) {}

    public function __invoke(Request $request): JsonResponse
    {
        return response()->json($this->geo->currencyForRequest($request));
    }
}
