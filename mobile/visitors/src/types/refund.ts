/**
 * Refund request — `GET /payments/refunds` + `POST /payments/{id}/refund-request`.
 */
export type RefundStatus =
  | 'pending'
  | 'processing'
  | 'completed'
  | 'failed'
  | 'rejected'
  | string;

export interface Refund {
  id: string;
  payment_id: string;
  amount: number;
  currency: string;
  reason: string;
  status: RefundStatus;
  decided_at?: string | null;
  decision_note?: string | null;
  created_at: string;
}

export interface RefundListResponse {
  data: Refund[];
  meta?: { current_page?: number; last_page?: number };
}
