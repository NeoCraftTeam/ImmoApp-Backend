export type RefundStatus =
  | 'requested'
  | 'reviewing'
  | 'approved'
  | 'rejected'
  | 'processed';

export interface RefundRequest {
  id: string;
  amount: number;
  currency?: string;
  status: RefundStatus;
  status_label?: string;
  reason?: string;
  payment_id?: string | null;
  created_at: string;
  processed_at?: string | null;
}
