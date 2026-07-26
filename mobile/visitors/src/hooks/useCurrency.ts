import { useSyncExternalStore } from 'react';

import { currencyStore, formatFromXAF } from '@/services/currency';

/**
 * Devise d'affichage adossée au singleton `currencyStore` (aucune requête
 * réseau — taux statiques). La devise est résolue AUTOMATIQUEMENT depuis
 * l'appareil (pas de choix manuel), et `format(amountXAF)` rend le montant
 * XAF dans cette devise. Affichage uniquement : aucun `setCurrency`.
 */
export function useCurrency() {
  const currency = useSyncExternalStore(
    currencyStore.subscribe,
    currencyStore.getCurrency,
    currencyStore.getCurrency,
  );

  return {
    currency,
    format: (amountXAF: number) => formatFromXAF(amountXAF, currency),
  };
}
