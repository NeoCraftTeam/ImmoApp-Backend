import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type { SearchAlert } from '@/types/search-alert';

interface ListResponse {
  data: SearchAlert[];
}

export function useSearchAlerts() {
  return useQuery<ListResponse, Error, SearchAlert[]>({
    queryKey: ['search-alerts'],
    queryFn: async () => {
      const { data } = await apiClient.get<ListResponse>(
        ENDPOINTS.searchAlerts.list,
      );
      return data;
    },
    select: (payload) => (Array.isArray(payload?.data) ? payload.data : []),
    staleTime: 60 * 1000,
  });
}

export function useCreateSearchAlert() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (input: Omit<SearchAlert, 'id' | 'created_at'>) => {
      const { data } = await apiClient.post(ENDPOINTS.searchAlerts.create, input);
      return data;
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['search-alerts'] }),
  });
}

export function useUpdateSearchAlert() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, ...patch }: Partial<SearchAlert> & { id: string }) => {
      const { data } = await apiClient.put(
        ENDPOINTS.searchAlerts.update(id),
        patch,
      );
      return data;
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['search-alerts'] }),
  });
}

export function useDeleteSearchAlert() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (id: string) => {
      await apiClient.delete(ENDPOINTS.searchAlerts.delete(id));
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['search-alerts'] }),
  });
}
