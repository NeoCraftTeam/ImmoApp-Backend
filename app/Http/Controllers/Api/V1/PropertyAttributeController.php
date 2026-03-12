<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\PropertyAttribute;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

final class PropertyAttributeController
{
    /**
     * Get available property attributes (public).
     */
    #[OA\Get(
        path: '/api/v1/property-attributes',
        summary: 'Attributs de propriété disponibles',
        description: 'Retourne la liste des attributs disponibles (Wi-Fi, parking, etc.) pour les annonces.',
        tags: ['🏠 Annonces'],
        responses: [
            new OA\Response(response: 200, description: 'Attributs récupérés avec succès'),
        ]
    )]
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => PropertyAttribute::toApiArray(),
            'grouped' => PropertyAttribute::toApiGroupedArray(),
        ]);
    }
}
