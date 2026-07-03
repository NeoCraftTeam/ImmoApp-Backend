/**
 * Neighborhood scorecard — POI-based liveability score per category,
 * computed server-side from OpenStreetMap + OpenRouteService walking
 * distances. Backend endpoint: `GET /ads/{ad}/neighborhood-scorecard`
 * (NeighborhoodScorecardService) : `global_score`, `status`
 * (`unavailable` quand Overpass a échoué) et `categories` en dict
 * keyé par catégorie avec `nearest_poi`.
 */
export interface ScorecardNearestPoi {
  osm_id?: string;
  name: string;
  distance_m?: number | null;
  /** Mode de calcul de la distance (ex. `walking`, `haversine`). */
  mode?: string | null;
}

export interface ScorecardCategory {
  key: string;
  label: string;
  score: number;
  poi_count?: number;
  radius_m?: number;
  nearest_poi?: ScorecardNearestPoi | null;
}

export interface NeighborhoodScorecard {
  global_score: number;
  status?: string;
  categories: ScorecardCategory[] | Record<string, Partial<ScorecardCategory>>;
  computed_at?: string | null;
  ors_used?: boolean;
}
