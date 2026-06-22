import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type {
  AppNotification,
  NotificationListResponse,
} from '@/types/notification';

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
    select: (payload) => (Array.isArray(payload?.data) ? payload.data : []),
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
