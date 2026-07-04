import { useSyncExternalStore } from 'react';

import { currencyStore, formatFromXAF } from '@/services/currency';

/**
 * Devise d'affichage réactive, adossée au singleton `currencyStore`
 * (aucune requête réseau — taux statiques). `format(amountXAF)` rend le
 * montant XAF dans la devise courante.
 */
export function useCurrency() {
  const currency = useSyncExternalStore(
    currencyStore.subscribe,
    currencyStore.getCurrency,
    currencyStore.getCurrency,
  );

  return {
    currency,
    setCurrency: currencyStore.setCurrency,
    format: (amountXAF: number) => formatFromXAF(amountXAF, currency),
  };
}
