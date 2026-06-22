/**
 * Neighborhood scorecard — POI-based liveability score per category,
 * computed server-side from OpenStreetMap + OpenRouteService walking
 * distances. Backend endpoint: `GET /ads/{ad}/neighborhood-scorecard`.
 */
export interface ScorecardCategory {
  key: string;
  label: string;
  score: number;
  poi_count?: number;
  nearest?: {
    name: string;
    distance_m?: number;
    walking_minutes?: number;
  } | null;
}

export interface NeighborhoodScorecard {
  overall_score: number;
  categories: ScorecardCategory[];
  unavailable?: boolean;
  computed_at?: string | null;
}
