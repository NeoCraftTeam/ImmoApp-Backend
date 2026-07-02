import { searchParamsToState, EMPTY_FILTERS } from '@/types/filters';
import { parsedToSearchParams } from '@/utils/nlp-search';

describe('parsedToSearchParams', () => {
  it('mappe les filtres IA en params de navigation (parité web buildNlpParams)', () => {
    expect(
      parsedToSearchParams({
        q: 'lumineux',
        city_name: 'Douala',
        type_name: 'Appartement',
        transaction_type: 'location',
        bedrooms: 2,
        price_min: 50000,
        price_max: 150000,
        surface_min: 40,
        has_parking: true,
        furnished: true,
      }),
    ).toEqual({
      q: 'lumineux',
      city: 'Douala',
      type: 'Appartement',
      transaction_type: 'location',
      bedrooms: '2',
      price_min: '50000',
      price_max: '150000',
      surface_min: '40',
      parking: '1',
      furnished: '1',
    });
  });

  it('replie le quartier dans q quand il n\'y a pas de texte libre', () => {
    expect(parsedToSearchParams({ quarter_name: 'Bastos' })).toEqual({ q: 'Bastos' });
  });

  it('ignore les valeurs nulles et une transaction inconnue', () => {
    expect(
      parsedToSearchParams({ transaction_type: 'colocation', bedrooms: null }),
    ).toEqual({});
  });
});

describe('searchParamsToState', () => {
  it('hydrate query + filtres depuis les params de navigation', () => {
    const { query, filters } = searchParamsToState({
      q: 'lumineux',
      city: 'Douala',
      transaction_type: 'location',
      bedrooms: '2',
      price_max: '150000',
      parking: '1',
      furnished: '1',
    });
    expect(query).toBe('lumineux');
    expect(filters).toEqual({
      ...EMPTY_FILTERS,
      city: 'Douala',
      transactionType: 'location',
      bedrooms: 2,
      maxPrice: 150000,
      hasParking: true,
      attributes: ['furnished'],
    });
  });

  it('ignore les valeurs malformées', () => {
    const { query, filters } = searchParamsToState({
      bedrooms: 'abc',
      price_min: '-5',
      transaction_type: 'troc',
    });
    expect(query).toBe('');
    expect(filters).toEqual(EMPTY_FILTERS);
  });

  it('prend la première valeur des params en tableau', () => {
    const { filters } = searchParamsToState({ city: ['Douala', 'Yaoundé'] });
    expect(filters.city).toBe('Douala');
  });
});
