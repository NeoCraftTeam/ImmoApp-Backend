/**
 * Saved search alert — `GET/POST/PUT /search-alerts`. Each alert is a
 * named filter set the user gets notified about when new ads match.
 */
export type AlertFrequency = 'immediate' | 'daily' | 'weekly';

export interface SearchAlert {
  id: string;
  label: string;
  is_active: boolean;
  frequency: AlertFrequency;
  filters: {
    city?: string | null;
    type?: string | null;
    transaction_type?: 'location' | 'vente' | null;
    min_price?: number | null;
    max_price?: number | null;
    bedrooms?: number | null;
    min_surface?: number | null;
    max_surface?: number | null;
    keywords?: string | null;
  };
  channels?: {
    email?: boolean;
    push?: boolean;
  };
  match_count?: number;
  created_at: string;
}
