<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\ReportAdListingRequest;
use App\Models\Ad;
use App\Services\AdReportService;
use Illuminate\Http\JsonResponse;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(name="🚩 Signalements", description="Signalement des annonces par les clients")
 */
final readonly class AdReportController
{
    public function __construct(
        private AdReportService $adReportService,
    ) {}

    /**
     * @OA\Post(
     *     path="/api/v1/ads/{ad}/reports",
     *     summary="Signaler une annonce",
     *     description="Permet à un client authentifié de signaler une annonce avec un motif principal et, si nécessaire, des détails complémentaires. La création est bloquée si l'utilisateur signale sa propre annonce ou s'il a déjà un signalement ouvert pour cette annonce.",
     *     tags={"🚩 Signalements"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="ad",
     *         in="path",
     *         required=true,
     *         description="UUID de l'annonce",
     *
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(ref="#/components/schemas/AdReportStoreRequest")
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Signalement enregistré",
     *
     *         @OA\JsonContent(ref="#/components/schemas/AdReportStoreSuccessResponse")
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Non authentifié",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Annonce introuvable",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="No query results for model [App\\Models\\Ad].")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation (champs invalides, auto-signalement, signalement déjà ouvert)",
     *
     *         @OA\JsonContent(ref="#/components/schemas/AdReportValidationErrorResponse")
     *     )
     * )
     */
    public function store(ReportAdListingRequest $request, Ad $ad): JsonResponse
    {
        $report = $this->adReportService->submit(
            $request->user(),
            $ad,
            $request->validated(),
            $request,
        );

        return response()->json([
            'message' => 'Signalement envoye. Merci de nous aider a proteger la communaute.',
            'data' => [
                'id' => $report->id,
                'status' => $report->status->value,
            ],
        ], 201);
    }
}
