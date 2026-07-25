import { getLocales } from 'expo-localization';

/**
 * Devise d'affichage — résolue AUTOMATIQUEMENT, zéro requête API,
 * AUCUN choix manuel utilisateur.
 *
 * Règle absolue (comme le web `keyhome-frontend-next`) : le backend stocke
 * TOUJOURS les prix en XAF (FCFA) ; l'affichage dans une autre devise est
 * purement visuel, les paiements se font en FCFA. La devise d'affichage est
 * dérivée UNE fois de la région/locale de l'appareil (l'équivalent mobile de
 * la géo-détection `CF-IPCountry` du web) et affichée de façon cohérente
 * partout. Il n'existe volontairement pas de sélecteur de devise : la page
 * Paramètres du web n'en propose pas non plus (le sélecteur web vit dans la
 * barre de navigation et le panneau bailleur reste toujours en FCFA).
 *
 * Les taux sont un snapshot statique aligné sur le fallback statique du web
 * (`/api/exchange-rates`), suffisant car ils bougent lentement — on évite
 * ainsi tout appel réseau récurrent.
 */
export const BASE_CURRENCY = 'XAF';

/** 1 XAF = rate[devise]. Snapshot aligné sur le fallback web. */
const RATES: Record<string, number> = {
  XAF: 1,
  XOF: 1,
  EUR: 0.001524,
  USD: 0.001647,
  GBP: 0.001302,
  CHF: 0.001478,
  CAD: 0.002282,
  NGN: 2.704,
  GHS: 0.02575,
  KES: 0.2129,
  ZAR: 0.03007,
  AED: 0.006048,
  CNY: 0.01191,
  JPY: 0.2545,
  INR: 0.1392,
  MAD: 0.01636,
};

const SYMBOLS: Record<string, string> = {
  XAF: 'FCFA',
  XOF: 'FCFA',
  EUR: '€',
  USD: '$',
  GBP: '£',
  CHF: 'CHF',
  CAD: 'CA$',
  NGN: '₦',
  GHS: 'GH₵',
  KES: 'KSh',
  ZAR: 'R',
  AED: 'AED',
  CNY: '¥',
  JPY: '¥',
  INR: '₹',
  MAD: 'MAD',
};

const COUNTRY_TO_CURRENCY: Record<string, string> = {
  CM: 'XAF', GA: 'XAF', CG: 'XAF', TD: 'XAF', CF: 'XAF', GQ: 'XAF',
  SN: 'XOF', CI: 'XOF', BJ: 'XOF', TG: 'XOF', BF: 'XOF', ML: 'XOF', NE: 'XOF',
  FR: 'EUR', BE: 'EUR', DE: 'EUR', IT: 'EUR', ES: 'EUR', PT: 'EUR',
  US: 'USD', CA: 'CAD', GB: 'GBP', CH: 'CHF',
  NG: 'NGN', GH: 'GHS', KE: 'KES', ZA: 'ZAR', MA: 'MAD',
  AE: 'AED', CN: 'CNY', JP: 'JPY', IN: 'INR',
};

function deviceDefaultCurrency(): string {
  try {
    const locales = getLocales();
    const region = locales[0]?.regionCode ?? '';
    const cur = locales[0]?.currencyCode ?? '';
    if (cur && RATES[cur]) return cur;
    if (region && COUNTRY_TO_CURRENCY[region]) return COUNTRY_TO_CURRENCY[region];
  } catch {
    /* ignore */
  }
  return BASE_CURRENCY;
}

/**
 * Store singleton : une seule source de vérité pour la devise d'affichage,
 * résolue automatiquement depuis l'appareil et immuable pour la session.
 * `useSyncExternalStore` s'y abonne pour lire la valeur.
 */
class CurrencyStore {
  private readonly currency: string = deviceDefaultCurrency();

  getCurrency = (): string => this.currency;

  /**
   * La devise étant dérivée automatiquement et immuable, aucun changement
   * n'est jamais émis — l'abonnement est un simple no-op requis par
   * `useSyncExternalStore`.
   */
  subscribe = (_fn: () => void): (() => void) => () => {};
}

export const currencyStore = new CurrencyStore();

export function symbolFor(currency: string): string {
  return SYMBOLS[currency] ?? currency;
}

/**
 * Résout le montant + la devise réellement affichés à partir d'un montant XAF.
 * XAF/XOF sont pegés 1:1 (aucune conversion). Pour toute autre devise, on ne
 * convertit que si un taux valide existe — sinon on retombe sur XAF pour ne
 * jamais afficher un montant FCFA avec un symbole étranger (parité web
 * `resolveDisplayedMoney`).
 */
function resolveDisplayedMoney(
  amountXAF: number,
  target: string,
): { amount: number; displayCurrency: string } {
  if (!Number.isFinite(amountXAF)) return { amount: 0, displayCurrency: BASE_CURRENCY };
  if (target === 'XAF' || target === 'XOF') {
    return { amount: amountXAF, displayCurrency: target };
  }
  const rate = RATES[target];
  if (!rate || !Number.isFinite(rate) || rate <= 0) {
    return { amount: amountXAF, displayCurrency: BASE_CURRENCY };
  }

  return { amount: amountXAF * rate, displayCurrency: target };
}

/** Convertit un montant XAF vers la devise cible (repli XAF si taux absent). */
export function convertFromXAF(amountXAF: number, target: string): number {
  return resolveDisplayedMoney(amountXAF, target).amount;
}

/**
 * Formate un montant XAF dans la devise cible. XAF/XOF : entier + « FCFA »
 * suffixe. Autres : 2 décimales + symbole préfixe (€, $) ou suffixe. Repli
 * automatique sur XAF quand aucun taux valide n'est disponible.
 */
export function formatFromXAF(amountXAF: number, target: string): string {
  const { amount, displayCurrency } = resolveDisplayedMoney(amountXAF, target);
  const sym = symbolFor(displayCurrency);
  if (displayCurrency === 'XAF' || displayCurrency === 'XOF') {
    return `${Math.round(amount).toLocaleString('fr-FR')} ${sym}`;
  }
  const value = amount.toLocaleString('fr-FR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
  // Symbole devant pour les devises occidentales, derrière sinon.
  return ['EUR', 'USD', 'GBP', 'CAD', 'CHF'].includes(displayCurrency)
    ? `${sym}${value}`
    : `${value} ${sym}`;
}
