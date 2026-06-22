export interface CreditPackage {
  id: string;
  name: string;
  points: number;
  price: number;
  currency?: string;
  bonus_points?: number;
  is_popular?: boolean;
  description?: string | null;
}

export interface CreditBalance {
  point_balance: number;
  currency?: string;
}

export interface VerifyCreditPurchaseResponse {
  status: 'completed' | 'pending' | 'failed' | 'not_found';
  point_balance?: number;
  message?: string;
}
