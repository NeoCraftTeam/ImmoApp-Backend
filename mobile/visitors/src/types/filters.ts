/**
 * Filter state used by the search tab. Shape mirrors the params the
 * `/ads/search` endpoint accepts (validated by `AdRequest`).
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
  /** Free-text type query — matches `ad_type.name`. */
  type: string | null;
  /** Minimum bedrooms (1 = "1+"). `null` = any. */
  bedrooms: number | null;
  /** Minimum bathrooms (1 = "1+"). `null` = any. */
  bathrooms: number | null;
  /** Rental billing period. Only meaningful when renting. */
  pricePeriod: 'mois' | 'jour' | null;
  /** Only ads with parking. */
  hasParking: boolean;
  /** Only ads with a 3D tour. */
  has3dTour: boolean;
  /** Only verified ads. */
  isVerified: boolean;
}

/** Sort options exposed in the UI, mapped to the backend `sort`/`order`. */
export type AdSort =
  | 'recent'
  | 'price_asc'
  | 'price_desc'
  | 'surface_desc'
  | 'rating_desc';

export const DEFAULT_SORT: AdSort = 'recent';

export const SORT_OPTIONS: { value: AdSort; label: string }[] = [
  { value: 'recent', label: 'Plus récentes' },
  { value: 'price_asc', label: 'Prix croissant' },
  { value: 'price_desc', label: 'Prix décroissant' },
  { value: 'surface_desc', label: 'Plus grande surface' },
  { value: 'rating_desc', label: 'Mieux notées' },
];

/** Translate a UI sort into backend `sort` + `order` query params. */
export function sortToParams(sort: AdSort): { sort: string; order: 'asc' | 'desc' } {
  switch (sort) {
    case 'price_asc':
      return { sort: 'price', order: 'asc' };
    case 'price_desc':
      return { sort: 'price', order: 'desc' };
    case 'surface_desc':
      return { sort: 'surface_area', order: 'desc' };
    case 'rating_desc':
      return { sort: 'reviews_avg_rating', order: 'desc' };
    case 'recent':
    default:
      return { sort: 'created_at', order: 'desc' };
  }
}

export const EMPTY_FILTERS: AdFilters = {
  minPrice: null,
  maxPrice: null,
  minSurface: null,
  maxSurface: null,
  transactionType: null,
  type: null,
  bedrooms: null,
  bathrooms: null,
  pricePeriod: null,
  hasParking: false,
  has3dTour: false,
  isVerified: false,
};

/** Count the number of constraints active — used for the badge on the filter button. */
export function activeFilterCount(filters: AdFilters): number {
  return Object.values(filters).filter(
    (v) => v !== null && v !== '' && v !== false,
  ).length;
}

/**
 * Translate `AdFilters` into the query-string params the `/ads/search`
 * endpoint understands (validated by `AdRequest`).
 */
export function filtersToParams(filters: AdFilters): Record<string, string | number> {
  const params: Record<string, string | number> = {};
  if (filters.minPrice != null) params.price_min = filters.minPrice;
  if (filters.maxPrice != null) params.price_max = filters.maxPrice;
  if (filters.minSurface != null) params.surface_min = filters.minSurface;
  if (filters.maxSurface != null) params.surface_max = filters.maxSurface;
  if (filters.transactionType) params.transaction_type = filters.transactionType;
  if (filters.type) params.type = filters.type;
  if (filters.bedrooms != null) params.bedrooms = filters.bedrooms;
  if (filters.bathrooms != null) params.bathrooms = filters.bathrooms;
  if (filters.pricePeriod) params.price_period = filters.pricePeriod;
  if (filters.hasParking) params.has_parking = 1;
  if (filters.has3dTour) params.has_3d_tour = 1;
  if (filters.isVerified) params.is_verified = 1;
  return params;
}
