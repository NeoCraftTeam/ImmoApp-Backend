<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\PaymentGatewayInterface;
use App\Enums\PaymentGateway;
use App\Exceptions\InvalidWebhookSignatureException;
use App\Exceptions\PaymentGatewayException;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Cashier;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\StripeClient;
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
final readonly class StripePaymentService implements PaymentGatewayInterface
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
            // Boot-time guard: a misconfigured production deploy with empty
            // STRIPE_SECRET would otherwise leak through and 500 on the
            // first card attempt. Throw early with a clear log line.
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
     *     meta?: array<string, mixed>
     * } $payload
     * @return array{link: string, tx_ref: string, status: string, gateway: string}
     */
    public function initiate(array $payload): array
    {
        $xafAmount = (float) $payload['amount'];
        $eurCents = self::convertXafToEurCents($xafAmount);
        $currency = strtolower((string) config('services.stripe.currency', 'eur'));
        $txRef = (string) $payload['tx_ref'];
        $description = (string) ($payload['description'] ?? 'Paiement KeyHome');

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
            'xaf_to_eur_rate' => (string) self::pegRate(),
        ], fn ($v): bool => $v !== null && $v !== '');

        try {
            $intent = $this->stripe->paymentIntents->create(
                [
                    'amount' => $eurCents,
                    'currency' => $currency,
                    'description' => mb_substr($description, 0, 1000),
                    'receipt_email' => (string) $payload['email'],
                    'metadata' => $meta,
                    // Use Stripe's dynamic catalogue : every method enabled
                    // in the Stripe Dashboard (Card, Apple Pay, Google Pay,
                    // SEPA, Bancontact, iDEAL, Klarna, Cash App Pay, Link,
                    // etc.) is presented automatically, filtered by the
                    // visitor's location and the PaymentIntent currency.
                    // KeyHome targets a global audience — diaspora paying
                    // from anywhere — so we let Stripe pick the optimal UI
                    // per region instead of hard-coding `payment_method_types`.
                    // Disable methods you don't want from the Stripe Dashboard
                    // → Settings → Payment methods (single source of truth).
                    'automatic_payment_methods' => ['enabled' => true],
                ],
                [
                    'idempotency_key' => 'kh_initiate:'.$txRef,
                ],
            );
        } catch (ApiErrorException $e) {
            Log::error('Stripe initiate failed', [
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
            // For Stripe, `link` carries the client secret consumed by
            // `<PaymentElement>` on the frontend. The interface name is
            // a leftover from the Flutterwave-only era.
            'link' => (string) $intent->client_secret,
            'tx_ref' => $txRef,
            'status' => 'pending',
            'gateway' => $this->getName(),
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
        // The caller passes the local `payment_link` for Stripe rows
        // (a clientSecret of the form `pi_xxx_secret_yyy`) so we can
        // extract the bare `pi_xxx` and call `retrieve()` — instant and
        // strongly consistent.
        $intentId = $this->extractIntentId($externalReference);

        try {
            if ($intentId !== null) {
                /** @var PaymentIntent $intent */
                $intent = $this->stripe->paymentIntents->retrieve($intentId);

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

        return $this->normaliseIntent($intent);
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
            $params['amount'] = self::convertXafToEurCents($amount);
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
        $xafAmountRefunded = self::convertEurCentsToXaf($eurAmountCents);

        return [
            'refund_id' => (string) $refund->id,
            'status' => (string) ($refund->status ?? 'unknown'),
            'amount_refunded' => $xafAmountRefunded,
            'raw' => $refund->toArray(),
        ];
    }

    /**
     * Convert XAF (whole francs) to EUR cents using the pegged rate.
     */
    public static function convertXafToEurCents(float $xafAmount): int
    {
        $rate = self::pegRate();

        // XAF amount → EUR amount → cents. Round to nearest cent.
        return (int) round(($xafAmount / $rate) * 100);
    }

    /**
     * Convert EUR cents back to XAF (whole francs).
     */
    public static function convertEurCentsToXaf(int $eurCents): float
    {
        $rate = self::pegRate();

        return round(($eurCents / 100) * $rate, 0);
    }

    /**
     * Pegged conversion rate (1 EUR = 655.957 XAF). Defaults to the BEAC
     * official peg; can be overridden via `services.stripe.xaf_to_eur_rate`.
     */
    private static function pegRate(): float
    {
        $rate = (float) config('services.stripe.xaf_to_eur_rate', 655.957);

        return $rate > 0 ? $rate : 655.957;
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
        $xafAmount = $xafFromMeta ?? self::convertEurCentsToXaf((int) ($intent->amount ?? 0));

        $expectedCurrency = (string) config('payment.default_currency', 'XAF');

        return [
            'status' => $normalisedStatus,
            'amount' => $xafAmount,
            'currency' => $expectedCurrency,
            'payment_method' => 'card',
            'paid_at' => $stripeStatus === 'succeeded' ? now()->toIso8601String() : null,
            'raw' => $intent->toArray(),
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
}
