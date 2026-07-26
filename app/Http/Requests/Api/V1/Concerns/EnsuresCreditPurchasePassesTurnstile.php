<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Concerns;

use App\Services\TurnstileService;
use App\Support\FrontendRedirectGuard;
use Illuminate\Contracts\Validation\Validator;

/**
 * Enforces Cloudflare Turnstile for credit-payment endpoints when secrets are configured.
 *
 * Matches {@see RegistrationService} fail-open semantics when Turnstile is not configured.
 */
trait EnsuresCreditPurchasePassesTurnstile
{
    protected function enforceTurnstileForCreditPurchase(Validator $validator): void
    {
        // Turnstile ne s'applique qu'aux clients web stateful (session +
        // widget DOM). Deux familles de clients sont exemptées, exactement
        // comme dans LoginService::authenticate / RegistrationService::register :
        //  - API mobile/intégration stateless (bearer Sanctum, sans session) ;
        //  - apps natives KeyHome (en-tête `X-KeyHome-Client`), qui peuvent
        //    hériter d'une session via EnsureFrontendRequestsAreStateful mais
        //    n'ont ni DOM ni widget pour exécuter Turnstile — l'exiger
        //    bloquerait tout achat de crédits mobile.
        // Le rate-limiter de la route reste actif dans tous les cas.
        if (!$this->hasSession() || FrontendRedirectGuard::isMobileAppRequest($this)) {
            return;
        }

        /** @var TurnstileService $turnstile */
        $turnstile = app(TurnstileService::class);
        if (!$turnstile->isConfigured()) {
            return;
        }

        $tokenInput = $this->input('turnstile_token');
        $token = is_string($tokenInput) ? $tokenInput : null;

        if (!$turnstile->verify($token, $this->ip())) {
            $validator->errors()->add(
                'turnstile_token',
                'Vérification anti-robot échouée. Veuillez recharger la page.',
            );
        }
    }
}
