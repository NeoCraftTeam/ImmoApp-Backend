import { useSyncExternalStore } from 'react';

import {
  currencyStore,
  formatCompactFromXAF,
  formatFromXAF,
  SUPPORTED_CURRENCIES,
  symbolFor,
} from '@/services/currency';

/**
 * Devise d'affichage adossée au store `currencyStore`. La devise est
 * résolue automatiquement (choix persisté → détection IP backend → région
 * de l'appareil), et modifiable via `setCurrency`. `format(amountXAF)` rend
 * un montant XAF dans la devise active. Les prix restent stockés/payés en
 * FCFA — l'affichage seul change.
 */
export function useCurrency() {
  const currency = useSyncExternalStore(
    currencyStore.subscribe,
    currencyStore.getCurrency,
    currencyStore.getCurrency,
  );

  return {
    currency,
    symbol: symbolFor(currency),
    supported: SUPPORTED_CURRENCIES,
    setCurrency: currencyStore.setCurrency,
    format: (amountXAF: number) => formatFromXAF(amountXAF, currency),
    /** Pastilles carte : pas de décimales, abrégé (18,5 k / 1,2 M). */
    formatCompact: (amountXAF: number) => formatCompactFromXAF(amountXAF, currency),
  };
}
