<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * In local development, the Next.js frontend runs on localhost:3000 while the
 * Filament admin panel runs on keyhome.test / admin.keyhome.test. WebAuthn requires
 * the Relying Party ID to be a registrable domain suffix of the page's origin.
 *
 * The static WEBAUTHN_ID=keyhome.test already satisfies all *.keyhome.test origins
 * (admin.keyhome.test, keyhome.test itself) because "keyhome.test" is a valid eTLD+1
 * suffix of all of them. The ONLY case where the static value cannot work is when the
 * caller origin is "localhost" (localhost:3000), since "keyhome.test" is not a suffix
 * of "localhost".
 *
 * Therefore this middleware overrides the RP ID ONLY when the origin host is
 * exactly "localhost". All other origins rely on the static WEBAUTHN_ID env value.
 *
 * It is intentionally restricted to the local environment. Production always uses
 * the static WEBAUTHN_ID env value (parent domain covering all subdomains).
 */
class DynamicWebAuthnRelyingParty
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('local')) {
            $origin = $request->header('Origin', '');

            if ($origin !== '') {
                $host = parse_url($origin, PHP_URL_HOST);

                if ($host === 'localhost') {
                    config(['webauthn.relying_party.id' => 'localhost']);
                }
            }
        }

        return $next($request);
    }
}
