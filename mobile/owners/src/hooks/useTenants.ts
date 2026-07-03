import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type { Tenant } from '@/types/owner';

export interface TenantInput {
  /** Nom complet (champ backend `name`). */
  name: string;
  phone?: string;
  email?: string;
  id_number?: string;
  notes?: string;
}

/** GET /my/tenants. */
export function useTenants(enabled = true) {
  return useQuery<{ data: Tenant[] }, Error, Tenant[]>({
    queryKey: ['tenants'],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: Tenant[] }>(ENDPOINTS.my.tenants, {
        params: { per_page: 50 },
      });
      return data;
    },
    select: (p) => (Array.isArray(p?.data) ? p.data : []),
    enabled,
    staleTime: 2 * 60 * 1000,
  });
}

/** POST /my/tenants. */
export function useCreateTenant() {
  const qc = useQueryClient();
  return useMutation<Tenant, Error, TenantInput>({
    mutationFn: async (input) => {
      const { data } = await apiClient.post<{ data?: Tenant } | Tenant>(
        ENDPOINTS.my.tenants,
        input,
      );
      return (data as { data?: Tenant }).data ?? (data as Tenant);
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['tenants'] }),
  });
}

/** PUT /my/tenants/{id}. */
export function useUpdateTenant() {
  const qc = useQueryClient();
  return useMutation<Tenant, Error, { id: string; input: TenantInput }>({
    mutationFn: async ({ id, input }) => {
      const { data } = await apiClient.put<{ data?: Tenant } | Tenant>(
        ENDPOINTS.my.tenant(id),
        input,
      );
      return (data as { data?: Tenant }).data ?? (data as Tenant);
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['tenants'] }),
  });
}

/** DELETE /my/tenants/{id}. */
export function useDeleteTenant() {
  const qc = useQueryClient();
  return useMutation<void, Error, string>({
    mutationFn: async (id) => {
      await apiClient.delete(ENDPOINTS.my.tenant(id));
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['tenants'] }),
  });
}
