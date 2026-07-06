<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payment;

use App\Support\FrontendRedirectGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Pont de retour natif après un paiement hosted-checkout mobile.
 *
 * Les passerelles (Stripe / GeniusPay) exigent une `success_url` http(s) :
 * impossible de leur passer directement un deep-link `keyhome://`. Cette
 * route HTTPS reçoit la redirection de la passerelle puis renvoie un 302
 * vers le deep-link natif de l'app — ce qui ferme l'onglet in-app
 * (ASWebAuthenticationSession) et rend la main à l'application mobile,
 * exactement comme le flux OAuth.
 *
 * Le paramètre `callback` est validé par le même whitelist de schémas que
 * les redirections OAuth ({@see FrontendRedirectGuard}) : seule notre app
 * enregistre ces schémas sur l'appareil, donc aucun open-redirect
 * exploitable côté web.
 */
final class PaymentNativeReturnController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $callback = (string) $request->query('callback', '');

        // Repli sûr : deep-link absent ou non whitelisté → on renvoie vers
        // l'accueil du frontend web plutôt que d'ouvrir une redirection
        // arbitraire (anti open-redirect).
        if ($callback === '' || !FrontendRedirectGuard::isAllowedAppScheme($callback)) {
            return redirect()->away(rtrim((string) config('app.frontend_url', config('app.url')), '/'));
        }

        // Propage les paramètres utiles renvoyés par la passerelle vers le
        // deep-link, pour que l'app puisse vérifier et afficher l'état.
        $forward = array_filter(
            $request->only(['tx_ref', 'reference', 'status', 'transaction_id']),
            static fn (mixed $v): bool => is_string($v) && $v !== '',
        );

        return redirect()->away($this->appendQuery($callback, $forward));
    }

    /**
     * @param  array<string, string>  $params
     */
    private function appendQuery(string $url, array $params): string
    {
        if ($params === []) {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.http_build_query($params);
    }
}
