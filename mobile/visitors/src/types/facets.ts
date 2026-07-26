/**
 * Global catalogue facets — `GET /ads/facets` (server-cached 10 min).
 * Counts are computed on the whole public catalogue, not the current
 * search, so they only hint at availability (mirrors the web).
 */
export interface FacetsResponse {
  cities?: { name: string; count: number }[];
  types?: { name: string; count: number }[];
  bedrooms?: { value: number; count: number }[];
  price_range?: { min: number | null; max: number | null };
  surface_range?: { min: number | null; max: number | null };
  has_parking?: { with_parking: number; without_parking: number };
}
