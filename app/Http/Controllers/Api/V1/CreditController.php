<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Http\Resources\PointPackageResource;
use App\Models\Payment;
use App\Models\PointPackage;
use App\Services\FedaPayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(name="🎯 Crédits / Points", description="Gestion des packs de points et achats")
 */
final class CreditController
{
    public function __construct(protected FedaPayService $fedaPay) {}

    /**
     * List all active point packages.
     */
    public function packages(): AnonymousResourceCollection
    {
        $packages = PointPackage::active()->get();

        return PointPackageResource::collection($packages);
    }

    /**
     * Return the authenticated user's current point balance.
     */
    public function balance(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'point_balance' => (int) $user->point_balance,
        ]);
    }

    /**
     * Initiate a FedaPay purchase for a point package.
     */
    public function purchase(Request $request, PointPackage $package): JsonResponse
    {
        if (!$package->is_active) {
            return response()->json([
                'message' => 'Ce pack n\'est plus disponible.',
            ], 422);
        }

        $user = $request->user();

        $callbackUrl = $request->input(
            'callback_url',
            config('app.frontend_url', config('app.url')).'/credits/callback'
        );

        $paymentData = $this->fedaPay->createCreditPayment(
            $package->price,
            $user,
            $package->id,
            $callbackUrl,
        );

        if (!$paymentData['success']) {
            return response()->json([
                'message' => 'Erreur lors de l\'initialisation du paiement.',
                'error' => config('app.debug') ? ($paymentData['message'] ?? null) : null,
            ], 500);
        }

        try {
            Payment::create([
                'user_id' => $user->id,
                'amount' => $package->price,
                'transaction_id' => (string) $paymentData['transaction_id'],
                'status' => PaymentStatus::PENDING,
                'payment_method' => PaymentMethod::FEDAPAY,
                'type' => PaymentType::CREDIT,
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur création paiement crédits: '.$e->getMessage());

            return response()->json([
                'message' => 'Erreur technique lors de l\'initialisation.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }

        return response()->json([
            'payment_url' => $paymentData['url'],
            'message' => 'Redirigez l\'utilisateur vers cette URL pour payer.',
        ]);
    }
}
