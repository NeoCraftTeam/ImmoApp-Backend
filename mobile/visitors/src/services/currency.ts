import AsyncStorage from '@react-native-async-storage/async-storage';
import { getLocales } from 'expo-localization';

/**
 * Conversion de devises — SINGLETON in-memory, zéro requête API.
 *
 * Règle absolue (comme le web) : le backend stocke TOUJOURS les prix en
 * XAF (FCFA) ; l'affichage dans une autre devise est purement visuel,
 * les paiements se font en FCFA. Les taux sont un snapshot statique
 * (base XAF), suffisant car ils bougent lentement — on évite ainsi tout
 * appel réseau récurrent. La devise d'affichage est résolue UNE fois
 * depuis la région de l'appareil, persistée, et modifiable par l'user.
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

export const SUPPORTED_CURRENCIES = Object.keys(RATES);

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

export const CURRENCY_LABELS: Record<string, string> = {
  XAF: 'Franc CFA (CEMAC)',
  XOF: 'Franc CFA (UEMOA)',
  EUR: 'Euro',
  USD: 'Dollar US',
  GBP: 'Livre Sterling',
  CHF: 'Franc Suisse',
  CAD: 'Dollar Canadien',
  NGN: 'Naira',
  GHS: 'Cedi',
  KES: 'Shilling Kényan',
  ZAR: 'Rand',
  AED: 'Dirham EAU',
  CNY: 'Yuan',
  JPY: 'Yen',
  INR: 'Roupie',
  MAD: 'Dirham Marocain',
};

const COUNTRY_TO_CURRENCY: Record<string, string> = {
  CM: 'XAF', GA: 'XAF', CG: 'XAF', TD: 'XAF', CF: 'XAF', GQ: 'XAF',
  SN: 'XOF', CI: 'XOF', BJ: 'XOF', TG: 'XOF', BF: 'XOF', ML: 'XOF', NE: 'XOF',
  FR: 'EUR', BE: 'EUR', DE: 'EUR', IT: 'EUR', ES: 'EUR', PT: 'EUR',
  US: 'USD', CA: 'CAD', GB: 'GBP', CH: 'CHF',
  NG: 'NGN', GH: 'GHS', KE: 'KES', ZA: 'ZAR', MA: 'MAD',
  AE: 'AED', CN: 'CNY', JP: 'JPY', IN: 'INR',
};

const STORAGE_KEY = 'keyhome.display_currency';

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
 * Store singleton : une seule source de vérité pour la devise
 * d'affichage. `useSyncExternalStore` s'y abonne pour la réactivité.
 */
class CurrencyStore {
  private currency: string = deviceDefaultCurrency();
  private hydrated = false;
  private listeners = new Set<() => void>();

  constructor() {
    // Réhydrate le choix persisté une seule fois (async, non bloquant).
    void AsyncStorage.getItem(STORAGE_KEY).then((saved) => {
      this.hydrated = true;
      if (saved && RATES[saved] && saved !== this.currency) {
        this.currency = saved;
        this.emit();
      }
    });
  }

  getCurrency = (): string => this.currency;

  isHydrated = (): boolean => this.hydrated;

  setCurrency = (next: string): void => {
    if (!RATES[next] || next === this.currency) return;
    this.currency = next;
    void AsyncStorage.setItem(STORAGE_KEY, next).catch(() => {});
    this.emit();
  };

  subscribe = (fn: () => void): (() => void) => {
    this.listeners.add(fn);
    return () => this.listeners.delete(fn);
  };

  private emit(): void {
    this.listeners.forEach((fn) => fn());
  }
}

export const currencyStore = new CurrencyStore();

export function symbolFor(currency: string): string {
  return SYMBOLS[currency] ?? currency;
}

/** Convertit un montant XAF vers la devise cible (repli XAF si taux absent). */
export function convertFromXAF(amountXAF: number, target: string): number {
  const rate = RATES[target];
  if (!rate || !Number.isFinite(rate)) return amountXAF;
  return amountXAF * rate;
}

/**
 * Formate un montant XAF dans la devise cible. XAF/XOF : entier + « FCFA »
 * suffixe. Autres : 2 décimales + symbole préfixe (€, $) ou suffixe.
 */
export function formatFromXAF(amountXAF: number, target: string): string {
  const converted = convertFromXAF(amountXAF, target);
  const sym = symbolFor(target);
  if (target === 'XAF' || target === 'XOF') {
    return `${Math.round(converted).toLocaleString('fr-FR')} ${sym}`;
  }
  const value = converted.toLocaleString('fr-FR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
  // Symbole devant pour les devises occidentales, derrière sinon.
  return ['EUR', 'USD', 'GBP', 'CAD', 'CHF'].includes(target)
    ? `${sym}${value}`
    : `${value} ${sym}`;
}
