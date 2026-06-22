/**
 * NeighborhoodScorecard normaliseur — le crash initial du détail
 * annonce venait d'un `data.categories` qui pouvait être un object
 * (dict) au lieu d'un array. On vérifie que les deux shapes mènent au
 * même tableau normalisé.
 */

interface ScorecardCategory {
  key: string;
  label: string;
  score: number;
  poi_count?: number;
  nearest?: unknown;
}

function humanizeKey(key: string): string {
  return key.charAt(0).toUpperCase() + key.slice(1).replace(/_/g, ' ');
}

function normalizeCategories(input: unknown): ScorecardCategory[] {
  if (Array.isArray(input)) return input as ScorecardCategory[];
  if (input && typeof input === 'object') {
    return Object.entries(input as Record<string, Partial<ScorecardCategory>>).map(
      ([key, value]) => ({
        key,
        label: value?.label ?? humanizeKey(key),
        score: value?.score ?? 0,
        poi_count: value?.poi_count,
        nearest: value?.nearest ?? null,
      }),
    );
  }
  return [];
}

describe('normalizeCategories', () => {
  it('passe les arrays telles quelles', () => {
    const arr = [{ key: 'transport', label: 'Transport', score: 75 }];
    expect(normalizeCategories(arr)).toEqual(arr);
  });

  it('convertit un object {key: {...}} en array', () => {
    const obj = {
      transport: { label: 'Transports', score: 75 },
      commerce: { score: 60 },
    };
    const result = normalizeCategories(obj);
    expect(result).toHaveLength(2);
    expect(result[0]?.key).toBe('transport');
    expect(result[1]?.label).toBe('Commerce'); // humanizé
  });

  it('renvoie [] pour null/undefined', () => {
    expect(normalizeCategories(null)).toEqual([]);
    expect(normalizeCategories(undefined)).toEqual([]);
  });

  it('renvoie [] pour primitives invalides', () => {
    expect(normalizeCategories(42)).toEqual([]);
    expect(normalizeCategories('foo')).toEqual([]);
  });

  it('survit à des values partielles ou null', () => {
    const obj = { gym: null, parc: { score: 80 } };
    const result = normalizeCategories(obj);
    expect(result).toHaveLength(2);
    expect(result.find((c) => c.key === 'gym')?.score).toBe(0); // fallback
    expect(result.find((c) => c.key === 'parc')?.score).toBe(80);
  });
});
