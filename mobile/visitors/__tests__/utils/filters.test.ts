import {
  activeFilterCount,
  EMPTY_FILTERS,
  filtersToParams,
  sortToParams,
} from '@/types/filters';

describe('filters', () => {
  it('activeFilterCount = 0 pour les filtres vides', () => {
    expect(activeFilterCount(EMPTY_FILTERS)).toBe(0);
  });

  it('compte chaque filtre actif', () => {
    expect(
      activeFilterCount({
        ...EMPTY_FILTERS,
        minPrice: 50000,
        transactionType: 'location',
      }),
    ).toBe(2);
  });

  it('filtersToParams omet les valeurs nulles', () => {
    const params = filtersToParams(EMPTY_FILTERS);
    expect(Object.keys(params)).toHaveLength(0);
  });

  it('filtersToParams convertit les valeurs présentes', () => {
    const params = filtersToParams({
      ...EMPTY_FILTERS,
      minPrice: 100000,
      maxPrice: 500000,
      minSurface: 20,
      maxSurface: 120,
      transactionType: 'location',
    });
    expect(params.price_min).toBe(100000);
    expect(params.price_max).toBe(500000);
    expect(params.surface_min).toBe(20);
    expect(params.surface_max).toBe(120);
    expect(params.transaction_type).toBe('location');
  });

  it('filtersToParams mappe les filtres avancés', () => {
    const params = filtersToParams({
      ...EMPTY_FILTERS,
      bedrooms: 2,
      bathrooms: 1,
      pricePeriod: 'mois',
      hasParking: true,
      has3dTour: true,
      isVerified: true,
    });
    expect(params.bedrooms).toBe(2);
    expect(params.bathrooms).toBe(1);
    expect(params.price_period).toBe('mois');
    expect(params.has_parking).toBe(1);
    expect(params.has_3d_tour).toBe(1);
    expect(params.is_verified).toBe(1);
  });

  it('activeFilterCount ignore les booléens à false', () => {
    expect(
      activeFilterCount({ ...EMPTY_FILTERS, hasParking: true, bedrooms: 3 }),
    ).toBe(2);
  });

  it('activeFilterCount compte chaque équipement sélectionné', () => {
    expect(
      activeFilterCount({ ...EMPTY_FILTERS, attributes: ['wifi', 'piscine'] }),
    ).toBe(2);
    expect(activeFilterCount({ ...EMPTY_FILTERS, attributes: [] })).toBe(0);
  });

  it('filtersToParams envoie les équipements comme tableau attributes', () => {
    const params = filtersToParams({
      ...EMPTY_FILTERS,
      attributes: ['wifi', 'piscine'],
    });
    expect(params.attributes).toEqual(['wifi', 'piscine']);
  });

  it('filtersToParams omet le tableau attributes vide', () => {
    expect(filtersToParams(EMPTY_FILTERS)).not.toHaveProperty('attributes');
  });

  it('sortToParams traduit le tri UI en sort/order backend', () => {
    expect(sortToParams('recent')).toEqual({ sort: 'created_at', order: 'desc' });
    expect(sortToParams('price_asc')).toEqual({ sort: 'price', order: 'asc' });
    expect(sortToParams('price_desc')).toEqual({ sort: 'price', order: 'desc' });
    expect(sortToParams('surface_desc')).toEqual({ sort: 'surface_area', order: 'desc' });
    expect(sortToParams('rating_desc')).toEqual({ sort: 'reviews_avg_rating', order: 'desc' });
  });
});
