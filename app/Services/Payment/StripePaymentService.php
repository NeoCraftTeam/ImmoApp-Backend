<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\PaymentGatewayInterface;
use App\Contracts\StripeSavedCardServiceInterface;
use App\Enums\PaymentGateway;
use App\Enums\PaymentMethod;
use App\Exceptions\InvalidWebhookSignatureException;
use App\Exceptions\PaymentGatewayException;
use App\Support\XafEurConverter;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Cashier;
use Stripe\Charge;
use Stripe\Checkout\Session as CheckoutSession;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\StripeClient;
use Stripe\StripeObject;
use Stripe\Webhook;

/**
 * Stripe gateway implementation.
 *
 * Stripe doesn't support XAF/XOF — KeyHome bills in EUR using the official
 * BEAC peg `1 EUR = 655.957 XAF`. The XAF amount is the canonical figure
 * (stored in `payments.amount`), the EUR equivalent travels with the
 * PaymentIntent metadata for receipt reconciliation.
 *
 * Unlike Flutterwave, Stripe is NOT redirect-based: `initiate()` returns
 * a `clientSecret` (in the `link` field for interface symmetry) which the
 * frontend hands to `<PaymentElement>` for in-page card collection.
 *
 * Idempotency: `tx_ref` is reused as Stripe's `idempotency_key` and stored
 * in `metadata.tx_ref` so the webhook can locate the local `Payment` row
 * regardless of retries.
 */
final readonly class StripePaymentService implements PaymentGatewayInterface, StripeSavedCardServiceInterface
{
    // The Stripe PHP SDK does not accept a per-request timeout option — the
    // RequestOptions whitelist only allows api_key, idempotency_key,
    // stripe_account, stripe_version, stripe_context. To enforce a timeout we
    // would need to inject a custom CurlClient via Stripe::setHttpClient(...)
    // at boot. Default 80s is acceptable for our endpoints (PaymentIntent
    // create is well under that).

    private StripeClient $stripe;

    public function __construct()
    {
        $secret = (string) config('services.stripe.secret');

        if ($secret === '') {
            // Boot-time guard: a misconfigured production deploy with an empty
            // STRIPE_SECRET must fail immediately with a clear message rather
            // than constructing a broken StripeClient that produces cryptic
            // SDK errors on the first card attempt.
            if (app()->isProduction()) {
                throw new \RuntimeException('STRIPE_SECRET is not configured. Set the STRIPE_SECRET environment variable before deploying.');
            }

            Log::warning('Stripe secret key is not configured; card payments will fail until STRIPE_SECRET is set.');
        }

        // Use Cashier's helper so we share its app-info headers ("Laravel
        // Cashier"). The Stripe SDK only accepts the keys defined in
        // `BaseStripeClient::DEFAULT_OPTIONS` (api_key, client_id, stripe_account,
        // stripe_version, stripe_context, api_base, connect_base, files_base) —
        // passing `api_version` throws `InvalidArgumentException`. We rely on
        // Cashier's pinned `stripe_version` (set via Cashier::STRIPE_VERSION).
        $this->stripe = Cashier::stripe(['api_key' => $secret]);
    }

    public function getName(): string
    {
        return PaymentGateway::Stripe->value;
    }

    /**
     * Create a PaymentIntent and return its client secret.
     *
     * The `link` key carries `pi_xxx_secret_yyy` (the public Stripe client
     * secret) so the frontend can mount `<Elements clientSecret>`. There is
     * no hosted redirect URL in this flow.
     *
     * Saved-card support (added Mai 2026):
     *  - When `customer_id` is provided, the PaymentIntent is attached to
     *    that Stripe Customer. Required to reuse a saved card OR to enable
     *    `setup_future_usage` for storing the card after first charge.
     *  - When `save_payment_method` is `true`, we add
     *    `setup_future_usage: 'off_session'`. Stripe will attach the
     *    PaymentMethod to the Customer once the intent succeeds, so the
     *    cardholder can be re-charged later without re-entering details.
     *  - When `payment_method_id` is provided (`pm_xxx`), we confirm the
     *    PaymentIntent **server-side** with `off_session: true`. The
     *    cardholder isn't in the loop ; if no 3DS challenge is required
     *    we go straight to `succeeded`, otherwise the intent returns
     *    `requires_action` and the frontend must trigger 3DS via the
     *    returned client secret. Either way the response normalises the
     *    final status into the `status` field so the orchestrator can
     *    skip the asynchronous webhook for fast-tracked success.
     *
     * @param  array{
     *     amount: float,
     *     currency: string,
     *     email: string,
     *     phone: string,
     *     name: string,
     *     tx_ref: string,
     *     redirect_url: string,
     *     payment_method?: string,
     *     payment_options?: string,
     *     description?: string,
     *     customer_id?: string|null,
     *     save_payment_method?: bool,
     *     payment_method_id?: string|null,
     *     meta?: array<string, mixed>
     * } $payload
     * @return array{link: string, tx_ref: string, status: string, gateway: string, stripe_flow: string}
     */
    public function initiate(array $payload): array
    {
        $xafAmount = (float) $payload['amount'];
        $eurCents = XafEurConverter::toEurCents($xafAmount);
        $currency = strtolower((string) config('services.stripe.currency', 'eur'));
        $txRef = (string) $payload['tx_ref'];
        $description = (string) ($payload['description'] ?? 'Paiement KeyHome');

        $customerId = self::stringOrNull($payload['customer_id'] ?? null);
        $savePaymentMethod = (bool) ($payload['save_payment_method'] ?? false);
        $paymentMethodId = self::stringOrNull($payload['payment_method_id'] ?? null);

        // Trim metadata to Stripe's hard limits (40 chars/key, 500 chars/value,
        // 50 keys total). We pass the bare minimum that lets webhooks look up
        // the local `Payment` row.
        $rawMeta = (array) ($payload['meta'] ?? []);
        $meta = array_filter([
            'tx_ref' => $txRef,
            'user_id' => isset($rawMeta['user_id']) ? (string) $rawMeta['user_id'] : null,
            'payment_type' => isset($rawMeta['payment_type']) ? (string) $rawMeta['payment_type'] : null,
            'ad_id' => isset($rawMeta['ad_id']) ? (string) $rawMeta['ad_id'] : null,
            'agency_id' => isset($rawMeta['agency_id']) ? (string) $rawMeta['agency_id'] : null,
            'plan_id' => isset($rawMeta['plan_id']) ? (string) $rawMeta['plan_id'] : null,
            'period' => isset($rawMeta['period']) ? (string) $rawMeta['period'] : null,
            'xaf_amount' => (string) (int) round($xafAmount),
            'xaf_to_eur_rate' => (string) XafEurConverter::rate(),
        ], fn ($v): bool => $v !== null && $v !== '');

        if ($paymentMethodId !== null) {
            // ── Off-session saved-card flow ──────────────────────────────────────
            // Confirm the PaymentIntent server-side with the saved card.
            // Stripe runs the SCA exemption logic and either succeeds
            // immediately (`succeeded`) or returns `requires_action` (3DS).
            // We pin `payment_method_types` to `card` because
            // `automatic_payment_methods` cannot be combined with an
            // explicit `payment_method`.
            $params = [
                'amount' => $eurCents,
                'currency' => $currency,
                'description' => mb_substr($description, 0, 1000),
                'receipt_email' => (string) $payload['email'],
                'metadata' => $meta,
                'payment_method' => $paymentMethodId,
                'payment_method_types' => ['card'],
                'confirm' => true,
                'off_session' => true,
            ];

            if ($customerId !== null) {
                $params['customer'] = $customerId;
            }

            try {
                $intent = $this->stripe->paymentIntents->create(
                    $params,
                    ['idempotency_key' => 'kh_initiate:'.$txRef],
                );
            } catch (ApiErrorException $e) {
                Log::error('Stripe initiate (off-session) failed', [
                    'tx_ref' => $txRef,
                    'message' => $e->getMessage(),
                    'stripe_code' => $e->getStripeCode(),
                ]);

                throw new PaymentGatewayException(
                    'Stripe a refusé l\'initialisation du paiement. Réessayez ou choisissez un autre moyen de paiement.',
                    previous: $e,
                );
            }

            $stripeStatus = (string) ($intent->status ?? 'requires_payment_method');
            $normalisedStatus = match ($stripeStatus) {
                'succeeded' => 'success',
                'requires_action' => 'requires_action',
                'requires_payment_method' => 'failed',
                'requires_confirmation', 'processing' => 'pending',
                'canceled' => 'cancelled',
                default => 'pending',
            };

            return [
                'link' => (string) $intent->client_secret,
                'tx_ref' => $txRef,
                'status' => $normalisedStatus,
                'gateway' => $this->getName(),
                'stripe_flow' => 'payment_intent',
            ];
        }

        // ── Checkout Session (new-card / first-time in-page flow) ────────────
        // Create a Checkout Session with `ui_mode: 'custom'` so the frontend
        // can mount the Payment Element via `CheckoutElementsProvider`.
        // The full dynamic payment-method catalogue (Card, Apple Pay, Google
        // Pay, SEPA, Bancontact, iDEAL, Klarna, Link…) is controlled from
        // the Stripe Dashboard → Settings → Payment methods.
        $paymentIntentData = [
            'metadata' => $meta,
            'receipt_email' => (string) $payload['email'],
            'description' => mb_substr($description, 0, 1000),
        ];

        if ($savePaymentMethod && $customerId !== null) {
            $paymentIntentData['setup_future_usage'] = 'off_session';
        }

        $sessionParams = [
            'ui_mode' => 'custom',
            'mode' => 'payment',
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => $currency,
                        'product_data' => ['name' => mb_substr($description, 0, 250)],
                        'unit_amount' => $eurCents,
                    ],
                    'quantity' => 1,
                ],
            ],
            'locale' => 'fr',
            'metadata' => $meta,
            'payment_intent_data' => $paymentIntentData,
            'return_url' => (string) $payload['redirect_url'],
        ];

        if ($customerId !== null) {
            $sessionParams['customer'] = $customerId;
        } else {
            $sessionParams['customer_email'] = (string) $payload['email'];
        }

        try {
            $session = $this->stripe->checkout->sessions->create(
                $sessionParams,
                ['idempotency_key' => 'kh_cs:'.$txRef],
            );
        } catch (ApiErrorException $e) {
            Log::error('Stripe checkout session creation failed', [
                'tx_ref' => $txRef,
                'message' => $e->getMessage(),
                'stripe_code' => $e->getStripeCode(),
            ]);

            throw new PaymentGatewayException(
                'Stripe a refusé l\'initialisation du paiement. Réessayez ou choisissez un autre moyen de paiement.',
                previous: $e,
            );
        }

        return [
            'link' => (string) $session->client_secret,
            'tx_ref' => $txRef,
            'status' => 'pending',
            'gateway' => $this->getName(),
            'stripe_flow' => 'checkout_session',
        ];
    }

    /**
     * List the PaymentMethods saved on a Stripe Customer (type=card only).
     *
     * @return array<int, array{id: string, brand: string, last4: string, exp_month: int, exp_year: int, is_default: bool}>
     */
    public function listSavedCards(string $customerId): array
    {
        try {
            $list = $this->stripe->paymentMethods->all([
                'customer' => $customerId,
                'type' => 'card',
                'limit' => 20,
            ]);

            $customer = $this->stripe->customers->retrieve($customerId);
            $defaultPaymentMethod = (string) ($customer->invoice_settings->default_payment_method ?? '');
        } catch (ApiErrorException $e) {
            Log::error('Stripe listSavedCards failed', [
                'customer_id' => $customerId,
                'message' => $e->getMessage(),
            ]);

            throw new PaymentGatewayException(
                'Impossible de récupérer vos moyens de paiement. Réessayez plus tard.',
                previous: $e,
            );
        }

        $cards = [];
        foreach ($list->data as $paymentMethod) {
            $card = $paymentMethod->card ?? null;
            if ($card === null) {
                continue;
            }
            $cards[] = [
                'id' => (string) $paymentMethod->id,
                'brand' => (string) ($card->brand ?? 'unknown'),
                'last4' => (string) ($card->last4 ?? '----'),
                'exp_month' => (int) ($card->exp_month ?? 0),
                'exp_year' => (int) ($card->exp_year ?? 0),
                'is_default' => $defaultPaymentMethod !== '' && (string) $paymentMethod->id === $defaultPaymentMethod,
            ];
        }

        return $cards;
    }

    /**
     * Detach a saved PaymentMethod from its Customer.
     *
     * After detachment Stripe returns the PaymentMethod object but the card
     * can no longer be charged off-session. Caller must ensure the
     * `$paymentMethodId` actually belongs to `$customerId` (defence in
     * depth — Stripe also enforces ownership server-side).
     */
    public function detachSavedCard(string $customerId, string $paymentMethodId): void
    {
        try {
            $paymentMethod = $this->stripe->paymentMethods->retrieve($paymentMethodId);

            if ((string) ($paymentMethod->customer ?? '') !== $customerId) {
                throw new PaymentGatewayException('Cette carte n\'appartient pas à votre compte.');
            }

            $this->stripe->paymentMethods->detach($paymentMethodId);
        } catch (ApiErrorException $e) {
            Log::error('Stripe detachSavedCard failed', [
                'customer_id' => $customerId,
                'payment_method_id' => $paymentMethodId,
                'message' => $e->getMessage(),
            ]);

            throw new PaymentGatewayException(
                'Impossible de supprimer cette carte. Réessayez plus tard.',
                previous: $e,
            );
        }
    }

    /**
     * Mark a saved PaymentMethod as the Customer's default for future
     * invoices (Cashier subscriptions) AND for off-session charges driven
     * from KeyHome (we read `invoice_settings.default_payment_method` in
     * `listSavedCards` to surface the `is_default` flag).
     */
    public function setDefaultSavedCard(string $customerId, string $paymentMethodId): void
    {
        try {
            $paymentMethod = $this->stripe->paymentMethods->retrieve($paymentMethodId);

            if ((string) ($paymentMethod->customer ?? '') !== $customerId) {
                throw new PaymentGatewayException('Cette carte n\'appartient pas à votre compte.');
            }

            $this->stripe->customers->update($customerId, [
                'invoice_settings' => [
                    'default_payment_method' => $paymentMethodId,
                ],
            ]);
        } catch (ApiErrorException $e) {
            Log::error('Stripe setDefaultSavedCard failed', [
                'customer_id' => $customerId,
                'payment_method_id' => $paymentMethodId,
                'message' => $e->getMessage(),
            ]);

            throw new PaymentGatewayException(
                'Impossible de définir cette carte comme par défaut.',
                previous: $e,
            );
        }
    }

    /**
     * Create a SetupIntent so the frontend can save a new card WITHOUT a
     * charge (profile flow). Returns the SetupIntent client secret.
     *
     * @return array{client_secret: string, id: string}
     */
    public function createSetupIntent(string $customerId): array
    {
        try {
            $intent = $this->stripe->setupIntents->create([
                'customer' => $customerId,
                'payment_method_types' => ['card'],
                'usage' => 'off_session',
            ]);
        } catch (ApiErrorException $e) {
            Log::error('Stripe createSetupIntent failed', [
                'customer_id' => $customerId,
                'message' => $e->getMessage(),
            ]);

            throw new PaymentGatewayException(
                'Impossible d\'enregistrer une nouvelle carte. Réessayez plus tard.',
                previous: $e,
            );
        }

        return [
            'client_secret' => (string) $intent->client_secret,
            'id' => (string) $intent->id,
        ];
    }

    /**
     * Retrieve a PaymentIntent and normalise its status.
     *
     * `$externalReference` is the local `tx_ref` (e.g. `KH-XXXXXX`). We
     * locate the PaymentIntent via `metadata.tx_ref` rather than storing
     * Stripe's `pi_xxx` id separately.
     *
     * @return array{status: string, amount: float, currency: string, payment_method: string|null, paid_at: string|null, raw: array<string, mixed>}
     */
    public function verify(string $externalReference): array
    {
        // Prefer `retrieve()` over `search()` whenever we have a Stripe id
        // available. The `paymentIntents.search` endpoint is **eventually
        // consistent** — newly created intents typically take up to a
        // minute to be indexed. Verifying right after `confirmPayment`
        // would therefore systematically return empty, marking the local
        // Payment as FAILED before the webhook has a chance to land.
        //
        // Two formats are supported:
        //   - PaymentIntent clientSecret  (`pi_xxx_secret_yyy`) → extract pi_xxx
        //   - CheckoutSession clientSecret (`cs_xxx_secret_yyy`) → extract cs_xxx,
        //     then retrieve the session and delegate to its payment_intent.
        $sessionId = $this->extractCheckoutSessionId($externalReference);
        if ($sessionId !== null) {
            return $this->verifyCheckoutSession($sessionId);
        }

        $intentId = $this->extractIntentId($externalReference);

        try {
            if ($intentId !== null) {
                /** @var PaymentIntent $intent */
                $intent = $this->stripe->paymentIntents->retrieve(
                    $intentId,
                    [
                        // Enriches PaymentIntent payloads with `payment_method_details` on the Charge
                        // so reconciliation can distinguish PayPal / Apple Pay from a plain PAN entry.
                        'expand' => ['latest_charge'],
                    ]
                );

                return $this->normaliseIntent($intent);
            }

            // Legacy fallback: only the local `tx_ref` is known. Use the
            // metadata search and accept the indexing latency. This path
            // is exercised by the Flutterwave-style callback page when no
            // `pi_xxx` is available.
            $list = $this->stripe->paymentIntents->search([
                'query' => sprintf('metadata[\'tx_ref\']:\'%s\'', addslashes($externalReference)),
                'limit' => 1,
            ]);
        } catch (ApiErrorException $e) {
            Log::error('Stripe verify failed', [
                'reference' => $externalReference,
                'message' => $e->getMessage(),
            ]);

            throw new PaymentGatewayException(
                'Impossible de vérifier le paiement Stripe. Réessayez plus tard.',
                previous: $e,
            );
        }

        /** @var PaymentIntent|null $intent */
        $intent = $list->data[0] ?? null;

        if (!$intent instanceof PaymentIntent) {
            // CRITICAL : we return 'pending' (not 'failed') so the local
            // Payment row stays in PENDING. Marking it FAILED here would
            // be wrong — the intent likely just hasn't been indexed yet,
            // and `isTerminal()` would then prevent the eventual webhook
            // from finalising the row.
            return [
                'status' => 'pending',
                'amount' => 0.0,
                'currency' => (string) config('payment.default_currency', 'XAF'),
                'payment_method' => null,
                'paid_at' => null,
                'raw' => ['note' => 'PaymentIntent not yet indexed; webhook will finalise.'],
            ];
        }

        /** @var PaymentIntent $expanded */
        $expanded = $this->stripe->paymentIntents->retrieve(
            $intent->id,
            [
                'expand' => ['latest_charge'],
            ],
        );

        return $this->normaliseIntent($expanded);
    }

    /**
     * Extract the canonical PaymentIntent id (`pi_xxx`) from any of the
     * supported reference formats :
     *  - `pi_3TVYUyDelySKRxJ1...`              → `pi_3TVYUy...`
     *  - `pi_3TVYUy..._secret_xyz` (clientSecret) → `pi_3TVYUy...`
     *  - `KH-XXXXXX` (local tx_ref)            → `null` (caller falls back to search)
     */
    private function extractIntentId(string $reference): ?string
    {
        if (!str_starts_with($reference, 'pi_')) {
            return null;
        }
        // A clientSecret has the format `<intent_id>_secret_<random>`.
        $idx = strpos($reference, '_secret_');

        return $idx === false ? $reference : substr($reference, 0, $idx);
    }

    /**
     * Extract a Checkout Session id (`cs_xxx`) from a client secret
     * of the form `cs_xxx_secret_yyy`.
     */
    private function extractCheckoutSessionId(string $reference): ?string
    {
        if (!str_starts_with($reference, 'cs_')) {
            return null;
        }
        $idx = strpos($reference, '_secret_');

        return $idx === false ? $reference : substr($reference, 0, $idx);
    }

    /**
     * Verify a Checkout Session (ui_mode: 'custom') by retrieving it with
     * its expanded PaymentIntent.
     *
     * Called by `verify()` when `payment_link` is a Checkout Session clientSecret
     * (`cs_xxx_secret_yyy`). Immediately consistent — no indexing lag.
     *
     * @return array{status: string, amount: float, currency: string, payment_method: string|null, paid_at: string|null, raw: array<string, mixed>}
     */
    private function verifyCheckoutSession(string $sessionId): array
    {
        try {
            /** @var CheckoutSession $session */
            $session = $this->stripe->checkout->sessions->retrieve(
                $sessionId,
                ['expand' => ['payment_intent', 'payment_intent.latest_charge']],
            );
        } catch (ApiErrorException $e) {
            Log::error('Stripe verify (checkout session) failed', [
                'session_id' => $sessionId,
                'message' => $e->getMessage(),
            ]);

            throw new PaymentGatewayException(
                'Impossible de vérifier le paiement Stripe. Réessayez plus tard.',
                previous: $e,
            );
        }

        $intent = $session->payment_intent;
        if ($intent instanceof PaymentIntent) {
            return $this->normaliseIntent($intent);
        }

        // Session exists but PaymentIntent not yet attached (very rare —
        // async payment methods before confirmation).
        $paymentStatus = (string) ($session->payment_status ?? 'unpaid');

        return [
            'status' => $paymentStatus === 'paid' ? 'success' : 'pending',
            'amount' => 0.0,
            'currency' => (string) config('payment.default_currency', 'XAF'),
            'payment_method' => null,
            'paid_at' => null,
            'raw' => ['session_payment_status' => $paymentStatus],
        ];
    }

    /**
     * Verify the Stripe-Signature header and return a normalised event.
     *
     * Stripe sends raw JSON; the controller MUST pass `$payload` as
     * `$request->getContent()` (not `$request->all()`) and `$headers` MUST
     * include `Stripe-Signature`. See `PaymentController::handleStripeWebhook`.
     *
     * @param  array<string, mixed>  $payload  Decoded payload (we re-encode for verification)
     * @param  array<string, mixed>  $headers
     * @return array{event: string, tx_ref: string, status: string, amount: float, currency: string, payment_method: string|null, raw: array<string, mixed>}
     */
    public function handleWebhook(array $payload, array $headers): array
    {
        $signature = (string) ($headers['stripe-signature'] ?? $headers['Stripe-Signature'] ?? '');
        $secret = (string) config('services.stripe.webhook_secret');
        $tolerance = (int) config('services.stripe.webhook_tolerance', 300);

        if ($signature === '' || $secret === '') {
            throw new InvalidWebhookSignatureException('Stripe webhook signature missing or secret not configured.');
        }

        $rawPayload = (string) ($payload['__raw'] ?? '');

        if ($rawPayload === '') {
            // Defensive: callers should always inject the raw body, but if
            // they don't, fall back to a re-serialised representation. This
            // will fail signature verification — that's the correct outcome.
            $rawPayload = json_encode($payload, JSON_UNESCAPED_SLASHES) ?: '';
        }

        try {
            $event = Webhook::constructEvent($rawPayload, $signature, $secret, $tolerance);
        } catch (SignatureVerificationException $e) {
            Log::warning('Stripe webhook: invalid signature', ['error' => $e->getMessage()]);

            throw new InvalidWebhookSignatureException('Stripe webhook signature verification failed.', previous: $e);
        } catch (\UnexpectedValueException $e) {
            throw new InvalidWebhookSignatureException('Stripe webhook payload is not valid JSON.', previous: $e);
        }

        $object = $event->data->object ?? null;
        $eventName = (string) $event->type;

        // Checkout Session events (ui_mode: 'custom' — Payment Element flow).
        if ($object instanceof CheckoutSession) {
            return $this->normaliseCheckoutSessionEvent($eventName, $object);
        }

        // We only care about PaymentIntent events for the orchestrator.
        // Subscription / invoice events are handled separately by Cashier.
        if (!$object instanceof PaymentIntent) {
            return [
                'event' => $eventName,
                'tx_ref' => '',
                'status' => 'ignored',
                'amount' => 0.0,
                'currency' => strtoupper((string) config('services.stripe.currency', 'eur')),
                'payment_method' => null,
                'raw' => $event->toArray(),
            ];
        }

        $normalised = $this->normaliseIntent($object);

        // Override status from the *event* type — Stripe sends terminal
        // events (`payment_intent.succeeded`, `payment_intent.payment_failed`,
        // `payment_intent.canceled`) with the matching object status, but we
        // pin the value defensively to ensure the orchestrator always sees
        // the correct lifecycle transition.
        $normalised['status'] = match ($eventName) {
            'payment_intent.succeeded' => 'success',
            'payment_intent.payment_failed' => 'failed',
            'payment_intent.canceled' => 'cancelled',
            default => $normalised['status'],
        };

        return [
            'event' => $eventName,
            'tx_ref' => (string) ($object->metadata->tx_ref ?? ''),
            'status' => $normalised['status'],
            'amount' => $normalised['amount'],
            'currency' => $normalised['currency'],
            'payment_method' => $normalised['payment_method'],
            'raw' => $normalised['raw'],
        ];
    }

    /**
     * Translate a Checkout Session event (`checkout.session.completed` etc.)
     * into the gateway-agnostic webhook shape expected by the orchestrator.
     *
     * The XAF amount is read from `session.metadata.xaf_amount` (written at
     * session creation time) to avoid a lossy EUR-cents → XAF round-trip.
     *
     * @return array{event: string, tx_ref: string, status: string, amount: float, currency: string, payment_method: string|null, raw: array<string, mixed>}
     */
    private function normaliseCheckoutSessionEvent(string $eventName, CheckoutSession $session): array
    {
        $txRef = (string) ($session->metadata->tx_ref ?? '');
        $paymentStatus = (string) ($session->payment_status ?? '');

        $normalisedStatus = match ($eventName) {
            'checkout.session.completed' => $paymentStatus === 'paid' ? 'success' : 'pending',
            'checkout.session.async_payment_succeeded' => 'success',
            'checkout.session.async_payment_failed' => 'failed',
            default => 'ignored',
        };

        $metadata = $session->metadata instanceof StripeObject ? $session->metadata->toArray() : [];
        $xafFromMeta = isset($metadata['xaf_amount']) ? (float) $metadata['xaf_amount'] : null;
        $xafAmount = $xafFromMeta ?? XafEurConverter::toXaf((int) ($session->amount_total ?? 0));

        return [
            'event' => $eventName,
            'tx_ref' => $txRef,
            'status' => $normalisedStatus,
            'amount' => $xafAmount,
            'currency' => strtoupper((string) config('payment.default_currency', 'XAF')),
            'payment_method' => PaymentMethod::CARD->value,
            'raw' => $session->toArray(),
        ];
    }

    /**
     * Refund a Stripe charge in full or partially.
     *
     * `$gatewayTransactionId` should be a `pi_xxx` PaymentIntent id. If a
     * `tx_ref` is passed instead, we look up the matching intent first.
     *
     * @return array{refund_id: string, status: string, amount_refunded: float, raw: array<string, mixed>}
     */
    public function refund(string $gatewayTransactionId, ?float $amount = null): array
    {
        $intentId = $this->resolveStripeIntentId($gatewayTransactionId);

        $params = ['payment_intent' => $intentId];

        if ($amount !== null && $amount > 0.0) {
            // The caller passes an XAF amount; convert to EUR cents.
            $params['amount'] = XafEurConverter::toEurCents($amount);
        }

        try {
            $refund = $this->stripe->refunds->create($params, [
                'idempotency_key' => 'kh_refund:'.$intentId.':'.($amount !== null ? (int) $amount : 'full'),
            ]);
        } catch (ApiErrorException $e) {
            Log::error('Stripe refund failed', [
                'intent_id' => $intentId,
                'message' => $e->getMessage(),
            ]);

            throw new PaymentGatewayException(
                'Stripe a refusé le remboursement.',
                previous: $e,
            );
        }

        // Stripe returns `amount_refunded` in the smallest currency unit
        // (cents). Convert back to XAF for our records using the same peg.
        $eurAmountCents = (int) ($refund->amount ?? 0);
        $xafAmountRefunded = XafEurConverter::toXaf($eurAmountCents);

        return [
            'refund_id' => (string) $refund->id,
            'status' => (string) ($refund->status ?? 'unknown'),
            'amount_refunded' => $xafAmountRefunded,
            'raw' => $refund->toArray(),
        ];
    }

    // Currency conversion is delegated to App\Support\XafEurConverter.
    // See that class for the BEAC peg rate and override mechanism.

    /**
     * Resolve the settled Charge backing a PaymentIntent (wallet / PayPal / card PAN).
     */
    private function resolveChargeFromIntent(PaymentIntent $intent): ?Charge
    {
        $latest = $intent->latest_charge ?? null;

        if ($latest instanceof Charge) {
            return $latest;
        }

        if (is_string($latest) && str_starts_with($latest, 'ch_')) {
            try {
                return $this->stripe->charges->retrieve($latest);
            } catch (ApiErrorException $e) {
                Log::warning('Stripe: could not expand latest_charge', [
                    'payment_intent' => $intent->id ?? null,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        foreach ($intent->charges->data ?? [] as $ch) {
            if ($ch instanceof Charge) {
                return $ch;
            }
        }

        return null;
    }

    /**
     * Stable French-facing labels driven by Stripe `payment_method_details`.
     *
     * @return array{label_fr: string, detail_fr: ?string}
     */
    private function stripeFrenchTraceFromCharge(?Charge $charge): array
    {
        $details = $charge?->payment_method_details;

        if ($details === null) {
            return [
                'label_fr' => PaymentMethod::CARD->label(),
                'detail_fr' => null,
            ];
        }

        $pmType = (string) (($details->__get('type') ?? $details->type) ?: '');

        return match ($pmType) {
            'paypal', 'paypal_express_checkout', 'paypal_v2', 'paypal_billing_agreement', 'paypal_v3' => [
                'label_fr' => 'PayPal',
                'detail_fr' => null,
            ],
            'amazon_pay', 'amazonpay' => [
                'label_fr' => 'Amazon Pay',
                'detail_fr' => null,
            ],
            'cashapp', 'cashapp_pay' => [
                'label_fr' => 'Cash App Pay',
                'detail_fr' => null,
            ],
            'link' => [
                'label_fr' => 'Stripe Link',
                'detail_fr' => null,
            ],
            'ideal' => [
                'label_fr' => 'iDEAL',
                'detail_fr' => null,
            ],
            'bancontact' => [
                'label_fr' => 'Bancontact',
                'detail_fr' => null,
            ],
            'klarna', 'afterpay_clearpay', 'affirm', 'clearpay_instalments' => [
                'label_fr' => str_contains($pmType, 'klarna') ? 'Klarna' : 'Fractionné (Buy now pay later)',
                'detail_fr' => null,
            ],
            'sepa_debit' => [
                'label_fr' => 'Prélèvement SEPA',
                'detail_fr' => null,
            ],
            'apple_pay_card', 'facebook_pay_card' => [
                'label_fr' => str_contains($pmType, 'apple') ? 'Apple Pay' : 'Carte',
                'detail_fr' => null,
            ],
            'grabpay' => [
                'label_fr' => 'GrabPay',
                'detail_fr' => null,
            ],
            'alipay' => [
                'label_fr' => 'Alipay',
                'detail_fr' => null,
            ],
            'wechat_pay' => [
                'label_fr' => 'WeChat Pay',
                'detail_fr' => null,
            ],
            'paynow', 'fpx', 'fpx_kfp' => [
                'label_fr' => 'Virement instantané (régional)',
                'detail_fr' => null,
            ],
            'card' => $this->stripeCardLikeTrace($details, $pmType),
            default => [
                'label_fr' => self::stripeGenericInstrumentLabelFr($pmType),
                'detail_fr' => null,
            ],
        };
    }

    /**
     * @return array{label_fr: string, detail_fr: ?string}
     */
    private function stripeCardLikeTrace(?StripeObject $details, string $pmType): array
    {
        if ($details === null) {
            return ['label_fr' => PaymentMethod::CARD->label(), 'detail_fr' => null];
        }

        $card = $details->card ?? null;
        $wallet = $card instanceof StripeObject ? ($card->wallet ?? null) : null;
        $walletType = $wallet instanceof StripeObject ? (string) ($wallet->type ?? '') : '';

        $brandRaw = '';
        if ($card instanceof StripeObject && isset($card->brand)) {
            $brandRaw = (string) $card->brand;
        }

        $last4 = $card instanceof StripeObject && isset($card->last4)
            ? preg_replace('/\D/', '', (string) $card->last4) : '';

        $detailFromCard = $brandRaw !== '' && $last4 !== ''
            ? sprintf('%s · •••• %s', self::stripeCardBrandFr($brandRaw), $last4)
            : ($brandRaw !== '' ? self::stripeCardBrandFr($brandRaw) : null);

        return match ($walletType) {
            'apple_pay' => ['label_fr' => 'Apple Pay', 'detail_fr' => $detailFromCard],
            'google_pay' => ['label_fr' => 'Google Pay', 'detail_fr' => $detailFromCard],
            'link' => ['label_fr' => 'Stripe Link', 'detail_fr' => $detailFromCard],
            'samsung_pay' => ['label_fr' => 'Samsung Pay', 'detail_fr' => $detailFromCard],
            'cashapp_pay' => ['label_fr' => 'Cash App Pay', 'detail_fr' => null],
            default => ['label_fr' => PaymentMethod::CARD->label(), 'detail_fr' => $detailFromCard],
        };
    }

    private static function stripeGenericInstrumentLabelFr(string $stripeType): string
    {
        $stripeType = trim(strtolower(str_replace('_', '-', $stripeType)));

        $map = [
            'google-pay' => 'Google Pay',
            'apple-pay' => 'Apple Pay',
            'sepa-direct-debit' => 'Prélèvement SEPA',
        ];

        return $map[$stripeType] ?? 'Paiement en ligne '.$stripeType;
    }

    private static function stripeCardBrandFr(string $brand): string
    {
        $b = strtolower($brand);

        return match ($b) {
            'visa', 'electron' => 'Visa',
            'mastercard' => 'Mastercard',
            'amex', 'american_express' => 'American Express',
            'diners' => 'Diners Club',
            'discover', 'eftpos_au', 'china_union_pay', 'jcb', 'rupay', 'eftpos_au' => ucfirst(str_replace('_', ' ', $b)),
            default => ucfirst($b ?: 'carte'),
        };
    }

    /**
     * @return array{label_fr: string, detail_fr: ?string, stripe_payment_method_type: string}
     */
    private function buildStripeKhPaymentTrace(PaymentIntent $intent, ?Charge $charge): array
    {
        $pmdType = '';

        if (($charge?->payment_method_details) instanceof StripeObject) {
            $pmdType = strtolower((string) (($charge->payment_method_details->__get('type')
                ?? $charge->payment_method_details->type) ?: ''));
        }

        if ($pmdType === '') {
            foreach ($intent->payment_method_types ?? [] as $t) {
                if ($t === '') {
                    continue;
                }

                $low = strtolower((string) $t);

                if (str_contains($low, 'paypal')) {
                    return [
                        'label_fr' => 'PayPal',
                        'detail_fr' => null,
                        'stripe_payment_method_type' => 'paypal',
                    ];
                }

                if ($low === 'link') {
                    return [
                        'label_fr' => 'Stripe Link',
                        'detail_fr' => null,
                        'stripe_payment_method_type' => 'link',
                    ];
                }

                $pmdType = $low;

                break;
            }
        }

        if (str_contains($pmdType, 'paypal')) {
            return [
                'label_fr' => 'PayPal',
                'detail_fr' => null,
                'stripe_payment_method_type' => 'paypal',
            ];
        }

        if ($pmdType === 'link') {
            return [
                'label_fr' => 'Stripe Link',
                'detail_fr' => null,
                'stripe_payment_method_type' => 'link',
            ];
        }

        $fromCharge = $this->stripeFrenchTraceFromCharge($charge);

        return [
            'label_fr' => $fromCharge['label_fr'],
            'detail_fr' => $fromCharge['detail_fr'],
            'stripe_payment_method_type' => $pmdType !== '' ? $pmdType : 'card',
        ];
    }

    /**
     * Translate a Stripe PaymentIntent into the gateway-agnostic shape that
     * `PaymentService` consumes.
     *
     * @return array{status: string, amount: float, currency: string, payment_method: string|null, paid_at: string|null, raw: array<string, mixed>}
     */
    private function normaliseIntent(PaymentIntent $intent): array
    {
        $stripeStatus = (string) ($intent->status ?? 'unknown');

        $normalisedStatus = match ($stripeStatus) {
            'succeeded' => 'success',
            'processing', 'requires_action', 'requires_confirmation', 'requires_payment_method' => 'pending',
            'canceled' => 'cancelled',
            default => 'failed',
        };

        // CRITICAL : we MUST report the original XAF amount, not a back-converted
        // value from EUR cents. The XAF→EUR→XAF round-trip is lossy because
        // Stripe rounds to whole cents (e.g. 1000 XAF → 152 cents → 997 XAF).
        // The orchestrator's `amount mismatch` guard would then force the
        // payment to FAILED on a perfectly valid charge.
        //
        // We pinned the original XAF amount in `metadata.xaf_amount` at
        // initiate time precisely for this reason — it's the source of truth.
        // If a row predates that metadata field (legacy), we fall back to the
        // back-converted amount with a wider tolerance handled upstream.
        $metadata = $intent->metadata->toArray();
        $xafFromMeta = isset($metadata['xaf_amount']) ? (float) $metadata['xaf_amount'] : null;
        $xafAmount = $xafFromMeta ?? XafEurConverter::toXaf((int) ($intent->amount ?? 0));

        $expectedCurrency = (string) config('payment.default_currency', 'XAF');

        $charge = $this->resolveChargeFromIntent($intent);

        if ($stripeStatus === 'succeeded' && !$charge instanceof Charge) {
            // Webhooks sometimes ship a skinny PaymentIntent (`latest_charge` id only).
            if (isset($intent->latest_charge) && is_string($intent->latest_charge) && str_starts_with($intent->latest_charge, 'ch_')) {
                try {
                    $charge = $this->stripe->charges->retrieve($intent->latest_charge);
                } catch (ApiErrorException $e) {
                    Log::warning('Stripe webhook: Charge retrieve failed', [
                        'intent' => $intent->id,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        }

        /** @var array<string, mixed> $rawPayload */
        $rawPayload = $intent->toArray();

        if ($stripeStatus === 'succeeded') {
            $rawPayload['kh_payment_trace'] = $this->buildStripeKhPaymentTrace($intent, $charge);
        }

        return [
            'status' => $normalisedStatus,
            'amount' => $xafAmount,
            'currency' => $expectedCurrency,
            'payment_method' => PaymentMethod::CARD->value,
            'paid_at' => $stripeStatus === 'succeeded' ? now()->toIso8601String() : null,
            'raw' => $rawPayload,
        ];
    }

    /**
     * Accept either a Stripe PaymentIntent id (`pi_xxx`) or a local
     * `tx_ref` (`KH-XXXXXX`) and return the canonical Stripe id.
     */
    private function resolveStripeIntentId(string $reference): string
    {
        if (str_starts_with($reference, 'pi_')) {
            return $reference;
        }

        try {
            $list = $this->stripe->paymentIntents->search([
                'query' => sprintf('metadata[\'tx_ref\']:\'%s\'', addslashes($reference)),
                'limit' => 1,
            ]);
        } catch (ApiErrorException $e) {
            throw new PaymentGatewayException(
                'Stripe : impossible de retrouver la transaction à rembourser.',
                previous: $e,
            );
        }

        $intent = $list->data[0] ?? null;

        if (!$intent instanceof PaymentIntent) {
            throw new PaymentGatewayException("Stripe : transaction introuvable pour la référence [{$reference}].");
        }

        return (string) $intent->id;
    }

    /**
     * Narrow a `mixed` payload value to a non-empty string or null.
     *
     * Used to extract optional `customer_id` / `payment_method_id` from the
     * `array<string, mixed>` payload without tripping PHPStan's
     * `booleanAnd.rightAlwaysTrue` false-positive on inline `is_string + !== ''`
     * chains.
     */
    private static function stringOrNull(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        return $value === '' ? null : $value;
    }
}
