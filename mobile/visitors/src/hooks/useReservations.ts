import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type { Reservation } from '@/types/reservation';

interface ListResponse {
  data: Reservation[];
}

/** Créneau réservable, aplati depuis `slots_by_date` du backend. */
export interface ViewingSlot {
  /** Jour du créneau au format `YYYY-MM-DD`. */
  date: string;
  /** Heure de début `HH:MM`. */
  starts_at: string;
  /** Heure de fin `HH:MM`. */
  ends_at: string;
  is_available: boolean;
}

interface SlotsResponse {
  data?: {
    ad_id?: string;
    slot_duration_minutes?: number;
    slots_by_date?: Record<
      string,
      { starts_at: string; ends_at: string; is_available: boolean }[]
    >;
  };
}

/**
 * GET /ads/{ad}/slots — créneaux de visite. Le backend renvoie un objet
 * `slots_by_date` ({ "YYYY-MM-DD": [{starts_at, ends_at, is_available}] }) ;
 * on l'aplatit en liste chronologique et on ne garde que les disponibles.
 */
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
    select: (payload) => {
      const byDate = payload?.data?.slots_by_date ?? {};
      const flat: ViewingSlot[] = [];
      for (const date of Object.keys(byDate).sort()) {
        for (const s of byDate[date] ?? []) {
          if (s.is_available) {
            flat.push({ date, starts_at: s.starts_at, ends_at: s.ends_at, is_available: true });
          }
        }
      }
      return flat;
    },
    enabled: Boolean(adId),
    staleTime: 30 * 1000,
  });
}

export function useCreateReservation(adId: string | undefined) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (input: {
      slot_date: string;
      slot_starts_at: string;
      slot_ends_at: string;
      client_message?: string;
    }) => {
      if (!adId) throw new Error('Missing ad id');
      const { data } = await apiClient.post(ENDPOINTS.viewings.reserve(adId), input);
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
    mutationFn: async (input: { id: string; cancellation_reason?: string }) => {
      await apiClient.delete(ENDPOINTS.viewings.cancel(input.id), {
        data: { cancellation_reason: input.cancellation_reason },
      });
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['my-reservations'] });
    },
  });
}
