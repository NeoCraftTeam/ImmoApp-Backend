/**
 * App notification — `GET /notifications`. The `type` discriminator
 * drives the icon + colour in the list view.
 */
export type NotificationType =
  | 'payment'
  | 'ad'
  | 'review'
  | 'search_alert'
  | 'message'
  | 'system';

export interface AppNotification {
  id: string;
  type: NotificationType | string;
  title: string;
  body?: string;
  data?: Record<string, unknown>;
  read_at?: string | null;
  created_at: string;
  /** Deep-link path the row should navigate to when tapped. */
  href?: string | null;
}

export interface NotificationListResponse {
  data: AppNotification[];
  meta?: {
    current_page?: number;
    last_page?: number;
    total?: number;
  };
}
