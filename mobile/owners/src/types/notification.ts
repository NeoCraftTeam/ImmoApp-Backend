export type NotificationType = string;

export interface NotificationItem {
  id: string;
  type: NotificationType;
  title?: string;
  body?: string;
  data?: Record<string, unknown>;
  read_at?: string | null;
  created_at: string;
  url?: string | null;
}
