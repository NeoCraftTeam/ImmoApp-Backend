/**
 * Mémorise la route visée quand l'AuthGate renvoie un utilisateur non
 * authentifié vers le login (deep-link paiement, notification push…),
 * pour la rejouer après connexion au lieu d'atterrir sur le dashboard.
 *
 * Volontairement en mémoire process (pas persisté) : si l'app est tuée
 * entre le login et le replay, le deep-link d'origine est de toute façon
 * perdu par l'OS — inutile de rejouer une intention périmée.
 */

let pendingRoute: string | null = null;

/** Routes qu'il ne sert à rien de rejouer après connexion. */
const IGNORED_PREFIXES = ['/(auth)', '/onboarding', '/auth/callback'];

export function rememberPendingRoute(href: string): void {
  if (!href || href === '/' || IGNORED_PREFIXES.some((p) => href.startsWith(p))) {
    return;
  }
  pendingRoute = href;
}

export function consumePendingRoute(): string | null {
  const route = pendingRoute;
  pendingRoute = null;
  return route;
}
