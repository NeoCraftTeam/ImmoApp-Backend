<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\PaymentGatewayInterface;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Events\PaymentFailed;
use App\Events\PaymentInitiated;
use App\Events\PaymentSucceeded;
use App\Exceptions\PaymentGatewayException;
use App\Exceptions\StripeCustomerMissingException;
use App\Models\Payment;
use App\Models\PointPackage;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\FrontendRedirectGuard;
use App\Support\PaymentTransactionLookup;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Central orchestrator for all payment operations.
 *
 * Delegates to the configured gateway. Supports automatic fallback to a secondary
 * gateway when the primary fails.
 */
final readonly class PaymentService
{
    /** @var array<string, PaymentGatewayInterface> */
    private array $registry;

    /**
     * @param  array<string, PaymentGatewayInterface>  $registry  Map of gateway name (lower-case, matches enum value) to instance.
     *                                                            Empty by default; populated by `AppServiceProvider` so the service
     *                                                            can route any `PaymentMethod` to its `gateway()` without N if/else branches.
     *                                                            `readonly` forbids in-place mutation so we agregate locally first.
     */
    public function __construct(
        private PaymentGatewayInterface $gateway,
        private ?PaymentGatewayInterface $fallbackGateway = null,
        array $registry = [],
    ) {
        // Make sure the primary + fallback are always reachable through
        // the registry so `processWebhook()` and `syncPaymentStatus()` can
        // delegate to the gateway that originally handled the payment.
        $registry[$this->gateway->getName()] ??= $this->gateway;
        if ($this->fallbackGateway !== null) {
            $registry[$this->fallbackGateway->getName()] ??= $this->fallbackGateway;
        }
        $this->registry = $registry;
    }

    /**
     * Create a pending payment record and obtain the checkout link.
     *
     * Stripe-only options (silently ignored by other gateways):
     *  - `save_payment_method` (bool) → asks Stripe to attach the card to
     *    the Customer on success so it can be re-charged off-session later.
     *  - `payment_method_id` (`pm_xxx`) → reuse a previously saved card.
     *    The PaymentIntent is confirmed server-side, off-session ;
     *    `initiateWithFallback()` may return `status === 'success'`
     *    (instant fulfilment), `'requires_action'` (3DS), or `'failed'`.
     *
     * @param  array{
     *     amount: float,
     *     currency?: string,
     *     type: string,
     *     payment_method?: string,
     *     phone_number?: string,
     *     ad_id?: string|null,
     *     agency_id?: string|null,
     *     plan_id?: string|null,
     *     period?: string|null,
     *     description?: string,
     *     redirect_url?: string|null,
     *     stripe_hosted?: bool,
     *     save_payment_method?: bool,
     *     payment_method_id?: string|null,
     *     meta?: array<string, mixed>
     * } $data
     * @return array{payment: Payment, link: string, tx_ref: string, gateway: string, status: string, stripe_flow: string|null}
     */
    public function createPayment(User $user, array $data): array
    {
        $txRef = 'KH-'.strtoupper(Str::random(12));
        $currency = $data['currency'] ?? config('payment.default_currency', 'XAF');
        $meta = array_merge($data['meta'] ?? [], [
            'payment_type' => $data['type'],
            'user_id' => $user->id,
            'ad_id' => $data['ad_id'] ?? null,
            'agency_id' => $data['agency_id'] ?? null,
            'plan_id' => $data['plan_id'] ?? null,
            'period' => $data['period'] ?? null,
        ]);

        $redirectUrl = $data['redirect_url'] ?? null;
        $redirectUrl = is_string($redirectUrl) && $redirectUrl !== '' ? $redirectUrl : null;

        // Les passerelles hosted-checkout (Kpay/Stripe…) exigent une URL
        // http(s) pour returnUrl/cancelUrl. Un deep-link mobile
        // (keyhome://, keyhomeowners://, exp://…) est rejeté par la passerelle :
        // on l'enveloppe dans un pont (payment.native-return) qui renverra un
        // 302 vers le deep-link une fois le paiement terminé — l'onglet in-app
        // se ferme alors nativement et l'app reprend la main. Un schéma inconnu
        // (ni http(s), ni whitelisté) est ignoré → repli sur l'URL web de retour.
        if ($redirectUrl !== null && preg_match('#^https?://#i', $redirectUrl) !== 1) {
            $redirectUrl = FrontendRedirectGuard::isAllowedAppScheme($redirectUrl)
                ? self::buildNativeReturnBridgeUrl($redirectUrl)
                : null;
        }

        if ($redirectUrl === null) {
            $configured = config('payment.gateways.kpay.redirect_url');
            $redirectUrl = (is_string($configured) && $configured !== '')
                ? $configured
                : $this->defaultFrontendPaymentReturnUrl(
                    (string) $data['type'],
                    self::stringOrNull($data['ad_id'] ?? null),
                );
        }

        $redirectUrl = self::appendTxRefToReturnUrl($redirectUrl, $txRef);

        $gatewayPayload = [
            'amount' => $data['amount'],
            'currency' => $currency,
            'email' => $user->email,
            'phone' => $data['phone_number'] ?? ($user->phone_number ?? ''),
            'name' => trim(($user->firstname ?? '').' '.($user->lastname ?? '')) ?: $user->email,
            'tx_ref' => $txRef,
            'redirect_url' => $redirectUrl,
            'description' => $data['description'] ?? 'Paiement KeyHome',
            'payment_method' => $data['payment_method'] ?? 'mobile_money',
            // Force la Checkout Session Stripe hébergée (URL) pour les clients
            // sans Stripe.js/SDK (apps mobiles) — sinon Stripe renvoie un
            // client_secret inutilisable côté natif.
            'stripe_hosted' => (bool) ($data['stripe_hosted'] ?? false),
            'meta' => $meta,
        ];

        // Stripe-only options. `customer_id` is resolved lazily through
        // Cashier so users who never paid by card stay free of a Stripe
        // Customer record until they opt in.
        $savePaymentMethod = (bool) ($data['save_payment_method'] ?? false);
        $paymentMethodId = self::stringOrNull($data['payment_method_id'] ?? null);
        $needsStripeCustomer = $savePaymentMethod || $paymentMethodId !== null;
        $isStripeFlow = ($data['payment_method'] ?? null) === PaymentMethod::CARD->value;

        if ($isStripeFlow && $needsStripeCustomer) {
            // Avoid the unnecessary `customers.retrieve` round-trip
            // performed by Cashier's `createOrGetStripeCustomer()` when
            // the user already has a stored `stripe_id`. We only need
            // the id string ; creating one if missing covers the
            // first-time card flow.
            $gatewayPayload['customer_id'] = $user->hasStripeId()
                ? (string) $user->stripeId()
                : (string) $user->createAsStripeCustomer()->id;
            $gatewayPayload['save_payment_method'] = $savePaymentMethod;
            $gatewayPayload['payment_method_id'] = $paymentMethodId;
        }

        // Route to the gateway implied by the chosen `payment_method`. If
        // the method is unknown or its gateway isn't registered, we fall
        // back to the legacy default (Kpay). Mobile money +
        // Orange Money → Kpay; Card → Stripe.
        $primaryGateway = $this->resolveGatewayForMethod($data['payment_method'] ?? null);

        try {
            [$result, $usedGateway] = $this->initiateWithFallback($gatewayPayload, $primaryGateway);
        } catch (StripeCustomerMissingException $e) {
            // stripe_id périmé (Customer supprimé ou créé avec d'anciennes
            // clés) : la carte enregistrée est partie avec l'ancien Customer.
            // Auto-réparation : oublier l'id local, recréer un Customer neuf
            // et relancer UNE fois en saisie de carte classique (sans
            // payment_method_id, invalide par construction).
            if (!isset($gatewayPayload['customer_id'])) {
                throw $e;
            }

            Log::info('Paiement Stripe: stripe_id périmé, recréation du Customer et relance', [
                'user_id' => $user->id,
            ]);

            $user->forceFill(['stripe_id' => null])->save();
            $gatewayPayload['customer_id'] = (string) $user->createAsStripeCustomer()->id;
            $gatewayPayload['payment_method_id'] = null;

            [$result, $usedGateway] = $this->initiateWithFallback($gatewayPayload, $primaryGateway);
        }

        $initialStatus = match ($result['status']) {
            'success' => PaymentStatus::SUCCESS,
            'failed' => PaymentStatus::FAILED,
            'cancelled' => PaymentStatus::CANCELLED,
            default => PaymentStatus::PENDING,
        };

        $payment = DB::transaction(function () use ($data, $txRef, $result, $user, $usedGateway, $initialStatus): Payment {
            $payment = new Payment;
            $payment->type = PaymentType::from((string) $data['type']);
            $payment->amount = (int) round((float) $data['amount']);
            $payment->transaction_id = $txRef;
            $payment->payment_method = PaymentMethod::from((string) ($data['payment_method'] ?? 'mobile_money'));
            $payment->user_id = $user->id;
            $payment->status = $initialStatus;
            $payment->gateway = $usedGateway->getName();
            $payment->payment_link = $result['link'];
            if (isset($result['raw'])) {
                $payment->gateway_response = $result['raw'];
            }
            $payment->phone_number = $data['phone_number'] ?? null;
            $payment->ad_id = $data['ad_id'] ?? null;
            $payment->agency_id = $data['agency_id'] ?? null;
            $payment->plan_id = $data['plan_id'] ?? null;
            $payment->period = $data['period'] ?? null;
            $payment->save();

            PaymentInitiated::dispatch($payment);

            return $payment;
        });

        // Off-session confirm path : Stripe already settled the intent so
        // we can dispatch the terminal event immediately. Webhook arrival
        // is still expected and remains idempotent (the orphan-debit guard
        // in `processWebhook()` ignores duplicate SUCCESS/FAILED states).
        if ($initialStatus === PaymentStatus::SUCCESS) {
            PaymentSucceeded::dispatch($payment);
        } elseif ($initialStatus === PaymentStatus::FAILED) {
            PaymentFailed::dispatch($payment);
        }

        Log::info('Payment created', [
            'payment_id' => $payment->id,
            'gateway' => $usedGateway->getName(),
            'amount' => $data['amount'],
            'user_id' => $user->id,
            'tx_ref' => $txRef,
            'initial_status' => $initialStatus->value,
        ]);

        return [
            'payment' => $payment,
            'link' => $result['link'],
            'tx_ref' => $txRef,
            'gateway' => $usedGateway->getName(),
            'status' => $result['status'],
            'stripe_flow' => $result['stripe_flow'] ?? null,
        ];
    }

    /**
     * Verify a payment by its internal tx_ref and update its status.
     */
    public function verifyByTxRef(string $txRef): Payment
    {
        // No gateway constraint here — a tx_ref is unique per Payment, and
        // restricting by `$this->gateway->getName()` would break verification
        // for Stripe-issued payments when Kpay is the default gateway.
        $payment = Payment::where('transaction_id', $txRef)->firstOrFail();

        return $this->syncPaymentStatus($payment);
    }

    /**
     * Verify a payment model instance and sync its status.
     *
     * Uses a DB lock to prevent race conditions with concurrent webhook processing.
     */
    public function syncPaymentStatus(Payment $payment, ?string $gatewayReferenceOverride = null): Payment
    {
        // Re-query Kpay when locally FAILED/CANCELLED — sandbox may have
        // completed after an early verify (wrong ref or race). SUCCESS/REFUNDED
        // are not re-opened here (webhook duplicate guard applies there).
        if ($payment->isPaid() || $payment->isRefunded()) {
            return $payment;
        }

        if (!$payment->transaction_id) {
            return $payment;
        }

        // Verify with the gateway that originally handled the payment, not
        // the default one. This is critical now that we run multiple
        // gateways simultaneously (Kpay + Stripe).
        $gatewayName = (string) ($payment->gateway ?? $this->gateway->getName());
        $verifyingGateway = $this->resolveGateway($gatewayName);

        // For Stripe, prefer the stored `payment_link` so the gateway can
        // retrieve the resource directly (instant, no indexing lag).
        // Supported formats:
        //   - `pi_xxx_secret_yyy`  (PaymentIntent)   → paymentIntents.retrieve()
        //   - `cs_xxx_secret_yyy`  (CheckoutSession)  → checkout.sessions.retrieve()
        // Falling back to tx_ref forces a paymentIntents.search() which has
        // a ~1 min indexing lag on Stripe's side.
        $reference = match ($gatewayName) {
            'stripe' => !empty($payment->payment_link)
                ? (string) $payment->payment_link
                : (string) $payment->transaction_id,
            'kpay' => self::resolveKpayVerifyReference($payment, $gatewayReferenceOverride),
            default => (string) $payment->transaction_id,
        };

        $result = $verifyingGateway->verify($reference);

        $expectedCurrency = config('payment.default_currency', 'XAF');

        return DB::transaction(function () use ($payment, $result, $expectedCurrency, $gatewayName, $gatewayReferenceOverride): Payment {
            /** @var Payment $locked */
            $locked = Payment::where('id', $payment->id)->lockForUpdate()->first();

            if ($locked->isPaid() || $locked->isRefunded()) {
                return $locked;
            }

            if ($locked->isTerminal()) {
                $isOrphanDebit = $result['status'] === 'success'
                    && in_array($locked->status, [PaymentStatus::CANCELLED, PaymentStatus::FAILED], true);

                if (!$isOrphanDebit) {
                    return $locked;
                }

                Log::critical('Verify: orphan debit detected — gateway succeeded after local terminal state', [
                    'payment_id' => $locked->id,
                    'tx_ref' => $locked->transaction_id,
                    'gateway' => $gatewayName,
                    'previous_status' => $locked->status->value,
                    'gateway_amount' => $result['amount'],
                    'gateway_currency' => $result['currency'],
                ]);
            }

            if ($result['status'] === 'success') {
                $paidAmount = (float) $result['amount'];
                $paidCurrency = (string) $result['currency'];

                // Tolerance for round-trip XAF→EUR→XAF conversion: Stripe rounds to
                // whole cents, so converting back can lose up to ~7 XAF (one EUR cent
                // ≈ 6.56 XAF at the BEAC peg). The current Stripe path pins the
                // original XAF amount in PaymentIntent.metadata.xaf_amount and we
                // read it back precisely, so this tolerance is purely defensive
                // (legacy rows / future gateways with similar precision quirks).
                $allowedDelta = 10.0;
                if (abs($paidAmount - (float) $locked->amount) > $allowedDelta
                    || !self::ledgerCurrencyMatches((string) $expectedCurrency, $paidCurrency, $gatewayName)) {
                    Log::critical('Payment amount/currency mismatch', [
                        'payment_id' => $locked->id,
                        'expected_amount' => $locked->amount,
                        'received_amount' => $paidAmount,
                        'expected_currency' => $expectedCurrency,
                        'received_currency' => $paidCurrency,
                    ]);

                    $locked->forceFill([
                        'status' => PaymentStatus::FAILED,
                        'gateway_response' => $result['raw'],
                    ])->save();

                    PaymentFailed::dispatch($locked->fresh() ?? $locked);

                    return $locked->fresh() ?? $locked;
                }

                $gatewayResponse = $result['raw'];
                if ($gatewayName === 'kpay' && is_string($gatewayReferenceOverride) && $gatewayReferenceOverride !== '') {
                    $gatewayResponse['kpay_id'] = $gatewayReferenceOverride;
                }

                $updateData = [
                    'status' => PaymentStatus::SUCCESS,
                    'gateway_response' => $gatewayResponse,
                ];

                // Update payment_method from gateway resolution (e.g. orange_money, mobile_money, card)
                if (!empty($result['payment_method'])) {
                    $resolvedMethod = PaymentMethod::tryFrom($result['payment_method']);
                    if ($resolvedMethod instanceof PaymentMethod) {
                        $updateData['payment_method'] = $resolvedMethod;
                    }
                }

                $locked->forceFill($updateData)->save();

                Log::info('Payment verified as success', [
                    'payment_id' => $locked->id,
                    'gateway' => $locked->gateway,
                ]);

                PaymentSucceeded::dispatch($locked->fresh() ?? $locked);
            } elseif ($result['status'] === 'cancelled' && $locked->status === PaymentStatus::PENDING) {
                $locked->forceFill([
                    'status' => PaymentStatus::CANCELLED,
                    'gateway_response' => $result['raw'],
                ])->save();

                PaymentFailed::dispatch($locked->fresh() ?? $locked);
            } elseif ($result['status'] === 'failed' && $locked->status === PaymentStatus::PENDING) {
                $locked->forceFill([
                    'status' => PaymentStatus::FAILED,
                    'gateway_response' => $result['raw'],
                ])->save();

                PaymentFailed::dispatch($locked->fresh() ?? $locked);
            }

            return $locked->fresh() ?? $locked;
        });
    }

    /**
     * Apply a hosted-checkout redirect hint for terminal failure states only.
     *
     * Never promotes pending → success (that would allow free credits). Safe to
     * mark failed/cancelled when the gateway redirect URL says so.
     */
    public function applySafeRedirectTerminalHint(Payment $payment, ?string $redirectStatus): Payment
    {
        if ($redirectStatus === null || trim($redirectStatus) === '') {
            return $payment;
        }

        if ($payment->status !== PaymentStatus::PENDING) {
            return $payment;
        }

        $normalized = strtolower(trim($redirectStatus));

        $terminalHint = match (true) {
            in_array($normalized, ['cancelled', 'canceled'], true) => PaymentStatus::CANCELLED,
            in_array($normalized, ['failed', 'declined', 'error', 'expired'], true) => PaymentStatus::FAILED,
            default => null,
        };

        if ($terminalHint === null) {
            return $payment;
        }

        // Conditional UPDATE: the in-memory PENDING check above can be stale —
        // a webhook may have committed SUCCESS since this row was loaded, and
        // an unconditional save() would overwrite a settled payment. The
        // WHERE clause makes the transition pending→terminal atomic; zero
        // rows affected means someone else already resolved it.
        $updated = Payment::query()
            ->whereKey($payment->id)
            ->where('status', PaymentStatus::PENDING)
            ->update(['status' => $terminalHint]);

        if ($updated === 1) {
            PaymentFailed::dispatch($payment->fresh() ?? $payment);
        }

        return $payment->fresh() ?? $payment;
    }

    /**
     * Process an incoming webhook from a specific gateway.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $headers
     * @return array<string, mixed> Normalised webhook data from the gateway's handleWebhook
     */
    public function processWebhook(array $payload, array $headers, string $gatewayName, ?string $rawBody = null): array
    {
        $gateway = $this->resolveGateway($gatewayName);
        $data = $gateway->handleWebhook($payload, $headers, $rawBody);

        $txRef = $data['tx_ref'];

        // ── Event-ID deduplication ──────────────────────────────────────
        // Stripe and Kpay both retry on non-200 responses. The
        // orchestrator's row-lock + terminal-state guards prevent
        // double-credit on simultaneous retries within a single
        // request lifecycle, but a retry that arrives minutes later
        // (after the payment is already SUCCESS) would normally
        // still hit the gateway service, the DB lookup, the
        // transaction begin, and only then short-circuit on the
        // terminal-state guard. With many retries on a single event,
        // that adds load and noise.
        //
        // Cache the event id for 24 h post-success — well past
        // Stripe's 3-day retry window and Kpay's hours-long
        // schedule. A second processWebhook call for the same event
        // returns the cached payload without re-running side effects.
        $eventId = $data['event_id'] ?? null;
        if (is_string($eventId) && $eventId !== '') {
            $cacheKey = 'payment_webhook_event:'.$gatewayName.':'.$eventId;
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                Log::info('Webhook replay short-circuit (event already processed)', [
                    'gateway' => $gatewayName,
                    'event_id' => $eventId,
                    'tx_ref' => $txRef,
                ]);

                return $cached;
            }
        }

        // CRITICAL: lockForUpdate must run inside a DB::transaction() so the row
        // lock is held until commit. Without this, two concurrent webhooks can both
        // observe a non-terminal payment and trigger duplicate side-effects.
        $eventToDispatch = DB::transaction(function () use ($txRef, $gatewayName, $data): array {
            /** @var Payment|null $payment */
            $payment = Payment::where('transaction_id', $txRef)
                ->where('gateway', $gatewayName)
                ->lockForUpdate()
                ->first();

            if (!$payment) {
                Log::warning('Webhook: payment not found', ['tx_ref' => $txRef, 'gateway' => $gatewayName]);

                // `payment_missing` lets the controller answer non-2xx so the
                // provider retries later: a webhook can outrun the DB commit
                // of createPayment (the row is inserted AFTER initiate()),
                // and a 200 here would acknowledge the event forever.
                return ['event' => null, 'payment_missing' => true];
            }

            // Orphan-debit guard: a payment may already be terminal (legitimately
            // SUCCESS, or marked CANCELLED/FAILED locally after a UI cancel /
            // multi-tab race) when the gateway later confirms a real charge.
            //
            //   - terminal SUCCESS + incoming success  → genuine duplicate, ignore
            //   - terminal SUCCESS + incoming failure  → ignore (we already
            //     fulfilled, gateway can't retroactively retract without refund)
            //   - terminal CANCELLED/FAILED + success  → MONEY MOVED but our row
            //     says no fulfilment: log critical, flip to SUCCESS, let
            //     post-payment actions run so the customer actually gets what
            //     they paid for. Support is alerted via the critical log.
            if ($payment->isTerminal()) {
                $isOrphanDebit = $data['status'] === 'success'
                    && in_array($payment->status, [PaymentStatus::CANCELLED, PaymentStatus::FAILED], true);

                if (!$isOrphanDebit) {
                    Log::info('Webhook ignoré: Paiement #'.$payment->id.' déjà traité (status: '.$payment->status->value.').');

                    return ['event' => null];
                }

                Log::critical('Webhook: orphan debit detected — gateway succeeded after local terminal state', [
                    'payment_id' => $payment->id,
                    'tx_ref' => $payment->transaction_id,
                    'gateway' => $gatewayName,
                    'previous_status' => $payment->status->value,
                    'gateway_event' => $data['event'],
                    'gateway_amount' => $data['amount'],
                    'gateway_currency' => $data['currency'],
                ]);
                // Fall through to the success branch below — `isTerminal()` is
                // re-evaluated only at the start of the closure, so the rest
                // of the success path will run normally.
            }

            $expectedCurrency = config('payment.default_currency', 'XAF');

            if ($data['status'] === 'success') {
                $paidAmount = (float) $data['amount'];
                $paidCurrency = (string) $data['currency'];

                // Same tolerance rationale as `syncPaymentStatus` — see the
                // comment there. Stripe round-trip XAF↔EUR cents loses up to
                // ~7 XAF per transaction; we accept a 10 XAF window to keep
                // legitimate charges from being marked FAILED.
                if (abs($paidAmount - (float) $payment->amount) > 10.0
                    || !self::ledgerCurrencyMatches((string) $expectedCurrency, $paidCurrency, $gatewayName)) {
                    Log::critical('Webhook: amount/currency mismatch', [
                        'payment_id' => $payment->id,
                        'expected_amount' => $payment->amount,
                        'received_amount' => $paidAmount,
                        'expected_currency' => $expectedCurrency,
                        'received_currency' => $paidCurrency,
                    ]);

                    $payment->forceFill([
                        'status' => PaymentStatus::FAILED,
                        'gateway_response' => $data['raw'],
                    ])->save();

                    return ['event' => 'failed', 'payment_id' => $payment->id];
                }

                $webhookUpdate = [
                    'status' => PaymentStatus::SUCCESS,
                    'gateway_response' => $data['raw'],
                ];

                if (!empty($data['payment_method'])) {
                    $resolvedMethod = PaymentMethod::tryFrom($data['payment_method']);
                    if ($resolvedMethod instanceof PaymentMethod) {
                        $webhookUpdate['payment_method'] = $resolvedMethod;
                    }
                }

                $payment->forceFill($webhookUpdate)->save();

                Log::info('Webhook: payment succeeded', ['payment_id' => $payment->id]);

                return ['event' => 'succeeded', 'payment_id' => $payment->id];
            }

            if ($data['status'] === 'cancelled') {
                $payment->forceFill([
                    'status' => PaymentStatus::CANCELLED,
                    'gateway_response' => $data['raw'],
                ])->save();

                Log::info('Webhook: payment cancelled', ['payment_id' => $payment->id]);

                return ['event' => 'failed', 'payment_id' => $payment->id];
            }

            if ($data['status'] === 'failed') {
                $payment->forceFill([
                    'status' => PaymentStatus::FAILED,
                    'gateway_response' => $data['raw'],
                ])->save();

                Log::info('Webhook: payment failed', ['payment_id' => $payment->id]);

                return ['event' => 'failed', 'payment_id' => $payment->id];
            }

            return ['event' => null];
        });

        if (($eventToDispatch['payment_missing'] ?? false) === true) {
            $data['payment_found'] = false;
        }

        // Dispatch events AFTER commit so listeners see the final state.
        if ($eventToDispatch['event'] !== null) {
            $payment = Payment::find($eventToDispatch['payment_id']);
            if ($payment) {
                if ($eventToDispatch['event'] === 'succeeded') {
                    PaymentSucceeded::dispatch($payment);
                } else {
                    PaymentFailed::dispatch($payment);
                }
            }
        }

        // Record the event for dedup AFTER the side effects committed,
        // so a crash mid-transaction still allows the next retry to
        // re-run the orchestrator. 24 h window covers Stripe's
        // 3-day exponential retry schedule well past the point where
        // a duplicate would still be ambiguous (longer windows risk
        // legitimate replays after long outages being suppressed).
        if (is_string($eventId) && $eventId !== '' && $eventToDispatch['event'] !== null) {
            Cache::put(
                'payment_webhook_event:'.$gatewayName.':'.$eventId,
                $data,
                now()->addHours(24),
            );
        }

        return $data;
    }

    public function getGatewayName(): string
    {
        return $this->gateway->getName();
    }

    /**
     * Resolve the authoritative server-side price for a payment type.
     *
     * @param  array<string, mixed>  $validated
     */
    public function resolveAmountForType(string $type, array $validated): ?float
    {
        return match ($type) {
            'credit' => $this->resolveCreditAmount($validated['plan_id'] ?? null),
            'subscription' => $this->resolveSubscriptionAmount($validated['plan_id'] ?? null, $validated['period'] ?? 'monthly'),
            default => null,
        };
    }

    private function resolveCreditAmount(?string $packageId): ?float
    {
        if (!$packageId) {
            return null;
        }

        $package = PointPackage::where('id', $packageId)->where('is_active', true)->first();

        return $package ? (float) $package->price : null;
    }

    private function resolveSubscriptionAmount(?string $planId, string $period): ?float
    {
        if (!$planId) {
            return null;
        }

        $plan = SubscriptionPlan::where('id', $planId)->where('is_active', true)->first();

        if (!$plan) {
            return null;
        }

        return $period === 'yearly' && $plan->price_yearly
            ? (float) $plan->price_yearly
            : (float) $plan->price;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: array{link: string, tx_ref: string, status: string, gateway: string, stripe_flow?: string, raw?: array<string, mixed>}, 1: PaymentGatewayInterface}
     */
    private function initiateWithFallback(array $payload, ?PaymentGatewayInterface $primary = null): array
    {
        $primary ??= $this->gateway;

        try {
            return [$primary->initiate($payload), $primary];
        } catch (PaymentGatewayException $e) {
            // Cross-gateway fallback only kicks in when the chosen primary
            // matches the orchestrator's primary (mobile money flow). For
            // Stripe card payments we propagate the error so the frontend
            // can offer a retry / alternative method instead of silently
            // switching to another gateway.
            if ($this->fallbackGateway === null || $primary->getName() !== $this->gateway->getName()) {
                throw $e;
            }

            Log::warning('Primary payment gateway failed, trying fallback', [
                'primary' => $primary->getName(),
                'fallback' => $this->fallbackGateway->getName(),
                'error' => $e->getMessage(),
            ]);

            return [$this->fallbackGateway->initiate($payload), $this->fallbackGateway];
        }
    }

    /**
     * Resolve the gateway implied by a `PaymentMethod` value (string).
     *
     * Routing rules live in {@see PaymentMethod::gateway()}. When the rule
     * resolves to a gateway that hasn't been wired into the registry (e.g.
     * Stripe disabled in container), we fall back to the orchestrator's
     * default so existing flows keep working.
     */
    private function resolveGatewayForMethod(?string $methodValue): PaymentGatewayInterface
    {
        if ($methodValue === null || $methodValue === '') {
            return $this->gateway;
        }

        $method = PaymentMethod::tryFrom($methodValue);
        if ($method === null) {
            return $this->gateway;
        }

        $gatewayName = $method->gateway()->value;

        return $this->registry[$gatewayName] ?? $this->gateway;
    }

    private function resolveGateway(string $name): PaymentGatewayInterface
    {
        // Final-resort container resolution — covers a webhook that arrives
        // before the registry was wired (rare, but possible during deploys /
        // artisan commands).
        return $this->registry[$name] ?? match ($name) {
            'kpay' => app(KpayPaymentService::class),
            'stripe' => app(StripePaymentService::class),
            default => throw new \InvalidArgumentException("Gateway [{$name}] not supported."),
        };
    }

    /**
     * Kpay's `GET /payments/:id` verify endpoint expects the Kpay `id`
     * (e.g. `pay_abc123`), not our KH tx_ref.
     *
     * Resolution order:
     *  1. gateway_response['kpay_id'] (or ['id']) captured at initiate time.
     *  2. Explicit override (e.g. supplied by a redirect callback), if it
     *     looks like a Kpay reference.
     *  3. Fall back to the KH tx_ref — Kpay will 404, but this keeps the
     *     call shape defensive rather than throwing before hitting the API.
     */
    private static function kpayIdFromPayment(Payment $payment): ?string
    {
        $response = $payment->gateway_response;
        if (is_array($response)) {
            $id = $response['kpay_id'] ?? $response['id'] ?? null;
            if (is_string($id) && $id !== '') {
                return $id;
            }
        }

        return null;
    }

    /**
     * Resolve the reference to pass to Kpay's verify endpoint.
     */
    private static function resolveKpayVerifyReference(Payment $payment, ?string $override): string
    {
        // 1. Stored Kpay `id` recorded at initiate time (stable, doesn't
        //    change between initiate/verify — unlike a checkout
        //    vs transaction reference split).
        $storedId = self::kpayIdFromPayment($payment);
        if ($storedId !== null) {
            return $storedId;
        }

        // 2. Explicit override from the redirect callback — only `pay_*` ids
        // are valid Kpay verify path segments (not KPAY-* / SANDBOX_* labels).
        if (is_string($override) && $override !== '' && PaymentTransactionLookup::isKpayApiPaymentId($override)) {
            return $override;
        }

        return (string) $payment->transaction_id;
    }

    /**
     * Kpay reports XOF while our ledger stores XAF — both are CFA francs at 1:1.
     */
    private static function ledgerCurrencyMatches(string $expected, string $paid, string $gatewayName): bool
    {
        $expected = strtoupper(trim($expected));
        $paid = strtoupper(trim($paid));

        if ($expected === $paid) {
            return true;
        }

        if ($gatewayName !== 'kpay') {
            return false;
        }

        $cfaFrancs = ['XAF', 'XOF'];

        return in_array($expected, $cfaFrancs, true) && in_array($paid, $cfaFrancs, true);
    }

    /**
     * Default hosted-checkout return URL on the PWA (Kpay).
     *
     * The gateway redirects after its own confirmation UI, appending
     * `status`, `tx_ref`, and related query parameters.
     */
    private function defaultFrontendPaymentReturnUrl(string $paymentType, ?string $adId): string
    {
        $base = rtrim((string) config('app.frontend_url', config('app.url')), '/');

        $flow = match ($paymentType) {
            PaymentType::CREDIT->value => 'credit',
            PaymentType::UNLOCK->value => 'unlock',
            PaymentType::SUBSCRIPTION->value => 'subscription',
            PaymentType::BOOST->value => 'boost',
            default => 'credit',
        };

        $query = ['flow' => $flow];
        if (is_string($adId) && $adId !== '') {
            $query['ad_id'] = $adId;
        }

        // Route through /payment/callback so Next.js issues a server-side 302
        // to /payment/return.  A direct link to /payment/return from an external
        // domain causes Next.js to return RSC wire format instead of full HTML.
        return $base.'/payment/callback?'.http_build_query($query);
    }

    /**
     * Construit l'URL du pont de retour natif à passer à la passerelle.
     *
     * On utilise le schéma+hôte RÉELS de la requête entrante (ce que le client
     * a utilisé pour joindre l'API) plutôt que `route()`/`config('app.url')` :
     * `URL::forceScheme('https')` forcerait sinon un https, ce qui casse le
     * retour en local (artisan serve = http://localhost:8000, sans TLS). En
     * preprod/prod la requête arrive déjà en https sur le bon domaine, donc le
     * pont hérite naturellement du bon schéma/hôte.
     */
    private static function buildNativeReturnBridgeUrl(string $deepLink): string
    {
        $base = request()->getSchemeAndHttpHost();
        if ($base === '') {
            $base = rtrim((string) config('app.url'), '/');
        }

        return $base.route('payment.native-return', ['callback' => $deepLink], absolute: false);
    }

    /**
     * Narrow a `mixed` payload value to a non-empty string or null.
     *
     * Centralises the `is_string + !== ''` guard so PHPStan sees a single,
     * unambiguous type-narrowing path (avoids the false-positive
     * `booleanAnd.rightAlwaysTrue` reported when the same chain is inlined).
     */
    private static function stringOrNull(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        return $value === '' ? null : $value;
    }

    /**
     * Kpay appends `reference` + `status` on redirect; preserve our KH tx_ref
     * so the PWA can verify even when the gateway omits metadata in the query string.
     */
    private static function appendTxRefToReturnUrl(string $returnUrl, string $txRef): string
    {
        $fragment = '';
        $urlWithoutFragment = $returnUrl;
        $hashPos = strpos($returnUrl, '#');
        if ($hashPos !== false) {
            $fragment = substr($returnUrl, $hashPos);
            $urlWithoutFragment = substr($returnUrl, 0, $hashPos);
        }

        $parts = parse_url($urlWithoutFragment);
        if ($parts === false) {
            return $returnUrl;
        }

        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        $query['tx_ref'] = $txRef;

        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = $parts['path'] ?? '';
        $user = $parts['user'] ?? '';
        $pass = isset($parts['pass']) ? ':'.$parts['pass'] : '';
        $auth = $user !== '' ? $user.$pass.'@' : '';

        $rebuilt = $scheme.'://'.$auth.$host.$port.$path.'?'.http_build_query($query).$fragment;

        return $rebuilt;
    }
}
