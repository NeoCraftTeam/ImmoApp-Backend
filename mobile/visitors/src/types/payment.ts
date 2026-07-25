/**
 * Payment transaction — `GET /payments/history`.
 */
export interface PaymentTransaction {
  id: string;
  amount: number;
  currency: string;
  status: 'pending' | 'success' | 'failed' | 'refunded' | 'cancelled' | string;
  status_label?: string;
  provider?: string | null;
  payment_method_detail?: string | null;
  reference?: string | null;
  description?: string | null;
  type?: string;
  created_at: string;
}

export interface PaymentsResponse {
  data: PaymentTransaction[];
  meta?: {
    current_page?: number;
    last_page?: number;
  };
}
