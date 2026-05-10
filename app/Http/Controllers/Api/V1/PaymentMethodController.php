<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Services\Payment\PaymentMethodGateService;
use Illuminate\Http\JsonResponse;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(name="Paiements")
 */
final readonly class PaymentMethodController
{
    public function __construct(private PaymentMethodGateService $gate) {}

    /**
     * Public list of currently available payment methods.
     *
     * Admin-controlled : flipping a toggle from Filament removes a method
     * from this response within the 5 min `Setting` cache TTL. The frontend
     * `<PaymentModal>` consumes this endpoint to render a dynamic selector
     * instead of hard-coding the four methods.
     *
     * @OA\Get(
     *     path="/api/v1/payments/methods",
     *     summary="Liste des moyens de paiement actifs",
     *     tags={"💰 Paiements"},
     *
     *     @OA\Response(response=200, description="Catalogue des moyens disponibles",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="data", type="array",
     *
     *                 @OA\Items(
     *
     *                     @OA\Property(property="value", type="string", example="card"),
     *                     @OA\Property(property="label", type="string", example="Carte bancaire"),
     *                     @OA\Property(property="gateway", type="string", example="stripe"),
     *                     @OA\Property(property="enabled", type="boolean", example=true)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->gate->describeAvailable(),
        ]);
    }
}
