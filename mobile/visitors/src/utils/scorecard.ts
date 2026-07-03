import type {
  NeighborhoodScorecard,
  ScorecardCategory,
} from '@/types/scorecard';

/**
 * Le backend renvoie `categories` comme un dict keyé par catégorie
 * (`{transport: {score, label, nearest_poi…}, …}`) ; un tableau reste
 * accepté par tolérance. Tout ce qui manque retombe sur des valeurs
 * sûres — jamais de NaN à l'affichage.
 */
export function normalizeCategories(input: unknown): ScorecardCategory[] {
  if (Array.isArray(input)) {
    return (input as ScorecardCategory[]).map((cat) => ({
      ...cat,
      score: toFiniteScore(cat?.score),
    }));
  }
  if (input && typeof input === 'object') {
    return Object.entries(input as Record<string, Partial<ScorecardCategory>>).map(
      ([key, value]) => ({
        key,
        label: value?.label ?? humanizeKey(key),
        score: toFiniteScore(value?.score),
        poi_count: value?.poi_count,
        radius_m: value?.radius_m,
        nearest_poi: value?.nearest_poi ?? null,
      }),
    );
  }
  return [];
}

/**
 * Score global — champ backend `global_score` ; si absent/invalide,
 * moyenne des scores de catégories (0 si rien).
 */
export function overallScore(
  data: Partial<NeighborhoodScorecard> | undefined,
  categories: ScorecardCategory[],
): number {
  const global = Number(data?.global_score);
  if (Number.isFinite(global)) {
    return Math.round(global);
  }
  if (categories.length === 0) {
    return 0;
  }
  const mean =
    categories.reduce((acc, cat) => acc + toFiniteScore(cat.score), 0) /
    categories.length;
  return Math.round(mean);
}

function toFiniteScore(value: unknown): number {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? Math.max(0, Math.min(100, parsed)) : 0;
}

function humanizeKey(key: string): string {
  return key.charAt(0).toUpperCase() + key.slice(1).replace(/_/g, ' ');
}
