/**
 * Saved search alert — `GET/POST/PUT /search-alerts`. Chaque alerte est un
 * jeu de critères nommé pour lequel l'utilisateur est notifié quand une
 * annonce correspond. Le backend (StoreSearchAlertRequest + modèle
 * SearchAlert) utilise des colonnes PLATES, pas un objet `filters`.
 */
export type AlertFrequency = 'immediate' | 'daily' | 'weekly';

export interface SearchAlert {
  id: string;
  label?: string | null;
  is_active?: boolean;
  frequency?: AlertFrequency;
  city_id?: string | null;
  city_name?: string | null;
  type_id?: string | null;
  type_name?: string | null;
  quarter_id?: string | null;
  price_min?: number | null;
  price_max?: number | null;
  bedrooms_min?: number | null;
  surface_min?: number | null;
  has_parking?: boolean | null;
  query?: string | null;
  notify_email?: boolean;
  notify_push?: boolean;
  match_count?: number;
  created_at?: string;
}
