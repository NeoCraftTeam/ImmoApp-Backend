import { RESOLVED_BASE_URL } from '@/api/client';

/**
 * Scope TanStack Query keys to the active API base URL so a switch
 * preprod → prod (or LAN → prod) never rehydrates wallet data from
 * another environment's AsyncStorage snapshot.
 */
export const QUERY_API_SCOPE = RESOLVED_BASE_URL;

export const queryKeys = {
  me: () => ['me', QUERY_API_SCOPE] as const,
  creditsBalance: () => ['credits-balance', QUERY_API_SCOPE] as const,
  paymentsHistory: () => ['payments-history', QUERY_API_SCOPE] as const,
  creditPackages: () => ['credit-packages', QUERY_API_SCOPE] as const,
  paymentStatus: (txRef: string) => ['payment-status', QUERY_API_SCOPE, txRef] as const,
} as const;

/** Query roots that must never be persisted offline (live wallet / auth). */
export const NON_PERSISTED_QUERY_ROOTS = new Set([
  'me',
  'notifications-unread-count',
  'credits-balance',
  'payments-history',
  'payment-status',
  'credit-packages',
]);
