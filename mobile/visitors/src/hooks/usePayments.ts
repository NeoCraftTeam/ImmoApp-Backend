import { useQuery } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type { PaymentTransaction, PaymentsResponse } from '@/types/payment';

export function usePayments() {
  return useQuery<PaymentsResponse, Error, PaymentTransaction[]>({
    queryKey: ['payments-history'],
    queryFn: async () => {
      const { data } = await apiClient.get<PaymentsResponse>(
        ENDPOINTS.payments.history,
      );
      return data;
    },
    select: (payload) => (Array.isArray(payload?.data) ? payload.data : []),
    staleTime: 60 * 1000,
  });
}

export function useCreditsBalance() {
  return useQuery<Record<string, unknown> | number, Error, number>({
    queryKey: ['credits-balance'],
    queryFn: async () => {
      const { data } = await apiClient.get(ENDPOINTS.credits.balance);
      return data;
    },
    // Le backend renvoie { point_balance: N } — l'ancienne lecture de
    // `balance` retombait toujours à 0 (d'où « 0 crédits » alors que le
    // web affiche le vrai solde). On accepte point_balance / balance /
    // credit_balance, à la racine ou sous `data`.
    select: (payload) => {
      if (typeof payload === 'number') return payload;
      const root = (payload ?? {}) as Record<string, unknown>;
      const nested = (root.data ?? {}) as Record<string, unknown>;
      for (const src of [root, nested]) {
        for (const key of ['point_balance', 'balance', 'credit_balance', 'credits']) {
          const v = src[key];
          if (typeof v === 'number') return v;
        }
      }
      return 0;
    },
    staleTime: 30 * 1000,
  });
}
