import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type { Reservation } from '@/types/reservation';

interface ListResponse {
  data: Reservation[];
}

export interface ViewingSlot {
  id: string;
  starts_at: string;
  ends_at?: string | null;
  is_booked?: boolean;
}

interface SlotsResponse {
  data: ViewingSlot[];
}

export function useViewingSlots(adId: string | undefined) {
  return useQuery<SlotsResponse, Error, ViewingSlot[]>({
    queryKey: ['viewing-slots', adId],
    queryFn: async () => {
      if (!adId) throw new Error('Missing ad id');
      const { data } = await apiClient.get<SlotsResponse>(
        ENDPOINTS.viewings.slots(adId),
      );
      return data;
    },
    select: (payload) =>
      (Array.isArray(payload?.data) ? payload.data : []).filter((s) => !s.is_booked),
    enabled: Boolean(adId),
    staleTime: 30 * 1000,
  });
}

export function useCreateReservation(adId: string | undefined) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (input: { slot_id: string; notes?: string }) => {
      if (!adId) throw new Error('Missing ad id');
      const { data } = await apiClient.post(
        ENDPOINTS.viewings.reserve(adId),
        input,
      );
      return data;
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['my-reservations'] });
      qc.invalidateQueries({ queryKey: ['viewing-slots', adId] });
    },
  });
}

export function useMyReservations() {
  return useQuery<ListResponse, Error, Reservation[]>({
    queryKey: ['my-reservations'],
    queryFn: async () => {
      const { data } = await apiClient.get<ListResponse>(
        ENDPOINTS.my.reservations,
      );
      return data;
    },
    select: (payload) => (Array.isArray(payload?.data) ? payload.data : []),
    staleTime: 60 * 1000,
  });
}

export function useCancelReservation() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (input: { id: string; reason?: string }) => {
      await apiClient.delete(ENDPOINTS.viewings.cancel(input.id), {
        data: { reason: input.reason },
      });
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['my-reservations'] });
    },
  });
}
