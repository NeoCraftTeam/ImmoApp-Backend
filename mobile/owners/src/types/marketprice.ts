export interface MarketPriceEstimate {
  estimated_price: number;
  currency?: string;
  range?: { low: number; high: number };
  comparable_count?: number;
  city?: string | null;
  quarter?: string | null;
  is_unreliable?: boolean;
  confidence?: number;
}
