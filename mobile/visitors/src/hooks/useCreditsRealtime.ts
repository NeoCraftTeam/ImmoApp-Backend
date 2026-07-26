import { useQueryClient } from '@tanstack/react-query';
import { useEffect } from 'react';

import { subscribePrivate } from '@/services/echo';
import { queryKeys } from '@/lib/query-keys';

/**
 * Abonne l'utilisateur connecté à son canal privé `user.{id}` et met à
 * jour le solde de crédits + l'historique des transactions EN TEMPS RÉEL
 * dès qu'un event `credits.updated` arrive (achat crédité côté serveur via
 * webhook, dépense de crédits, remboursement) — sans polling.
 *
 * Le solde est posé à sa valeur absolue (idempotent, multi-appareils) ;
 * l'historique est invalidé pour récupérer la nouvelle transaction. No-op
 * sans user connecté ou sans config Reverb.
 */
export function useCreditsRealtime(userId: string | undefined): void {
  const qc = useQueryClient();

  useEffect(() => {
    if (!userId) return;

    const unsubscribe = subscribePrivate(
      `user.${userId}`,
      ['credits.updated'],
      (_event, raw) => {
        const data = raw as { balance?: number };
        if (typeof data?.balance === 'number') {
          qc.setQueryData(queryKeys.creditsBalance(), { point_balance: data.balance });
        }
        // La transaction diffusée n'a pas forcément la même forme que
        // l'historique des paiements — on invalide pour refetch propre.
        qc.invalidateQueries({ queryKey: queryKeys.paymentsHistory() });
      },
    );

    return unsubscribe;
  }, [userId, qc]);
}
