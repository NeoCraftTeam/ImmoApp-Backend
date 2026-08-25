<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Cashier;
use Stripe\ApiRequestor;
use Stripe\HttpClient\CurlClient as StripeCurlClient;
use Stripe\StripeClient;

/**
 * Builds the configured {@see StripeClient} shared by the Stripe gateway
 * collaborators (charge lifecycle + saved-card management).
 *
 * Centralises the secret read, the production boot-time guard, and the curl
 * timeout overrides so every Stripe service constructs its client identically.
 * Extracted from StripePaymentService so StripeSavedCardService can reuse the
 * exact same construction without duplicating it.
 */
final class StripeClientFactory
{
    // Network timeout for all Stripe API calls made by KeyHome services.
    // The Stripe PHP SDK default is 80 s (connect 30 s). nginx's
    // fastcgi_read_timeout on the VPS is typically 60 s, so without an
    // explicit override nginx terminates the connection first and returns
    // its own raw 502 page — without CORS headers — causing the browser
    // to reject the response entirely. Keeping these values below nginx's
    // timeout ensures ApiConnectionException propagates back through PHP,
    // Laravel catches it as PaymentGatewayException, and the client
    // receives a proper JSON 502 response with Access-Control-Allow-Origin.
    private const int STRIPE_TIMEOUT_S = 20;

    private const int STRIPE_CONNECT_TIMEOUT_S = 5;

    public static function make(): StripeClient
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
        $curlClient = StripeCurlClient::instance();
        $curlClient->setTimeout(self::STRIPE_TIMEOUT_S);
        $curlClient->setConnectTimeout(self::STRIPE_CONNECT_TIMEOUT_S);
        ApiRequestor::setHttpClient($curlClient);

        return Cashier::stripe(['api_key' => $secret]);
    }
}
