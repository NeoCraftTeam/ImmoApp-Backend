import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type { CurrentSubscription, SubscriptionPlan } from '@/types/owner';

/** GET /subscriptions/plans (public, cached). */
export function useSubscriptionPlans() {
  return useQuery<{ data: SubscriptionPlan[] }, Error, SubscriptionPlan[]>({
    queryKey: ['subscription-plans'],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: SubscriptionPlan[] }>(
        ENDPOINTS.subscriptions.plans,
      );
      return data;
    },
    select: (p) =>
      (Array.isArray(p?.data) ? p.data : []).sort(
        (a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0),
      ),
    staleTime: 30 * 60 * 1000,
  });
}

/**
 * GET /subscriptions/current. Le backend renvoie directement
 * { has_subscription, subscription, stats } — PAS d'enveloppe `data`.
 */
export function useCurrentSubscription(enabled = true) {
  return useQuery<CurrentSubscription, Error, CurrentSubscription>({
    queryKey: ['subscription-current'],
    queryFn: async () => {
      const { data } = await apiClient.get<CurrentSubscription>(
        ENDPOINTS.subscriptions.current,
      );
      return data;
    },
    enabled,
    staleTime: 2 * 60 * 1000,
  });
}

/**
 * POST /subscriptions/subscribe — initiates the payment for a plan.
 * Returns the gateway payment link / client secret so the screen can
 * route the owner to checkout.
 */
export function useSubscribe() {
  const qc = useQueryClient();
  return useMutation<
    { payment?: { payment_link?: string } } & Record<string, unknown>,
    Error,
    { plan_id: string; billing_period: 'monthly' | 'yearly' }
  >({
    mutationFn: async (input) => {
      const { data } = await apiClient.post(ENDPOINTS.subscriptions.subscribe, input);
      return data;
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['subscription-current'] }),
  });
}

/** POST /subscriptions/cancel. */
export function useCancelSubscription() {
  const qc = useQueryClient();
  return useMutation<void, Error, void>({
    mutationFn: async () => {
      await apiClient.post(ENDPOINTS.subscriptions.cancel);
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['subscription-current'] }),
  });
}

/** PATCH /subscriptions/auto-renew. */
export function useToggleAutoRenew() {
  const qc = useQueryClient();
  return useMutation<void, Error, boolean>({
    mutationFn: async (autoRenew) => {
      await apiClient.patch(ENDPOINTS.subscriptions.autoRenew, { auto_renew: autoRenew });
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['subscription-current'] }),
  });
}
