import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type {
  AppNotification,
  NotificationListResponse,
} from '@/types/notification';

/**
 * Le backend renvoie les notifications Laravel brutes : titre/message/type
 * vivent dans le champ JSON `data` (pas au niveau racine), d'où des
 * titres vides si on lit directement `notification.title`. On aplatit
 * ici, et on dérive un deep-link vers l'annonce concernée quand présent.
 */
function normalizeNotification(raw: Record<string, unknown>): AppNotification {
  const data = (raw.data ?? {}) as Record<string, unknown>;
  const adId = (data.ad_id ?? data.adId) as string | undefined;
  const slug = (data.ad_slug ?? data.slug) as string | undefined;
  const href =
    (data.href as string | undefined) ??
    (slug || adId ? `/ads/${encodeURIComponent(slug ?? adId ?? '')}` : null);
  return {
    id: String(raw.id ?? ''),
    type: (data.type ?? raw.type ?? 'system') as AppNotification['type'],
    title: (data.title as string) ?? 'Notification',
    body: (data.message ?? data.body) as string | undefined,
    data,
    read_at: (raw.read_at as string | null) ?? null,
    created_at: (raw.created_at as string) ?? new Date(0).toISOString(),
    href,
  };
}

export function useNotifications(unreadOnly = false) {
  return useQuery<NotificationListResponse, Error, AppNotification[]>({
    queryKey: ['notifications', unreadOnly],
    queryFn: async () => {
      const { data } = await apiClient.get<NotificationListResponse>(
        ENDPOINTS.notifications.list,
        { params: { per_page: 30, unread_only: unreadOnly } },
      );
      return data;
    },
    select: (payload) =>
      Array.isArray(payload?.data)
        ? payload.data.map((n) => normalizeNotification(n as unknown as Record<string, unknown>))
        : [],
    staleTime: 30 * 1000,
    refetchInterval: 60 * 1000,
  });
}

export function useUnreadNotificationCount() {
  return useQuery<{ count: number } | { data: { count: number } }, Error, number>({
    queryKey: ['notifications-unread-count'],
    queryFn: async () => {
      const { data } = await apiClient.get(ENDPOINTS.notifications.unreadCount);
      return data;
    },
    select: (payload) => {
      if (payload && typeof payload === 'object' && 'count' in payload) {
        return (payload as { count: number }).count ?? 0;
      }
      if (
        payload &&
        typeof payload === 'object' &&
        'data' in payload &&
        typeof (payload as { data?: { count?: number } }).data?.count === 'number'
      ) {
        return (payload as { data: { count: number } }).data.count;
      }
      return 0;
    },
    staleTime: 30 * 1000,
    refetchInterval: 60 * 1000,
  });
}

export function useMarkNotificationRead() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (id: string) => {
      await apiClient.post(ENDPOINTS.notifications.markRead(id));
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['notifications'] });
      qc.invalidateQueries({ queryKey: ['notifications-unread-count'] });
    },
  });
}

export function useMarkAllNotificationsRead() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async () => {
      await apiClient.post(ENDPOINTS.notifications.markAllRead);
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['notifications'] });
      qc.invalidateQueries({ queryKey: ['notifications-unread-count'] });
    },
  });
}

export function useDeleteNotification() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (id: string) => {
      await apiClient.delete(ENDPOINTS.notifications.delete(id));
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['notifications'] });
      qc.invalidateQueries({ queryKey: ['notifications-unread-count'] });
    },
  });
}
