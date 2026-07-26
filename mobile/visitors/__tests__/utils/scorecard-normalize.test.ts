/**
 * NeighborhoodScorecard — normalisation du contrat backend réel
 * (`global_score`, `categories` en dict avec `nearest_poi`). Le crash
 * « NaN » venait d'un champ `overall_score` inexistant côté API.
 */
import { normalizeCategories, overallScore } from '@/utils/scorecard';

describe('normalizeCategories', () => {
  it('normalise le dict backend keyé par catégorie', () => {
    const categories = normalizeCategories({
      transport: {
        score: 40,
        label: 'Transport',
        poi_count: 3,
        nearest_poi: { name: 'Arrêt Ndokoti', distance_m: 350, mode: 'walking' },
      },
      sante: { score: 10, label: 'Santé' },
    });
    expect(categories).toHaveLength(2);
    expect(categories[0]).toMatchObject({
      key: 'transport',
      label: 'Transport',
      score: 40,
      nearest_poi: { name: 'Arrêt Ndokoti', distance_m: 350 },
    });
    expect(categories[1]?.nearest_poi).toBeNull();
  });

  it('accepte aussi un tableau et borne les scores invalides', () => {
    const categories = normalizeCategories([
      { key: 'securite', label: 'Sécurité', score: 250 },
      { key: 'loisirs', label: 'Loisirs', score: Number.NaN },
    ]);
    expect(categories[0]?.score).toBe(100);
    expect(categories[1]?.score).toBe(0);
  });

  it('retourne un tableau vide pour une entrée inattendue', () => {
    expect(normalizeCategories(null)).toEqual([]);
    expect(normalizeCategories('oops')).toEqual([]);
  });
});

describe('overallScore', () => {
  it('utilise global_score quand présent', () => {
    expect(overallScore({ global_score: 62 }, [])).toBe(62);
  });

  it('ne produit jamais NaN — moyenne des catégories en secours', () => {
    const categories = normalizeCategories({
      a: { score: 20 },
      b: { score: 40 },
    });
    expect(overallScore({} as never, categories)).toBe(30);
    expect(overallScore(undefined, [])).toBe(0);
  });
});
