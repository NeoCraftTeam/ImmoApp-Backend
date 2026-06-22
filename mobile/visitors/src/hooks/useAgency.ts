import { useQuery } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type { Agency } from '@/types/agency';

export function useAgency(id: string | undefined) {
  return useQuery<{ data: Agency } | Agency, Error, Agency>({
    queryKey: ['agency', id],
    queryFn: async () => {
      if (!id) throw new Error('Missing agency id');
      const { data } = await apiClient.get(ENDPOINTS.agencies.detail(id));
      return data;
    },
    select: (payload) =>
      ('data' in (payload as { data?: unknown })
        ? (payload as { data: Agency }).data
        : (payload as Agency)),
    enabled: Boolean(id),
    staleTime: 5 * 60 * 1000,
  });
}
