import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type { NotificationItem } from '@/types/notification';

interface NotificationsResponse {
  data?: NotificationItem[];
}

export function useNotifications(enabled = true) {
  return useQuery<NotificationsResponse, Error, NotificationItem[]>({
    queryKey: ['notifications'],
    queryFn: async () => {
      const { data } = await apiClient.get<NotificationsResponse>(
        ENDPOINTS.notifications.list,
        { params: { per_page: 50 } },
      );
      return data;
    },
    select: (p) => (Array.isArray(p?.data) ? p.data : []),
    enabled,
    staleTime: 30 * 1000,
  });
}

export function useUnreadNotificationCount(enabled = true) {
  return useQuery<{ count?: number; unread_count?: number }, Error, number>({
    queryKey: ['notifications-unread'],
    queryFn: async () => {
      const { data } = await apiClient.get<{ count?: number; unread_count?: number }>(
        ENDPOINTS.notifications.unreadCount,
      );
      return data;
    },
    select: (p) => p?.count ?? p?.unread_count ?? 0,
    enabled,
    refetchInterval: 60 * 1000,
    staleTime: 30 * 1000,
  });
}

export function useMarkNotificationRead() {
  const qc = useQueryClient();
  return useMutation<void, Error, string>({
    mutationFn: async (id) => {
      await apiClient.post(ENDPOINTS.notifications.markRead(id));
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['notifications'] });
      qc.invalidateQueries({ queryKey: ['notifications-unread'] });
    },
  });
}

export function useMarkAllNotificationsRead() {
  const qc = useQueryClient();
  return useMutation<void, Error, void>({
    mutationFn: async () => {
      await apiClient.post(ENDPOINTS.notifications.markAllRead);
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['notifications'] });
      qc.invalidateQueries({ queryKey: ['notifications-unread'] });
    },
  });
}
