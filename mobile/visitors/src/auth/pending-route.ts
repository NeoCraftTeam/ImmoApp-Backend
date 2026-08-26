/**
 * Mémorise la route visée quand un mur de connexion (SignInWall) renvoie
 * un visiteur non authentifié vers le login, pour la rejouer après
 * connexion au lieu de le déposer sur le feed d'accueil.
 *
 * Volontairement en mémoire process (pas persisté) : si l'app est tuée
 * entre le login et le replay, l'intention d'origine est de toute façon
 * perdue par l'OS — inutile de rejouer une destination périmée.
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
