import { useMutation, useQueryClient } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type { LeaseContract } from '@/types/owner';

/** POST /my/lease-contracts/{adId}/generate — créer un bail depuis une annonce. */
export function useGenerateLease() {
  const qc = useQueryClient();
  return useMutation<
    LeaseContract,
    Error,
    {
      adId: string;
      tenant_id: string;
      lease_start: string;
      lease_end: string;
      monthly_rent: number;
      deposit?: number;
    }
  >({
    mutationFn: async ({ adId, ...payload }) => {
      const { data } = await apiClient.post<{ data: LeaseContract }>(
        ENDPOINTS.my.leaseGenerate(adId),
        payload,
      );
      return data.data;
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['leases'] }),
  });
}

export function useRenewLease() {
  const qc = useQueryClient();
  return useMutation<LeaseContract, Error, { id: string; new_end_date: string }>({
    mutationFn: async ({ id, new_end_date }) => {
      const { data } = await apiClient.post<{ data: LeaseContract }>(
        ENDPOINTS.my.leaseRenew(id),
        { new_end_date },
      );
      return data.data;
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['leases'] }),
  });
}

export function useTerminateLease() {
  const qc = useQueryClient();
  return useMutation<LeaseContract, Error, { id: string; reason?: string }>({
    mutationFn: async ({ id, reason }) => {
      const { data } = await apiClient.post<{ data: LeaseContract }>(
        ENDPOINTS.my.leaseTerminate(id),
        { reason },
      );
      return data.data;
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['leases'] }),
  });
}
