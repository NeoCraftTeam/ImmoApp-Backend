<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Concerns;

use App\Services\TurnstileService;
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
        // widget DOM). L'API mobile stateless (bearer Sanctum, sans
        // session) ne peut pas fournir de token — même logique que le
        // login/register. Le rate-limiter de la route protège l'abus.
        if (!$this->hasSession()) {
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
