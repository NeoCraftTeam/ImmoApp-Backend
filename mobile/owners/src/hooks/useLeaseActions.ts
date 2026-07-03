import { useMutation, useQueryClient } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type { LeaseContract } from '@/types/owner';

/**
 * POST /my/lease-contracts/{adId}/generate — créer un bail depuis une
 * annonce. Contrat backend (GenerateLeaseContractRequest) : le locataire
 * est passé par ses coordonnées (tenant_name + tenant_phone requis),
 * pas par un tenant_id, et la fin de bail se calcule via
 * lease_duration_months (1–120).
 */
export function useGenerateLease() {
  const qc = useQueryClient();
  return useMutation<
    LeaseContract,
    Error,
    {
      adId: string;
      tenant_name: string;
      tenant_phone: string;
      tenant_email?: string;
      tenant_id_number?: string;
      unit_reference?: string;
      lease_start: string;
      lease_duration_months: number;
      monthly_rent?: number;
      deposit_amount?: number;
      special_conditions?: string;
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

/** POST /my/lease-contracts/{id}/renew — prolonge de N mois (1–120). */
export function useRenewLease() {
  const qc = useQueryClient();
  return useMutation<LeaseContract, Error, { id: string; extend_months: number }>({
    mutationFn: async ({ id, extend_months }) => {
      const { data } = await apiClient.post<{ data: LeaseContract }>(
        ENDPOINTS.my.leaseRenew(id),
        { extend_months },
      );
      return data.data;
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['leases'] }),
  });
}

/** POST /my/lease-contracts/{id}/terminate — motif requis (3–1000 car.). */
export function useTerminateLease() {
  const qc = useQueryClient();
  return useMutation<LeaseContract, Error, { id: string; reason: string }>({
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
