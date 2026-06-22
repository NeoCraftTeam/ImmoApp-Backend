/**
 * Payment transaction — `GET /payments/history`.
 */
export interface PaymentTransaction {
  id: string;
  amount: number;
  currency: string;
  status: 'pending' | 'success' | 'failed' | 'refunded' | string;
  provider?: string | null;
  reference?: string | null;
  description?: string | null;
  created_at: string;
}

export interface PaymentsResponse {
  data: PaymentTransaction[];
  meta?: {
    current_page?: number;
    last_page?: number;
  };
}
