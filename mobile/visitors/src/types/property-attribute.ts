/**
 * Property attributes (equipment) — `GET /property-attributes`
 * (CDN-cached 30 minutes). The endpoint returns the attributes twice:
 * `data` is a map keyed by slug (used to label `Ad.attributes` on the
 * ad detail screen) and `grouped` nests them under their category
 * (used by the search filter accordions).
 */
export interface PropertyAttributeMeta {
  /** Slug — also the value sent to `/ads/search` as `attributes[]`. */
  value: string;
  label: string;
  /** Web icon name hint; the mobile UI maps slugs to lucide icons instead. */
  icon?: string | null;
  admin_icon?: string | null;
  category?: {
    id: number | null;
    name: string | null;
    slug: string | null;
  } | null;
}

export interface PropertyAttributeCategory {
  id: number;
  name: string;
  slug: string;
  attributes: PropertyAttributeMeta[];
}

export interface PropertyAttributesResponse {
  success?: boolean;
  data?: Record<string, PropertyAttributeMeta>;
  grouped?: PropertyAttributeCategory[];
}
