import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import { queryKeys } from '@/lib/query-keys';

export interface CreditPackage {
  id: string;
  name: string;
  description?: string | null;
  badge?: string | null;
  /** Crédits octroyés — champ backend `points_awarded`. */
  points_awarded?: number;
  /** Prix en XAF (entier) — le backend stocke toujours en FCFA. */
  price: number;
  price_formatted?: string;
  features?: string[];
  is_popular?: boolean;
  sort_order?: number;
}

interface PackagesResponse {
  data?: CreditPackage[];
}

/** GET /credits/packages — catalogue d'achat de crédits. */
export function useCreditPackages(enabled = true) {
  return useQuery<PackagesResponse, Error, CreditPackage[]>({
    queryKey: queryKeys.creditPackages(),
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
  return useMutation<
    PurchaseResponse,
    Error,
    { packageId: string; callback_url?: string; payment_method?: 'mobile_money' | 'orange_money' | 'card' }
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

export interface UnlockAdResponse {
  status: 'unlocked' | 'already_unlocked' | 'owner' | 'insufficient_points';
  message?: string;
  point_balance?: number;
  unlock_cost?: number;
  packages?: unknown[];
}

/**
 * POST /payments/initialize/{ad} — déverrouille le contact d'une annonce
 * en dépensant des crédits. 402 = solde insuffisant (le backend joint le
 * catalogue de packs) ; le caller route alors vers l'écran crédits.
 */
export function useUnlockAd() {
  const qc = useQueryClient();
  return useMutation<UnlockAdResponse, Error, { adId: string; slugOrId: string }>({
    mutationFn: async ({ adId }) => {
      const { data } = await apiClient.post<UnlockAdResponse>(
        ENDPOINTS.payments.unlock(adId),
      );
      return data;
    },
    onSuccess: (data, { slugOrId, adId }) => {
      // Refetch l'annonce sous ses deux identifiants possibles (slug sur la
      // fiche, id ailleurs) pour matérialiser adresse/téléphone déverrouillés.
      qc.invalidateQueries({ queryKey: ['ad', slugOrId] });
      qc.invalidateQueries({ queryKey: ['ad', adId] });
      qc.invalidateQueries({ queryKey: queryKeys.creditsBalance() });
      qc.invalidateQueries({ queryKey: queryKeys.paymentsHistory() });
      qc.invalidateQueries({ queryKey: ['conversations'] });
      if (typeof data.point_balance === 'number') {
        qc.setQueryData(queryKeys.creditsBalance(), { point_balance: data.point_balance });
      }
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
  return useMutation<VerifyResponse, Error, { tx_ref?: string; reference?: string; gateway_redirect_status?: string }>({
    mutationFn: async (input) => {
      const { data } = await apiClient.post<VerifyResponse>(
        ENDPOINTS.credits.verifyPurchase,
        input,
      );
      return data;
    },
    onSuccess: (data) => {
      qc.invalidateQueries({ queryKey: queryKeys.creditsBalance() });
      qc.invalidateQueries({ queryKey: queryKeys.paymentsHistory() });
      if (typeof data.point_balance === 'number') {
        qc.setQueryData(queryKeys.creditsBalance(), { point_balance: data.point_balance });
      }
    },
  });
}
