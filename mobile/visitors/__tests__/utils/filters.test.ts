import { activeFilterCount, EMPTY_FILTERS, filtersToParams } from '@/types/filters';

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
});
