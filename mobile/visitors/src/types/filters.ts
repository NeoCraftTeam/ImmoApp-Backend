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
  /** Selected equipment slugs (AND semantics on the backend). */
  attributes: string[];
  /** City name picked from the autocomplete — the backend matches on name. */
  city: string | null;
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
  attributes: [],
  city: null,
};

/** Count the number of constraints active — used for the badge on the filter button. */
export function activeFilterCount(filters: AdFilters): number {
  const { attributes, ...scalars } = filters;
  const scalarCount = Object.values(scalars).filter(
    (v) => v !== null && v !== '' && v !== false,
  ).length;
  return scalarCount + attributes.length;
}

/**
 * Translate `AdFilters` into the query-string params the `/ads/search`
 * endpoint understands (validated by `AdRequest`).
 */
export function filtersToParams(
  filters: AdFilters,
): Record<string, string | number | string[]> {
  const params: Record<string, string | number | string[]> = {};
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
  if (filters.attributes.length > 0) params.attributes = filters.attributes;
  if (filters.city) params.city = filters.city;
  return params;
}

/**
 * Hydrate query + filters from navigation params (Home hero search,
 * AI search). Expo-router params are strings (or string arrays); every
 * unknown or malformed value is simply ignored. `furnished=1` maps to
 * the `furnished` amenity, like the web URL sync.
 */
export function searchParamsToState(
  params: Record<string, string | string[] | undefined>,
): { query: string; filters: AdFilters } {
  const str = (v: string | string[] | undefined): string | null => {
    const raw = Array.isArray(v) ? v[0] : v;
    return raw != null && raw.trim() !== '' ? raw.trim() : null;
  };
  const num = (v: string | string[] | undefined): number | null => {
    const raw = str(v);
    if (raw === null) return null;
    const parsed = Number(raw);
    return Number.isFinite(parsed) && parsed >= 0 ? parsed : null;
  };

  const transaction = str(params.transaction_type);
  const filters: AdFilters = {
    ...EMPTY_FILTERS,
    city: str(params.city),
    type: str(params.type),
    bedrooms: num(params.bedrooms),
    minPrice: num(params.price_min),
    maxPrice: num(params.price_max),
    minSurface: num(params.surface_min),
    transactionType:
      transaction === 'location' || transaction === 'vente' ? transaction : null,
    hasParking: str(params.parking) === '1',
    attributes: str(params.furnished) === '1' ? ['furnished'] : [],
  };
  return { query: str(params.q) ?? '', filters };
}

/** One removable chip in the active-filters row above the results. */
export interface FilterChipDescriptor {
  key:
    | 'query'
    | 'city'
    | 'type'
    | 'bedrooms'
    | 'transactionType'
    | 'pricePeriod'
    | 'hasParking'
    | 'has3dTour'
    | 'isVerified';
  label: string;
}

/**
 * Chips shown above the results, mirroring the web's "Active filter
 * chips" row: same filter set (no chip for price/surface/bathrooms/
 * amenities — those still count in `activeFilterCount`), plus the
 * mobile-only `isVerified` toggle.
 */
export function activeFilterChips(
  filters: AdFilters,
  query: string,
): FilterChipDescriptor[] {
  const chips: FilterChipDescriptor[] = [];
  const trimmed = query.trim();
  if (trimmed !== '') chips.push({ key: 'query', label: `« ${trimmed} »` });
  if (filters.city) chips.push({ key: 'city', label: `Ville : ${filters.city}` });
  if (filters.type) chips.push({ key: 'type', label: `Type : ${filters.type}` });
  if (filters.bedrooms != null) {
    chips.push({ key: 'bedrooms', label: `${filters.bedrooms}+ chambres` });
  }
  if (filters.transactionType) {
    chips.push({
      key: 'transactionType',
      label: filters.transactionType === 'location' ? 'Location' : 'Vente',
    });
  }
  if (filters.pricePeriod) {
    chips.push({
      key: 'pricePeriod',
      label: filters.pricePeriod === 'mois' ? 'Par mois' : 'Par jour',
    });
  }
  if (filters.hasParking) chips.push({ key: 'hasParking', label: 'Parking' });
  if (filters.has3dTour) chips.push({ key: 'has3dTour', label: 'Visite 3D' });
  if (filters.isVerified) chips.push({ key: 'isVerified', label: 'Vérifiée' });
  return chips;
}

/** Reset the filter behind one chip; `query` chips are handled by the screen. */
export function removeFilterChip(
  filters: AdFilters,
  key: FilterChipDescriptor['key'],
): AdFilters {
  switch (key) {
    case 'city':
      return { ...filters, city: null };
    case 'type':
      return { ...filters, type: null };
    case 'bedrooms':
      return { ...filters, bedrooms: null };
    case 'transactionType':
      return { ...filters, transactionType: null };
    case 'pricePeriod':
      return { ...filters, pricePeriod: null };
    case 'hasParking':
      return { ...filters, hasParking: false };
    case 'has3dTour':
      return { ...filters, has3dTour: false };
    case 'isVerified':
      return { ...filters, isVerified: false };
    case 'query':
    default:
      return filters;
  }
}
