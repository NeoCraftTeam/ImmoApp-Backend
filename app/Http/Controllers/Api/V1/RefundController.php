<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\RefundRequest;
use App\Models\Payment;
use App\Services\Payment\RefundService;
use Illuminate\Http\JsonResponse;

final readonly class RefundController
{
    public function __construct(
        private RefundService $refundService,
    ) {}

    /**
     * @OA\Post(
     *     path="/api/v1/admin/payments/{payment}/refund",
     *     summary="Initiate a refund for a payment (admin only)",
     *     tags={"Admin - Payments"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="payment", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"reason"},
     *
     *         @OA\Property(property="reason", type="string", example="Client mécontent du service"),
     *         @OA\Property(property="amount", type="number", example=5000),
     *         @OA\Property(property="admin_note", type="string", example="Remboursement exceptionnel")
     *     )),
     *
     *     @OA\Response(response=200, description="Refund processed"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error or payment not refundable")
     * )
     */
    public function store(RefundRequest $request, Payment $payment): JsonResponse
    {
        try {
            $refund = $this->refundService->processRefund(
                $payment,
                $request->user(),
                $request->validated(),
            );

            return response()->json([
                'message' => 'Remboursement traité avec succès.',
                'refund' => [
                    'id' => $refund->id,
                    'amount' => $refund->amount,
                    'status' => $refund->status->value,
                    'is_partial' => $refund->is_partial,
                    'gateway_refund_id' => $refund->gateway_refund_id,
                    'side_effects_reversed' => $refund->side_effects_reversed,
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/admin/payments/{payment}/refunds",
     *     summary="List refunds for a payment (admin only)",
     *     tags={"Admin - Payments"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="payment", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=200, description="List of refunds")
     * )
     */
    public function index(Payment $payment): JsonResponse
    {
        $refunds = $payment->refunds()
            ->with('processedBy:id,firstname,lastname')
            ->latest()
            ->get();

        return response()->json(['data' => $refunds]);
    }
}
