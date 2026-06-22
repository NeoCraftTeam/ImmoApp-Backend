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
      transactionType: 'location',
    });
    expect(params.min_price).toBe(100000);
    expect(params.max_price).toBe(500000);
    expect(params.transaction_type).toBe('location');
  });
});
