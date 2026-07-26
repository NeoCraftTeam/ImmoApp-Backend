import AsyncStorage from '@react-native-async-storage/async-storage';
import { getLocales } from 'expo-localization';
import * as Location from 'expo-location';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';

/**
 * Devise d'affichage. Le backend stocke TOUJOURS les prix en XAF (FCFA) ;
 * l'affichage dans une autre devise est purement visuel (paiements en FCFA).
 *
 * Résolution de la devise (première source valable gagne) :
 *   1. Choix manuel de l'utilisateur (persisté) — prime toujours.
 *   2. Géolocalisation par IP via le backend (`GET /geo/currency`,
 *      MaxMind) — fiable même si le locale du téléphone est dans une autre
 *      langue (un téléphone en français en Suisse ne doit pas voir EUR).
 *   3. Repli local : `regionCode` de l'appareil (PAS `currencyCode`, qui
 *      est lié à la LANGUE et non à la position), puis XAF.
 *
 * Le store est mutable : la détection IP arrive de façon asynchrone et un
 * sélecteur permet à l'utilisateur de changer de devise.
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

/** Devises proposées dans le sélecteur (celles avec un taux connu). */
export const SUPPORTED_CURRENCIES = Object.keys(RATES);

/**
 * Repli local : privilégie le `regionCode` (où est physiquement l'appareil)
 * plutôt que le `currencyCode` (lié à la LANGUE — un téléphone en français
 * rapporte EUR même en Suisse). On ne retombe sur `currencyCode` que si la
 * région est inconnue.
 */
function deviceDefaultCurrency(): string {
  try {
    const locales = getLocales();
    const region = (locales[0]?.regionCode ?? '').toUpperCase();
    const cur = locales[0]?.currencyCode ?? '';
    if (region && COUNTRY_TO_CURRENCY[region]) return COUNTRY_TO_CURRENCY[region];
    if (cur && RATES[cur]) return cur;
  } catch {
    /* ignore */
  }
  return BASE_CURRENCY;
}

const STORAGE_KEY = 'kh_currency_choice_v1';

/**
 * Store de devise mutable. Ordre de résolution : choix persisté → détection
 * IP backend (asynchrone) → repli locale/région. `useSyncExternalStore` s'y
 * abonne ; un changement (détection IP arrivée, ou sélection utilisateur)
 * notifie les abonnés.
 */
class CurrencyStore {
  private currency: string = deviceDefaultCurrency();

  private readonly listeners = new Set<() => void>();

  private bootstrapped = false;

  getCurrency = (): string => this.currency;

  subscribe = (fn: () => void): (() => void) => {
    this.listeners.add(fn);
    // Résolution paresseuse au premier abonnement (premier écran monté).
    void this.bootstrap();
    return () => {
      this.listeners.delete(fn);
    };
  };

  /** Sélection manuelle — persistée et prioritaire sur la détection IP. */
  setCurrency = (next: string): void => {
    if (!RATES[next]) return;
    this.apply(next);
    void AsyncStorage.setItem(STORAGE_KEY, next).catch(() => {});
  };

  /**
   * Relance la détection (API géo puis GPS) — appelée après que
   * l'utilisateur a accordé la permission de localisation sur l'écran
   * d'autorisations : le GPS peut maintenant résoudre le pays même si
   * l'API est injoignable. Un choix manuel persisté reste prioritaire.
   */
  redetect = (): void => {
    this.bootstrapped = false;
    void this.bootstrap();
  };

  private apply(next: string): void {
    if (next === this.currency) return;
    this.currency = next;
    this.listeners.forEach((l) => l());
  }

  private async bootstrap(): Promise<void> {
    if (this.bootstrapped) return;
    this.bootstrapped = true;

    // 1. Choix manuel persisté — prime sur tout.
    try {
      const saved = await AsyncStorage.getItem(STORAGE_KEY);
      if (saved && RATES[saved]) {
        this.apply(saved);
        return;
      }
    } catch {
      /* ignore */
    }

    // 2. Détection par IP côté backend (MaxMind). Le locale/région reste
    // affiché en attendant la réponse (pas de flash FCFA au démarrage).
    // On n'applique QUE une vraie détection (`source: 'ip'`) : quand le
    // serveur répond son repli XAF (`source: 'fallback'`), l'imposer
    // écraserait une meilleure info locale (GPS/région de l'appareil).
    try {
      const { data } = await apiClient.get<{ currency?: string; source?: string }>(
        ENDPOINTS.geo.currency,
        { timeout: 6_000 },
      );
      if (data?.source === 'ip' && data.currency && RATES[data.currency]) {
        this.apply(data.currency);
        return;
      }
    } catch {
      /* réseau indisponible → sources locales ci-dessous */
    }

    // 3. Pays via GPS (uniquement si la permission est DÉJÀ accordée —
    // jamais de prompt ici) : robuste même API down, et corrige le cas
    // « téléphone en français en Suisse » (région FR → EUR au lieu de CHF).
    try {
      const country = await deviceCountryFromGps();
      const fromGps = country ? COUNTRY_TO_CURRENCY[country] : undefined;
      if (fromGps && RATES[fromGps]) {
        this.apply(fromGps);
      }
    } catch {
      /* GPS indisponible → le repli locale/région du constructeur reste */
    }
  }
}

/**
 * Résout le code pays ISO via la position de l'appareil. Ne demande JAMAIS
 * la permission (no prompt) ; borne la lecture GPS à 4 s pour ne pas
 * retarder l'affichage.
 */
async function deviceCountryFromGps(): Promise<string | null> {
  const permission = await Location.getForegroundPermissionsAsync();
  if (!permission.granted) return null;

  const position =
    (await Location.getLastKnownPositionAsync()) ??
    (await Promise.race([
      Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.Lowest }),
      new Promise<null>((resolve) => setTimeout(() => resolve(null), 4_000)),
    ]));
  if (!position) return null;

  const places = await Location.reverseGeocodeAsync({
    latitude: position.coords.latitude,
    longitude: position.coords.longitude,
  });
  const iso = places[0]?.isoCountryCode;
  return iso ? iso.toUpperCase() : null;
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
