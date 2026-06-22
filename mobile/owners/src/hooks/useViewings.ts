import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type { ViewingReservation } from '@/types/owner';

/** GET /my/viewing-reservations — landlord inbox of viewing requests. */
export function useViewings(status?: string, enabled = true) {
  return useQuery<{ data: ViewingReservation[] }, Error, ViewingReservation[]>({
    queryKey: ['viewings', status ?? 'all'],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: ViewingReservation[] }>(
        ENDPOINTS.my.viewingReservations,
        { params: { status: status || undefined, per_page: 50 } },
      );
      return data;
    },
    select: (p) => (Array.isArray(p?.data) ? p.data : []),
    enabled,
    staleTime: 60 * 1000,
  });
}

function useViewingAction(action: (id: string) => string) {
  const qc = useQueryClient();
  return useMutation<void, Error, string>({
    mutationFn: async (id) => {
      await apiClient.post(action(id));
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['viewings'] });
      qc.invalidateQueries({ queryKey: ['owner-stats'] });
    },
  });
}

export const useConfirmViewing = () => useViewingAction(ENDPOINTS.reservations.confirm);
export const useNoShowViewing = () => useViewingAction(ENDPOINTS.reservations.noShow);

/** PATCH /reservations/{id}/notes — attach a private note. */
export function useViewingNotes() {
  const qc = useQueryClient();
  return useMutation<void, Error, { id: string; notes: string }>({
    mutationFn: async ({ id, notes }) => {
      await apiClient.patch(ENDPOINTS.reservations.notes(id), { notes });
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['viewings'] }),
  });
}
