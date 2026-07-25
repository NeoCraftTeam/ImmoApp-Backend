/**
 * Historique de paiement owner — entrée crédits, abonnements, boosts.
 * Aligné sur la réponse de `/payments/history`.
 */
export type PaymentStatus =
  | 'pending'
  | 'succeeded'
  | 'success'
  | 'failed'
  | 'refunded'
  | 'cancelled'
  | 'requires_action';

export interface PaymentEntry {
  id: string;
  tx_ref: string;
  amount: number;
  currency?: string;
  status: PaymentStatus;
  status_label?: string;
  description?: string | null;
  method?: string | null;
  payment_method_label?: string | null;
  payment_method_detail?: string | null;
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

/** Méthode de paiement disponible (config admin). */
export interface PaymentMethod {
  id: string;
  code: string;
  label: string;
  gateway: 'kpay' | 'stripe' | string;
  channel?: 'mobile_money' | 'card' | 'wallet' | string;
  icon?: string | null;
  requires_phone?: boolean;
  is_default?: boolean;
}

export type PaymentPurpose =
  | 'credit'
  | 'subscription'
  | 'boost'
  | 'pro_service'
  | 'unlock';

export interface InitiatePaymentInput {
  amount: number;
  type: PaymentPurpose;
  payment_method: string;
  phone_number?: string;
  promo_code?: string;
  agency_id?: string;
  plan_id?: string;
  billing_period?: 'monthly' | 'yearly';
  payment_method_id?: string;
  save_payment_method?: boolean;
  callback_url?: string;
  /** ID interne du package crédit / boost / service pro selon `type`. */
  reference_id?: string;
}

export interface InitiatePaymentResponse {
  reference: string;
  tx_ref: string;
  payment_link?: string;
  payment_url?: string;
  gateway: string;
  status: PaymentStatus;
  stripe_flow?: 'redirect' | 'embedded';
  amount?: number;
  currency?: string;
}

/** Réponse publique de polling. */
export interface PublicPaymentStatus {
  status: PaymentStatus | string;
  amount?: number;
  currency?: string;
  message?: string;
  reference?: string;
}

/** Carte Stripe sauvegardée. */
export interface SavedCard {
  id: string;
  brand: string;
  last4: string;
  exp_month: number;
  exp_year: number;
  is_default?: boolean;
}
