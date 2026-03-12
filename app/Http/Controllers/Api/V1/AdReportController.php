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
     *     description="Permet à un client authentifié de signaler une annonce avec un motif principal et, si nécessaire, des détails complémentaires.",
     *     tags={"🚩 Signalements"},
     *     security={{"sanctum":{}}},
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
     *         @OA\JsonContent(
     *             required={"reason"},
     *
     *             @OA\Property(property="reason", type="string", enum={"inaccurate","not_real_property","scam","shocking_content","other"}),
     *             @OA\Property(property="scam_reason", type="string", nullable=true, enum={"asked_off_platform_payment","shared_contacts","promoting_external_services","duplicate_listing","misleading_listing"}),
     *             @OA\Property(property="payment_methods", type="array", nullable=true, @OA\Items(type="string", enum={"bank_transfer","card","cash","paypal","moneygram","western_union","other"})),
     *             @OA\Property(property="description", type="string", nullable=true, maxLength=2000)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Signalement enregistré",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Signalement envoye. Merci de nous aider a proteger la communaute."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="string", format="uuid"),
     *                 @OA\Property(property="status", type="string", example="pending")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Non authentifié"),
     *     @OA\Response(response=422, description="Erreur de validation")
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
