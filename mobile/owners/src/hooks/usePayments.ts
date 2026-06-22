import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type {
  InitiatePaymentInput,
  InitiatePaymentResponse,
  PaymentEntry,
  PaymentHistory,
  PaymentMethod,
  PublicPaymentStatus,
  SavedCard,
} from '@/types/payment';

/* ============================================================
 * HISTORY
 * ============================================================ */

export function usePayments(enabled = true) {
  return useQuery<PaymentHistory>({
    queryKey: ['payments-history'],
    queryFn: async () => {
      const { data } = await apiClient.get<PaymentHistory>(
        ENDPOINTS.payments.history,
        { params: { per_page: 50 } },
      );
      return data;
    },
    enabled,
    staleTime: 60 * 1000,
  });
}

/* ============================================================
 * METHODS — config admin (mobile money / card / wallet)
 * ============================================================ */

interface MethodsResponse {
  data?: PaymentMethod[];
}

export function usePaymentMethods(enabled = true) {
  return useQuery<MethodsResponse, Error, PaymentMethod[]>({
    queryKey: ['payment-methods'],
    queryFn: async () => {
      const { data } = await apiClient.get<MethodsResponse>(
        ENDPOINTS.payments.methods,
      );
      return data;
    },
    select: (p) => (Array.isArray(p?.data) ? p.data : []),
    enabled,
    staleTime: 5 * 60 * 1000,
  });
}

/* ============================================================
 * INITIATE — orchestre l'achat (credit / subscription / boost…)
 * ============================================================ */

/**
 * POST /payments/initiate_payment — déclenche le checkout côté gateway.
 * Le caller :
 *   1. Récupère `payment_link` / `payment_url`
 *   2. Ouvre le lien via `expo-web-browser` (auth session) avec retour
 *      `keyhome://payment-success?tx_ref={tx_ref}`
 *   3. À la fermeture, redirige vers `/payment-success` avec ce tx_ref.
 *
 * Aucune navigation n'est faite ici — le hook est un pur conduit
 * vers l'API pour rester testable et permettre de l'utiliser depuis
 * plusieurs flows (subscriptions, credits, boost, services pro).
 */
export function useInitiatePayment() {
  return useMutation<InitiatePaymentResponse, Error, InitiatePaymentInput>({
    mutationFn: async (input) => {
      const { data } = await apiClient.post<InitiatePaymentResponse>(
        ENDPOINTS.payments.initiate,
        input,
      );
      return data;
    },
  });
}

export function useVerifyPayment() {
  const qc = useQueryClient();
  return useMutation<
    { status: string; payment?: PaymentEntry },
    Error,
    { tx_ref?: string; reference?: string }
  >({
    mutationFn: async (input) => {
      const { data } = await apiClient.post<{
        status: string;
        payment?: PaymentEntry;
      }>(ENDPOINTS.payments.verify, input);
      return data;
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['credits-balance'] });
      qc.invalidateQueries({ queryKey: ['payments-history'] });
      qc.invalidateQueries({ queryKey: ['current-subscription'] });
    },
  });
}

export function useCancelPayment() {
  return useMutation<void, Error, string>({
    mutationFn: async (tx_ref) => {
      await apiClient.post(ENDPOINTS.payments.cancel, { tx_ref });
    },
  });
}

/* ============================================================
 * STATUS — polling public post-checkout
 * ============================================================ */

export function usePublicPaymentStatus(txRef: string | undefined) {
  return useQuery<
    { data: PublicPaymentStatus } | PublicPaymentStatus,
    Error,
    PublicPaymentStatus
  >({
    queryKey: ['payment-status', txRef],
    queryFn: async () => {
      if (!txRef) throw new Error('Missing tx_ref');
      const { data } = await apiClient.get(
        ENDPOINTS.payments.publicStatus(txRef),
      );
      return data;
    },
    select: (payload) =>
      'data' in (payload as { data?: unknown })
        ? (payload as { data: PublicPaymentStatus }).data
        : (payload as PublicPaymentStatus),
    enabled: Boolean(txRef),
    refetchInterval: (q) => {
      const status = (q.state.data as PublicPaymentStatus | undefined)?.status;
      return status === 'pending' || !status ? 3000 : false;
    },
  });
}

/* ============================================================
 * RECEIPT — URL d'un reçu PDF signé (pour partager / télécharger)
 * ============================================================ */

export function buildReceiptUrl(paymentId: string): string {
  return ENDPOINTS.payments.receipt(paymentId);
}

/* ============================================================
 * REFUND REQUEST — demande de remboursement sur paiement
 * ============================================================ */

export function useRequestPaymentRefund() {
  const qc = useQueryClient();
  return useMutation<void, Error, string>({
    mutationFn: async (paymentId) => {
      await apiClient.post(ENDPOINTS.payments.refundRequest(paymentId));
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['refunds'] });
      qc.invalidateQueries({ queryKey: ['payments-history'] });
    },
  });
}

/* ============================================================
 * STRIPE — cartes sauvegardées
 * ============================================================ */

interface StripeMethodsResponse {
  data?: SavedCard[];
}

export function useStripeMethods(enabled = true) {
  return useQuery<StripeMethodsResponse, Error, SavedCard[]>({
    queryKey: ['stripe-methods'],
    queryFn: async () => {
      const { data } = await apiClient.get<StripeMethodsResponse>(
        ENDPOINTS.payments.stripeMethods,
      );
      return data;
    },
    select: (p) => (Array.isArray(p?.data) ? p.data : []),
    enabled,
    staleTime: 60 * 1000,
  });
}

export function useDeleteStripeMethod() {
  const qc = useQueryClient();
  return useMutation<void, Error, string>({
    mutationFn: async (pmId) => {
      await apiClient.delete(ENDPOINTS.payments.stripeMethod(pmId));
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['stripe-methods'] }),
  });
}

export function useSetDefaultStripeMethod() {
  const qc = useQueryClient();
  return useMutation<void, Error, string>({
    mutationFn: async (pmId) => {
      await apiClient.post(ENDPOINTS.payments.stripeSetDefault(pmId));
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['stripe-methods'] }),
  });
}
