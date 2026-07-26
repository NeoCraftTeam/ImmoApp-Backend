/**
 * Structured filters returned by `POST /search/parse` (natural-language
 * search). The backend runs the query through an LLM (with a regex
 * fallback) and returns nullable filter candidates — never a list of
 * ads. Mirror of the web `ParsedSearchParams`.
 */
export interface ParsedSearchParams {
  original_query?: string | null;
  q?: string | null;
  city_id?: string | number | null;
  city_name?: string | null;
  type_id?: string | number | null;
  type_name?: string | null;
  quarter_name?: string | null;
  transaction_type?: string | null;
  bedrooms?: number | null;
  price_min?: number | null;
  price_max?: number | null;
  surface_min?: number | null;
  has_parking?: boolean | null;
  furnished?: boolean | null;
}
