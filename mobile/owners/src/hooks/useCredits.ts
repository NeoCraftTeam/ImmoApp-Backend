import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type {
  CreditBalance,
  CreditPackage,
  VerifyCreditPurchaseResponse,
} from '@/types/credits';

/* ============================================================
 * BALANCE
 * ============================================================ */

/**
 * GET /credits/balance — solde de points. Le backend peut renvoyer
 * `{point_balance}` directement, `{balance}`, ou `{data: {…}}` selon
 * l'historique — on normalise tout en un number.
 */
export function useCreditsBalance(enabled = true) {
  return useQuery<unknown, Error, number>({
    queryKey: ['credits-balance'],
    queryFn: async () => {
      const { data } = await apiClient.get<unknown>(ENDPOINTS.credits.balance);
      return data;
    },
    select: (payload) => extractBalance(payload),
    enabled,
    staleTime: 30 * 1000,
  });
}

export function extractBalance(payload: unknown): number {
  if (typeof payload === 'number') return payload;
  if (payload && typeof payload === 'object') {
    const obj = payload as Record<string, unknown>;
    if (typeof obj.point_balance === 'number') return obj.point_balance;
    if (typeof obj.balance === 'number') return obj.balance;
    const inner = obj.data as Record<string, unknown> | undefined;
    if (inner) {
      if (typeof inner.point_balance === 'number') return inner.point_balance;
      if (typeof inner.balance === 'number') return inner.balance;
    }
  }
  return 0;
}

/* ============================================================
 * PACKAGES — catalogue achat crédits
 * ============================================================ */

interface PackagesResponse {
  data?: CreditPackage[];
}

export function useCreditPackages(enabled = true) {
  return useQuery<PackagesResponse, Error, CreditPackage[]>({
    queryKey: ['credit-packages'],
    queryFn: async () => {
      const { data } = await apiClient.get<PackagesResponse>(
        ENDPOINTS.credits.packages,
      );
      return data;
    },
    select: (p) => (Array.isArray(p?.data) ? p.data : []),
    enabled,
    staleTime: 5 * 60 * 1000,
  });
}

/* ============================================================
 * PURCHASE — initie l'achat d'un package crédit
 * ============================================================ */

interface PurchaseResponse {
  payment_link?: string;
  payment_url?: string;
  tx_ref: string;
  gateway: string;
  status: string;
}

export function usePurchaseCredits() {
  return useMutation<
    PurchaseResponse,
    Error,
    {
      packageId: string;
      payment_method?: string;
      payment_method_id?: string;
      save_payment_method?: boolean;
      callback_url?: string;
    }
  >({
    mutationFn: async ({ packageId, ...payload }) => {
      const { data } = await apiClient.post<PurchaseResponse>(
        ENDPOINTS.credits.purchase(packageId),
        payload,
      );
      return data;
    },
  });
}

/**
 * POST /credits/verify-purchase — idempotent, à appeler dès qu'on a
 * un tx_ref pour mettre à jour le balance plus vite que le webhook.
 */
export function useVerifyCreditPurchase() {
  const qc = useQueryClient();
  return useMutation<
    VerifyCreditPurchaseResponse,
    Error,
    { tx_ref?: string; reference?: string; gateway_redirect_status?: string }
  >({
    mutationFn: async (input) => {
      const { data } = await apiClient.post<VerifyCreditPurchaseResponse>(
        ENDPOINTS.credits.verifyPurchase,
        input,
      );
      return data;
    },
    onSuccess: (data) => {
      qc.invalidateQueries({ queryKey: ['credits-balance'] });
      if (data.point_balance != null) {
        qc.setQueryData<CreditBalance>(['credits-balance'], {
          point_balance: data.point_balance,
        });
      }
    },
  });
}
