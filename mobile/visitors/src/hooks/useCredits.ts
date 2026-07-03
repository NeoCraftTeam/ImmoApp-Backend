import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';

export interface CreditPackage {
  id: string;
  name: string;
  /** Nombre de crédits (points) octroyés. */
  credits?: number;
  points?: number;
  price: number;
  currency?: string;
  /** Réductions / mise en avant éventuelles. */
  is_popular?: boolean;
  bonus_points?: number;
}

interface PackagesResponse {
  data?: CreditPackage[];
}

/** GET /credits/packages — catalogue d'achat de crédits. */
export function useCreditPackages(enabled = true) {
  return useQuery<PackagesResponse, Error, CreditPackage[]>({
    queryKey: ['credit-packages'],
    queryFn: async () => {
      const { data } = await apiClient.get<PackagesResponse>(ENDPOINTS.credits.packages);
      return data;
    },
    select: (p) => (Array.isArray(p?.data) ? p.data : []),
    enabled,
    staleTime: 5 * 60 * 1000,
  });
}

interface PurchaseResponse {
  payment_link?: string;
  payment_url?: string;
  tx_ref: string;
  gateway?: string;
  status?: string;
}

/** POST /credits/purchase/{package} — initie le paiement (checkout hébergé). */
export function usePurchaseCredits() {
  return useMutation<PurchaseResponse, Error, { packageId: string; callback_url?: string }>({
    mutationFn: async ({ packageId, ...payload }) => {
      const { data } = await apiClient.post<PurchaseResponse>(
        ENDPOINTS.credits.purchase(packageId),
        payload,
      );
      return data;
    },
  });
}

interface VerifyResponse {
  point_balance?: number;
  status?: string;
}

/** POST /credits/verify-purchase — met à jour le solde dès le retour du checkout. */
export function useVerifyCreditPurchase() {
  const qc = useQueryClient();
  return useMutation<VerifyResponse, Error, { tx_ref?: string; reference?: string }>({
    mutationFn: async (input) => {
      const { data } = await apiClient.post<VerifyResponse>(
        ENDPOINTS.credits.verifyPurchase,
        input,
      );
      return data;
    },
    onSuccess: (data) => {
      qc.invalidateQueries({ queryKey: ['credits-balance'] });
      qc.invalidateQueries({ queryKey: ['payments-history'] });
      if (typeof data.point_balance === 'number') {
        qc.setQueryData(['credits-balance'], { point_balance: data.point_balance });
      }
    },
  });
}
