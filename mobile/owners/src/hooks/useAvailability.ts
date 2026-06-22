import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type { AvailabilityPayload, AvailabilitySlot } from '@/types/availability';

interface SlotsResponse {
  data?: AvailabilitySlot[];
}

/** GET /ads/{adId}/availability — créneaux de visite pour une annonce. */
export function useAdAvailability(adId: string | undefined, enabled = true) {
  return useQuery<SlotsResponse, Error, AvailabilitySlot[]>({
    queryKey: ['ad-availability', adId],
    queryFn: async () => {
      if (!adId) return { data: [] };
      const { data } = await apiClient.get<SlotsResponse>(
        ENDPOINTS.availability.list(adId),
      );
      return data;
    },
    select: (p) => (Array.isArray(p?.data) ? p.data : []),
    enabled: enabled && !!adId,
    staleTime: 30 * 1000,
  });
}

export function useCreateAvailability(adId: string) {
  const qc = useQueryClient();
  return useMutation<AvailabilitySlot, Error, AvailabilityPayload>({
    mutationFn: async (payload) => {
      const { data } = await apiClient.post<{ data: AvailabilitySlot }>(
        ENDPOINTS.availability.create(adId),
        payload,
      );
      return data.data;
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['ad-availability', adId] }),
  });
}

export function useUpdateAvailability(adId: string) {
  const qc = useQueryClient();
  return useMutation<
    AvailabilitySlot,
    Error,
    { id: string; payload: Partial<AvailabilityPayload> }
  >({
    mutationFn: async ({ id, payload }) => {
      const { data } = await apiClient.patch<{ data: AvailabilitySlot }>(
        ENDPOINTS.availability.update(adId, id),
        payload,
      );
      return data.data;
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['ad-availability', adId] }),
  });
}

export function useDeleteAvailability(adId: string) {
  const qc = useQueryClient();
  return useMutation<void, Error, string>({
    mutationFn: async (id) => {
      await apiClient.delete(ENDPOINTS.availability.delete(adId, id));
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['ad-availability', adId] }),
  });
}
