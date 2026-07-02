import type { ParsedSearchParams } from '@/types/nlp-search';

/**
 * Convert the `POST /search/parse` response into the string params the
 * search tab understands (expo-router only carries strings). Mobile
 * counterpart of the web `buildNlpParams`: same keys, plus the quarter
 * name folded into `q` when the AI found a quarter but no free text
 * (the search endpoint matches quarter names through `q`).
 */
export function parsedToSearchParams(
  parsed: ParsedSearchParams,
): Record<string, string> {
  const params: Record<string, string> = {};
  const q = parsed.q ?? (parsed.quarter_name || null);
  if (q) params.q = q;
  if (parsed.city_name) params.city = parsed.city_name;
  if (parsed.type_name) params.type = parsed.type_name;
  if (parsed.transaction_type === 'location' || parsed.transaction_type === 'vente') {
    params.transaction_type = parsed.transaction_type;
  }
  if (parsed.bedrooms != null) params.bedrooms = String(parsed.bedrooms);
  if (parsed.price_min != null) params.price_min = String(parsed.price_min);
  if (parsed.price_max != null) params.price_max = String(parsed.price_max);
  if (parsed.surface_min != null) params.surface_min = String(parsed.surface_min);
  if (parsed.has_parking) params.parking = '1';
  if (parsed.furnished) params.furnished = '1';
  return params;
}
