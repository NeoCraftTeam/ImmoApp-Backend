import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type { BoostPack, BoostStatus } from '@/types/owner';

/** GET /boost-packs — available boost packs (public). */
export function useBoostPacks() {
  return useQuery<{ data: BoostPack[] }, Error, BoostPack[]>({
    queryKey: ['boost-packs'],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: BoostPack[] }>(ENDPOINTS.boost.packs);
      return data;
    },
    select: (p) => (Array.isArray(p?.data) ? p.data : []),
    staleTime: 30 * 60 * 1000,
  });
}

/** GET /my/ads/{id}/boost-status. */
export function useBoostStatus(adId: string | undefined) {
  return useQuery<{ data: BoostStatus }, Error, BoostStatus>({
    queryKey: ['boost-status', adId],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: BoostStatus }>(
        ENDPOINTS.my.boostStatus(adId as string),
      );
      return data;
    },
    select: (p) => p.data,
    enabled: !!adId,
    staleTime: 60 * 1000,
  });
}

/** POST /my/ads/{id}/boost — spend credits to boost. */
export function useApplyBoost(adId: string | undefined) {
  const qc = useQueryClient();
  return useMutation<unknown, Error, string>({
    mutationFn: async (boostPackId) => {
      if (!adId) throw new Error('Missing ad id');
      const { data } = await apiClient.post(ENDPOINTS.my.boost(adId), {
        boost_pack_id: boostPackId,
      });
      return data;
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['boost-status', adId] });
      qc.invalidateQueries({ queryKey: ['ad', adId] });
      qc.invalidateQueries({ queryKey: ['my-ads'] });
      qc.invalidateQueries({ queryKey: ['credits-balance'] });
    },
  });
}

/** DELETE /my/ads/{id}/boost — remove the active boost. */
export function useRemoveBoost(adId: string | undefined) {
  const qc = useQueryClient();
  return useMutation<void, Error, void>({
    mutationFn: async () => {
      if (!adId) return;
      await apiClient.delete(ENDPOINTS.my.boost(adId));
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['boost-status', adId] });
      qc.invalidateQueries({ queryKey: ['ad', adId] });
    },
  });
}

/** GET /credits/balance. */
export function useCreditsBalance(enabled = true) {
  return useQuery<{ balance: number } | { data: { balance: number } }, Error, number>({
    queryKey: ['credits-balance'],
    queryFn: async () => {
      const { data } = await apiClient.get(ENDPOINTS.credits.balance);
      return data;
    },
    select: (p) => {
      const flat = (p as { balance?: number }).balance;
      const nested = (p as { data?: { balance?: number } }).data?.balance;
      return flat ?? nested ?? 0;
    },
    enabled,
    staleTime: 60 * 1000,
  });
}
