<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\HandlePostPaymentActions;
use App\Enums\PaymentStatus;
use App\Exceptions\InvalidWebhookSignatureException;
use App\Exceptions\PaymentGatewayException;
use App\Http\Requests\Api\V1\FlutterwaveInitiateRequest;
use App\Http\Requests\Api\V1\FlutterwaveVerifyRequest;
use App\Http\Resources\PaymentResource;
use App\Jobs\ProcessFlutterwaveWebhookJob;
use App\Models\Payment;
use App\Models\PromoCode;
use App\Models\PromoCodeUsage;
use App\Models\User;
use App\Services\Payment\PaymentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(name="Paiements", description="Gestion des paiements (multi-gateway)")
 */
final class PaymentController
{
    public function __construct(
        protected HandlePostPaymentActions $postPaymentActions,
        protected PaymentService $paymentService,
    ) {}

    /**
     * Initiate a Flutterwave payment.
     *
     * Intended for: subscription, credit purchases.
     * Returns a hosted checkout link to redirect the user.
     *
     * @OA\Post(
     *     path="/api/v1/payments/flutterwave/initiate",
     *     summary="Initier un paiement Flutterwave",
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
    public function initiate(FlutterwaveInitiateRequest $request): JsonResponse
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
                'payment_method' => $validated['payment_method'] ?? 'flutterwave',
                'phone_number' => $validated['phone_number'] ?? null,

                'agency_id' => $validated['agency_id'] ?? null,
                'plan_id' => $validated['plan_id'] ?? null,
                'period' => $validated['period'] ?? null,
                'description' => $description,
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
                'status' => 'pending',
            ]);
        });
    }

    /**
     * Verify a Flutterwave payment after the user returns from checkout.
     *
     * @OA\Post(
     *     path="/api/v1/payments/flutterwave/verify",
     *     summary="Vérifier un paiement Flutterwave",
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
    public function verify(FlutterwaveVerifyRequest $request): JsonResponse
    {
        $validated = $request->validated();

        /** @var User $user */
        $user = $request->user();

        $payment = Payment::where('transaction_id', $validated['tx_ref'])
            ->where('user_id', $user->id)
            ->first();

        if (!$payment) {
            return response()->json(['message' => 'Paiement introuvable.'], 404);
        }

        $payment = $this->paymentService->syncPaymentStatus($payment);

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
     * (`/credits/callback`, `/payment-success`) where the user's session
     * cookie may have been lost during the cross-origin Flutterwave redirect.
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
        if (!preg_match('/^KH-[A-Z0-9]{6,32}$/i', $txRef)) {
            return response()->json(['status' => 'unknown']);
        }

        /** @var Payment|null $payment */
        $payment = Payment::query()
            ->where('transaction_id', $txRef)
            ->first();

        if ($payment === null) {
            return response()->json(['status' => 'unknown']);
        }

        return response()->json([
            'status' => $payment->status->value,
        ]);
    }

    /**
     * Cancel a pending Flutterwave payment on user request.
     *
     * @OA\Post(
     *     path="/api/v1/payments/flutterwave/cancel",
     *     summary="Annuler un paiement Flutterwave en attente",
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
     *     @OA\Parameter(name="gateway", in="path", required=true, @OA\Schema(type="string", enum={"flutterwave"})),
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
        if ($txRef !== '' && $event === 'payment_intent.succeeded') {
            ProcessFlutterwaveWebhookJob::dispatch(
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

        $payload = $request->all();
        $headers = [
            'verif-hash' => (string) $request->header('verif-hash', ''),
            'HTTP_VERIF_HASH' => (string) $request->header('verif-hash', ''),
            'flutterwave-signature' => (string) $request->header('flutterwave-signature', ''),
        ];

        // 1. Verify signature synchronously — fast hash check, must reply to Flutterwave quickly.
        try {
            $data = $this->paymentService->processWebhook($payload, $headers, $gateway);
            $txRef = (string) ($data['tx_ref'] ?? '');
        } catch (InvalidWebhookSignatureException) {
            return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 401);
        } catch (PaymentGatewayException|\Exception $e) {
            Log::error("{$gateway} webhook signature/parse error: ".$e->getMessage());

            return response()->json(['status' => 'error'], 500);
        }

        // 2. Dispatch heavy DB work + post-payment actions to the queue.
        //    PHP-FPM worker is released immediately; Flutterwave gets its 200 in < 200 ms.
        if ($txRef !== '') {
            ProcessFlutterwaveWebhookJob::dispatch(
                $txRef,
                $gateway,
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
    public function history(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $payments = Payment::where('user_id', $user->id)
            ->with('pointPackage')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'data' => PaymentResource::collection($payments),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
            ],
        ]);
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

        // Visitor's locale currency (CHF / EUR / USD…) and the per-XAF rate
        // — both come from the frontend's `useCurrency()` so the PDF can
        // render the local converted amount as primary with the canonical
        // FCFA as a reference subtitle. We accept only ISO codes from a
        // fixed allow-list and a positive finite rate; anything else falls
        // back to FCFA-only display (no conversion).
        $allowedCurrencies = ['EUR', 'USD', 'GBP', 'CHF', 'CAD', 'JPY', 'MXN', 'BRL', 'CNY', 'AUD', 'KRW'];
        $rawCurrency = strtoupper((string) $request->query('currency', ''));
        $rawRate = (float) $request->query('rate', 0);
        $useLocale = in_array($rawCurrency, $allowedCurrencies, true)
            && is_finite($rawRate)
            && $rawRate > 0;
        $localeCurrency = $useLocale ? $rawCurrency : null;
        $localeRate = $useLocale ? $rawRate : null;
        $localeSymbol = $useLocale ? match ($rawCurrency) {
            'EUR' => '€',
            'USD', 'CAD', 'AUD', 'MXN', 'BRL' => '$',
            'GBP' => '£',
            'CHF' => 'CHF',
            'JPY', 'CNY' => '¥',
            'KRW' => '₩',
            default => $rawCurrency,
        } : null;

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
}
