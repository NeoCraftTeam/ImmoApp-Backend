<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Payment;

use App\Actions\HandlePostPaymentActions;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Exceptions\InvalidWebhookSignatureException;
use App\Exceptions\PaymentGatewayException;
use App\Http\Requests\Api\V1\InitiatePaymentRequest;
use App\Http\Requests\Api\V1\PaymentHistoryRequest;
use App\Http\Requests\Api\V1\PaymentReceiptPdfRequest;
use App\Http\Requests\Api\V1\VerifyPaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Jobs\ProcessPaymentWebhookJob;
use App\Models\Payment;
use App\Models\PromoCode;
use App\Models\PromoCodeUsage;
use App\Models\User;
use App\Services\Payment\PaymentService;
use App\Support\PaymentPresentation;
use App\Support\PaymentTransactionLookup;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(name="Paiements", description="Gestion des paiements (multi-gateway)")
 */
final class PaymentController
{
    /**
     * ISO 4217 codes accepted by {@see resolveVisitorLocalePdfHints()} mapped to a display symbol for PDF receipts.
     *
     * @var array<string, string>
     */
    private const array VISITOR_LOCALE_SYMBOL_BY_CCY = [
        'EUR' => '€',
        'USD' => '$',
        'CAD' => '$',
        'AUD' => '$',
        'MXN' => '$',
        'BRL' => '$',
        'GBP' => '£',
        'CHF' => 'CHF',
        'JPY' => '¥',
        'CNY' => '¥',
        'KRW' => '₩',
    ];

    public function __construct(
        protected HandlePostPaymentActions $postPaymentActions,
        protected PaymentService $paymentService,
    ) {}

    /**
     * Initiate a payment via the configured gateway.
     *
     * Intended for: subscription, credit purchases.
     * Returns a hosted checkout link to redirect the user.
     *
     * @OA\Post(
     *     path="/api/v1/payments/initiate_payment",
     *     summary="Initier un paiement",
     *     tags={"💰 Paiements"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="amount", type="number", example=150000),
     *             @OA\Property(property="type", type="string", example="credit"),
     *             @OA\Property(property="payment_method", type="string", example="mobile_money"),
     *             @OA\Property(property="phone_number", type="string", example="+237699000000")
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Lien de paiement retourné"),
     *     @OA\Response(response=401, description="Non authentifié"),
     *     @OA\Response(response=422, description="Validation échouée")
     * )
     */
    public function initiate(InitiatePaymentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        /** @var User $user */
        $user = $request->user();

        $type = $validated['type'];
        $amount = $this->paymentService->resolveAmountForType($type, $validated);

        if ($amount === null) {
            return response()->json([
                'message' => 'Impossible de déterminer le montant pour ce type de paiement.',
            ], 422);
        }

        // Wrap promo code validation + payment creation in a single transaction
        // to prevent race conditions on single-use promo codes.
        return DB::transaction(function () use ($validated, $user, $type, $amount): JsonResponse {
            $appliedPromoCode = null;
            $finalAmount = $amount;

            if (!empty($validated['promo_code'])) {
                $promoCode = PromoCode::where('code', strtoupper((string) $validated['promo_code']))
                    ->lockForUpdate()
                    ->first();

                if ($promoCode && $promoCode->isValidForUser($user, $type)) {
                    $finalAmount = max(0.0, $finalAmount - $promoCode->calculateDiscount($finalAmount));
                    $appliedPromoCode = $promoCode;
                }
            }

            $description = match ($type) {
                'subscription' => 'Abonnement agence',
                'credit' => 'Achat de crédits',
                default => 'Paiement KeyHome',
            };

            $result = $this->paymentService->createPayment($user, [
                'amount' => $finalAmount,
                'type' => $type,
                'payment_method' => $validated['payment_method'] ?? 'mobile_money',
                'phone_number' => $validated['phone_number'] ?? null,

                'agency_id' => $validated['agency_id'] ?? null,
                'plan_id' => $validated['plan_id'] ?? null,
                'period' => $validated['period'] ?? null,
                'description' => $description,
                'save_payment_method' => (bool) ($validated['save_payment_method'] ?? false),
                'payment_method_id' => isset($validated['payment_method_id']) && is_string($validated['payment_method_id']) && $validated['payment_method_id'] !== ''
                    ? $validated['payment_method_id']
                    : null,
                'meta' => [
                    'package_id' => ($type === 'credit') ? ($validated['plan_id'] ?? null) : null,
                ],
            ]);

            if ($appliedPromoCode !== null) {
                PromoCodeUsage::create([
                    'promo_code_id' => $appliedPromoCode->id,
                    'user_id' => $user->id,
                    'payment_id' => $result['payment']->id,
                ]);
                $appliedPromoCode->increment('used_count');
            }

            return response()->json([
                'reference' => $result['payment']->id,
                'payment_link' => $result['link'],
                'tx_ref' => $result['tx_ref'],
                'gateway' => $result['gateway'],
                // The orchestrator already mapped the gateway's reply onto
                // the internal Payment status (PENDING / SUCCESS / FAILED /
                // CANCELLED) when Stripe could short-circuit the flow
                // (saved card off-session). Surface the same value here
                // so the frontend can skip the verify poll on instant
                // success / failure.
                'status' => $result['status'],
                // Stripe-only: tells the frontend which SDK flow to use for
                // the `payment_link` secret ('checkout_session' or 'payment_intent').
                'stripe_flow' => $result['stripe_flow'] ?? null,
            ]);
        });
    }

    /**
     * Verify a payment after the user returns from checkout.
     *
     * @OA\Post(
     *     path="/api/v1/payments/verify_payment",
     *     summary="Vérifier un paiement",
     *     tags={"💰 Paiements"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="tx_ref", type="string", example="KH-ABCDEF123456")
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Statut du paiement"),
     *     @OA\Response(response=404, description="Paiement introuvable")
     * )
     */
    public function verify(VerifyPaymentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        /** @var User $user */
        $user = $request->user();

        $payment = PaymentTransactionLookup::findForUser(
            $user,
            $validated['tx_ref'] ?? null,
            $validated['reference'] ?? null,
        );

        if ($payment === null) {
            return response()->json(['message' => 'Paiement introuvable.'], 404);
        }

        $payment = $this->paymentService->syncPaymentStatus(
            $payment,
            $validated['reference'] ?? null,
        );

        if ($payment->isPaid()) {
            $this->postPaymentActions->execute($payment, (array) ($payment->gateway_response ?? []));
        }

        return response()->json([
            'status' => $payment->status->value,
            'is_paid' => $payment->isPaid(),
            'reference' => $payment->id,
            'ad_id' => $payment->ad_id,
            'tx_ref' => $payment->transaction_id,
            'gateway' => $payment->gateway,
            'payment_method' => $payment->payment_method?->value,
            'payment_method_label' => $payment->payment_method?->label(),
        ]);
    }

    /**
     * Public payment status — minimal payload, no authentication required.
     *
     * Returns ONLY the status (`pending` | `success` | `failed` | `cancelled`)
     * for a given `tx_ref`. Designed for the post-checkout callback page
     * (`/payment/return`, `/credits/callback`, `/payment-success`) where the user's session
     * cookie may have been lost during the cross-origin GeniusPay redirect.
     *
     * Security:
     *  - The `tx_ref` is opaque (`KH-XXXXXXXXXXXX`, ~62-bit entropy) and acts
     *    as a one-time capability — knowing it grants ONLY the right to read
     *    the payment status, never to modify it or read PII.
     *  - No user info, amount, payment method, gateway response, or any
     *    other detail is returned. Defense-in-depth against ID-enumeration.
     *  - Rate-limited (60/min per IP) to prevent brute-force of `tx_ref` space.
     *  - Always returns 200 with `status: 'unknown'` on miss to avoid
     *    distinguishing "exists" from "not exists" via HTTP status codes.
     *
     * @OA\Get(
     *     path="/api/v1/payments/{txRef}/public-status",
     *     summary="Statut public d'un paiement (sans auth)",
     *     tags={"💰 Paiements"},
     *
     *     @OA\Parameter(name="txRef", in="path", required=true, @OA\Schema(type="string", example="KH-ABCDEF123456")),
     *
     *     @OA\Response(response=200, description="Statut du paiement (jamais de PII)")
     * )
     */
    public function publicStatus(string $txRef): JsonResponse
    {
        // Hard-validate the format BEFORE hitting the DB so a flood of
        // malformed requests can't produce a SQL injection attempt against
        // the UUID-shaped column.
        $payment = PaymentTransactionLookup::findByPublicReference($txRef);

        if ($payment === null) {
            return response()->json(['status' => 'unknown']);
        }

        return response()->json([
            'status' => $payment->status->value,
        ]);
    }

    /**
     * Cancel a pending payment on user request.
     *
     * @OA\Post(
     *     path="/api/v1/payments/cancel_payment",
     *     summary="Annuler un paiement en attente",
     *     tags={"💰 Paiements"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="tx_ref", type="string", example="KH-ABCDEF123456")
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Paiement annulé"),
     *     @OA\Response(response=404, description="Paiement introuvable"),
     *     @OA\Response(response=409, description="Paiement déjà traité")
     * )
     */
    public function cancel(Request $request): JsonResponse
    {
        $request->validate([
            'tx_ref' => ['required', 'string'],
        ]);

        /** @var User $user */
        $user = $request->user();

        return DB::transaction(function () use ($user, $request): JsonResponse {
            $payment = Payment::where('transaction_id', $request->input('tx_ref'))
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (!$payment) {
                return response()->json(['message' => 'Paiement introuvable.'], 404);
            }

            if ($payment->isTerminal()) {
                return response()->json([
                    'message' => 'Ce paiement a déjà été traité.',
                    'status' => $payment->status->value,
                ], 409);
            }

            $payment->forceFill(['status' => PaymentStatus::CANCELLED])->save();

            Log::info('Payment cancelled by user', [
                'payment_id' => $payment->id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'message' => 'Paiement annulé avec succès.',
                'status' => 'cancelled',
            ]);
        });
    }

    /**
     * Handle a webhook from any supported payment gateway.
     * The {gateway} route parameter is validated by the route constraint.
     *
     * @OA\Post(
     *     path="/api/v1/webhooks/{gateway}",
     *     summary="Webhook passerelle de paiement",
     *     tags={"💰 Paiements"},
     *
     *     @OA\Parameter(name="gateway", in="path", required=true, @OA\Schema(type="string", enum={"geniuspay"})),
     *
     *     @OA\Response(response=200, description="Webhook traité"),
     *     @OA\Response(response=401, description="Signature invalide")
     * )
     */
    /**
     * Stripe webhook handler.
     *
     * Stripe requires the RAW body for signature verification — we feed
     * `getContent()` directly into the gateway, not `$request->all()`.
     * Otherwise verification would always fail because Laravel's parsed
     * payload doesn't byte-match what Stripe signed.
     *
     * @OA\Post(
     *     path="/api/v1/webhooks/stripe",
     *     summary="Webhook Stripe",
     *     tags={"💰 Paiements"},
     *
     *     @OA\Response(response=200, description="Webhook traité"),
     *     @OA\Response(response=400, description="Payload invalide"),
     *     @OA\Response(response=401, description="Signature invalide")
     * )
     */
    public function handleStripeWebhook(Request $request): JsonResponse
    {
        // Stripe sends ~10 event types per PaymentIntent lifecycle (created,
        // requires_action, processing, succeeded, …). Logging at info level
        // floods the production log; debug keeps the trace available locally
        // while letting `Log::warning/error` surface real incidents.
        Log::debug('--- WEBHOOK stripe START ---');

        $rawPayload = (string) $request->getContent();
        $signature = (string) $request->header('Stripe-Signature', '');

        if ($rawPayload === '' || $signature === '') {
            return response()->json(['status' => 'error', 'message' => 'Missing payload or signature'], 400);
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($rawPayload, true);
        if (!is_array($decoded)) {
            return response()->json(['status' => 'error', 'message' => 'Invalid JSON'], 400);
        }

        // `__raw` is consumed by `StripePaymentService::handleWebhook` for
        // signature verification — Laravel's parsed payload would fail.
        $decoded['__raw'] = $rawPayload;

        try {
            $data = $this->paymentService->processWebhook(
                $decoded,
                ['stripe-signature' => $signature],
                'stripe',
            );
        } catch (InvalidWebhookSignatureException) {
            return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 401);
        } catch (PaymentGatewayException|\Exception $e) {
            Log::error('Stripe webhook signature/parse error: '.$e->getMessage());

            return response()->json(['status' => 'error'], 500);
        }

        $txRef = (string) ($data['tx_ref'] ?? '');
        $event = (string) ($data['event'] ?? '');

        // Only push to the queue when the event is one we actually act on.
        // Stripe sends many event types (`payment_intent.created`, etc.); we
        // only want post-payment side-effects on terminal success events so
        // we don't redundantly schedule jobs that early-return.
        if ($txRef !== '' && in_array($event, [
            'payment_intent.succeeded',
            'checkout.session.completed',
            'checkout.session.async_payment_succeeded',
        ], true)) {
            ProcessPaymentWebhookJob::dispatch(
                $txRef,
                'stripe',
                (array) ($data['raw'] ?? []),
                $request->header('X-Request-ID'),
                $request->header('X-Correlation-ID'),
            );
        }

        return response()->json(['status' => 'ok']);
    }

    public function handleWebhook(Request $request, string $gateway): JsonResponse
    {
        Log::info("--- WEBHOOK {$gateway} START ---");

        // The `{gateway}` route is constrained to `geniuspay`; Stripe has its
        // own dedicated webhook endpoint (`handleStripeWebhook`).
        if ($gateway === 'geniuspay') {
            return $this->handleGeniusPayWebhook($request);
        }

        return response()->json(['status' => 'error', 'message' => 'Unsupported gateway'], 404);
    }

    private function handleGeniusPayWebhook(Request $request): JsonResponse
    {
        $rawPayload = (string) $request->getContent();
        if ($rawPayload === '') {
            return response()->json(['status' => 'error', 'message' => 'Missing payload'], 400);
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($rawPayload, true);
        if (!is_array($decoded)) {
            return response()->json(['status' => 'error', 'message' => 'Invalid JSON'], 400);
        }

        $headers = [
            'X-Webhook-Signature' => (string) $request->header('X-Webhook-Signature', ''),
            'X-Webhook-Timestamp' => (string) $request->header('X-Webhook-Timestamp', ''),
            'X-Webhook-Event' => (string) $request->header('X-Webhook-Event', ''),
        ];

        try {
            $data = $this->paymentService->processWebhook($decoded, $headers, 'geniuspay');
            $txRef = (string) ($data['tx_ref'] ?? '');
        } catch (InvalidWebhookSignatureException) {
            return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 401);
        } catch (PaymentGatewayException|\Exception $e) {
            Log::error('geniuspay webhook signature/parse error: '.$e->getMessage());

            return response()->json(['status' => 'error'], 500);
        }

        if ($txRef !== '' && in_array((string) ($data['event'] ?? ''), ['payment.success'], true)) {
            ProcessPaymentWebhookJob::dispatch(
                $txRef,
                'geniuspay',
                (array) ($data['raw'] ?? []),
                $request->header('X-Request-ID'),
                $request->header('X-Correlation-ID'),
            );
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Return the authenticated user's payment history.
     *
     * @OA\Get(
     *     path="/api/v1/payments/history",
     *     summary="Historique des paiements",
     *     tags={"💰 Paiements"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Liste paginée des transactions")
     * )
     */
    public function history(PaymentHistoryRequest $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        /** @var array{page?: int, per_page?: int} $validated */
        $validated = $request->validated();
        $perPage = min(max((int) ($validated['per_page'] ?? 10), 1), 50);

        $payments = Payment::query()
            ->where('user_id', $user->id)
            ->with('pointPackage')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return PaymentResource::collection($payments);
    }

    /**
     * Export the authenticated user's full payment history as a branded PDF.
     *
     * @OA\Get(
     *     path="/api/v1/payments/export",
     *     summary="Exporter l'historique des paiements en PDF",
     *     tags={"💰 Paiements"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="period", in="query", required=false,
     *
     *         @OA\Schema(type="integer", enum={30, 90, 365}, description="Nb de jours à inclure. Omis = tout l'historique.")),
     *
     *     @OA\Response(response=200, description="Fichier PDF téléchargeable",
     *
     *         @OA\MediaType(mediaType="application/pdf")),
     *
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function export(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $period = $request->integer('period', 0);

        [
            'localeCurrency' => $localeCurrency,
            'localeRate' => $localeRate,
            'localeSymbol' => $localeSymbol,
        ] = $this->resolveVisitorLocalePdfHints($request);

        $query = Payment::where('user_id', $user->id)
            ->with('pointPackage', 'ad')
            ->orderByDesc('created_at');

        if ($period > 0) {
            $query->where('created_at', '>=', now()->subDays($period));
        }

        $payments = $query->get();

        $paidPayments = $payments->filter(fn (Payment $p) => $p->status === PaymentStatus::SUCCESS);

        $totalAmount = $paidPayments->sum(fn (Payment $p) => (float) $p->amount);
        $creditsEarned = $paidPayments->sum(
            fn (Payment $p) => $p->pointPackage->points_awarded ?? 0
        );

        $periodLabel = match ($period) {
            30 => '30 derniers jours',
            90 => '90 derniers jours',
            365 => 'Cette année',
            default => 'Tout l\'historique',
        };

        $logoPath = public_path('images/keyhomelogo_transparent.png');
        $logoBase64 = file_exists($logoPath)
            ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath))
            : null;

        $pdf = Pdf::loadView('pdf.payment-history', [
            'user' => $user,
            'payments' => $payments,
            'totalAmount' => $totalAmount,
            'totalCount' => $payments->count(),
            'paidCount' => $paidPayments->count(),
            'creditsEarned' => $creditsEarned,
            'periodLabel' => $periodLabel,
            'generatedAt' => now()->format('d/m/Y à H:i'),
            'logoBase64' => $logoBase64,
            'localeCurrency' => $localeCurrency,
            'localeRate' => $localeRate,
            'localeSymbol' => $localeSymbol,
        ])
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
                'defaultFont' => 'DejaVu Sans',
            ]);

        return $pdf->download('keyhome-paiements-'.now()->format('Y-m-d').'.pdf');
    }

    /**
     * Export a single payment as a printable PDF receipt (same auth as history/export).
     *
     * @OA\Get(
     *     path="/api/v1/payments/{payment}/receipt",
     *     summary="Reçu PDF d'une transaction",
     *     tags={"💰 Paiements"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="PDF"),
     *     @OA\Response(response=403, description="Interdit"),
     *     @OA\Response(response=404, description="Introuvable")
     * )
     */
    public function receipt(PaymentReceiptPdfRequest $request, Payment $payment): Response
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($payment->user_id === $user->id, 403);

        [
            'localeCurrency' => $localeCurrency,
            'localeRate' => $localeRate,
            'localeSymbol' => $localeSymbol,
        ] = $this->resolveVisitorLocalePdfHints($request);

        $payment->loadMissing('pointPackage', 'ad');

        $presentation = PaymentPresentation::forPayment($payment);

        $typeLabel = match ($payment->type) {
            PaymentType::UNLOCK => 'Déblocage',
            PaymentType::SUBSCRIPTION => 'Abonnement',
            PaymentType::BOOST => 'Boost',
            PaymentType::CREDIT => 'Crédits',
        };

        $logoPath = public_path('images/keyhomelogo_transparent.png');
        $logoBase64 = file_exists($logoPath)
            ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath))
            : null;

        $pdf = Pdf::loadView('pdf.payment-receipt', [
            'user' => $user,
            'payment' => $payment,
            'presentation' => $presentation,
            'typeLabel' => $typeLabel,
            'generatedAt' => now()->format('d/m/Y à H:i'),
            'logoBase64' => $logoBase64,
            'localeCurrency' => $localeCurrency,
            'localeRate' => $localeRate,
            'localeSymbol' => $localeSymbol,
        ])
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
                'defaultFont' => 'DejaVu Sans',
            ]);

        $safeRef = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $payment->transaction_id) ?? 'recu';

        return $pdf->stream('keyhome-recu-'.$safeRef.'.pdf');
    }

    /**
     * @return array{
     *     localeCurrency: string|null,
     *     localeRate: float|null,
     *     localeSymbol: string|null
     * }
     */
    private function resolveVisitorLocalePdfHints(Request $request): array
    {
        $allowedCurrencies = ['EUR', 'USD', 'GBP', 'CHF', 'CAD', 'JPY', 'MXN', 'BRL', 'CNY', 'AUD', 'KRW'];
        $rawCurrency = strtoupper((string) $request->query('currency', ''));
        $rawRate = (float) $request->query('rate', 0);
        $useLocale = in_array($rawCurrency, $allowedCurrencies, true)
            && is_finite($rawRate)
            && $rawRate > 0;

        if (!$useLocale) {
            return [
                'localeCurrency' => null,
                'localeRate' => null,
                'localeSymbol' => null,
            ];
        }

        return [
            'localeCurrency' => $rawCurrency,
            'localeRate' => $rawRate,
            'localeSymbol' => self::VISITOR_LOCALE_SYMBOL_BY_CCY[$rawCurrency],
        ];
    }
}
