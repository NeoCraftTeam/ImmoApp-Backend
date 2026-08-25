<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\PaymentType;

/**
 * Builds the hosted-checkout return URLs handed to a payment gateway.
 *
 * Pure URL assembly extracted out of PaymentService so the orchestrator keeps
 * only the charge lifecycle. Three concerns live here:
 *  - the default PWA return URL per payment flow ({@see defaultFrontend()}),
 *  - the native return bridge that turns a mobile deep-link into an https URL
 *    the gateway accepts ({@see nativeBridge()}),
 *  - preserving our KH tx_ref on the redirect ({@see appendTxRef()}).
 */
final class PaymentReturnUrl
{
    /**
     * Default hosted-checkout return URL on the PWA (Kpay).
     *
     * The gateway redirects after its own confirmation UI, appending
     * `status`, `tx_ref`, and related query parameters.
     */
    public static function defaultFrontend(string $paymentType, ?string $adId): string
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
    public static function nativeBridge(string $deepLink): string
    {
        $base = request()->getSchemeAndHttpHost();
        if ($base === '') {
            $base = rtrim((string) config('app.url'), '/');
        }

        return $base.route('payment.native-return', ['callback' => $deepLink], absolute: false);
    }

    /**
     * Kpay appends `reference` + `status` on redirect; preserve our KH tx_ref
     * so the PWA can verify even when the gateway omits metadata in the query string.
     */
    public static function appendTxRef(string $returnUrl, string $txRef): string
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
