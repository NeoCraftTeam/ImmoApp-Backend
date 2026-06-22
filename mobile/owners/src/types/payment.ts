/**
 * Historique de paiement owner — entrée crédits, abonnements, boosts.
 * Aligné sur la réponse de `/payments/history`.
 */
export type PaymentStatus =
  | 'pending'
  | 'succeeded'
  | 'failed'
  | 'refunded'
  | 'cancelled';

export interface PaymentEntry {
  id: string;
  tx_ref: string;
  amount: number;
  currency?: string;
  status: PaymentStatus;
  status_label?: string;
  description?: string | null;
  method?: string | null;
  created_at: string;
  reference_type?: string | null;
  reference_id?: string | null;
}

export interface PaymentHistory {
  data: PaymentEntry[];
  meta?: {
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
  };
}
