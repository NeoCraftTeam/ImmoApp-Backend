import type { Ad } from './ad';

/**
 * Public agency profile — `GET /agencies/{id}`.
 */
export interface Agency {
  id: string;
  name: string;
  logo?: string | null;
  city?: string | null;
  description?: string | null;
  is_verified?: boolean;
  trust_score?: number | null;
  agents_count?: number;
  ads_count?: number;
  ads?: Ad[];
  rating?: number | null;
  reviews_count?: number;
  created_at?: string;
}
