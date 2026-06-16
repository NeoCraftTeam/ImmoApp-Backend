/**
 * Filter state used by the search tab. Shape matches the params the
 * backend `/ads` endpoint accepts so the hook can pass it straight
 * through with no shape transformation.
 *
 * `null` means "no constraint" — using `undefined` would conflict with
 * partial-object spreading patterns we use to merge UI state.
 */
export interface AdFilters {
  /** XOF lower bound, inclusive. */
  minPrice: number | null;
  /** XOF upper bound, inclusive. */
  maxPrice: number | null;
  /** m² lower bound, inclusive. */
  minSurface: number | null;
  /** m² upper bound, inclusive. */
  maxSurface: number | null;
  /** Rental vs sale. `null` matches both. */
  transactionType: 'location' | 'vente' | null;
  /** Free-text type query — matches `ad_type.name` via ILIKE. */
  type: string | null;
}

export const EMPTY_FILTERS: AdFilters = {
  minPrice: null,
  maxPrice: null,
  minSurface: null,
  maxSurface: null,
  transactionType: null,
  type: null,
};

/** Count the number of constraints active — used for the badge on the filter button. */
export function activeFilterCount(filters: AdFilters): number {
  return Object.values(filters).filter((v) => v !== null && v !== '').length;
}

/** Translate `AdFilters` into the query-string params the backend understands. */
export function filtersToParams(filters: AdFilters): Record<string, string | number> {
  const params: Record<string, string | number> = {};
  if (filters.minPrice != null) params.min_price = filters.minPrice;
  if (filters.maxPrice != null) params.max_price = filters.maxPrice;
  if (filters.minSurface != null) params.min_surface = filters.minSurface;
  if (filters.maxSurface != null) params.max_surface = filters.maxSurface;
  if (filters.transactionType) params.transaction_type = filters.transactionType;
  if (filters.type) params.type = filters.type;
  return params;
}
