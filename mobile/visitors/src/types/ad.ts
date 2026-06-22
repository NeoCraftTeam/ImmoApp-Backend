/**
 * Mobile mirror of the backend `AdResource::toArray()` shape. Kept
 * intentionally narrow — only fields the visitor app reads. Adding a
 * field on the backend doesn't break the mobile app (extra keys are
 * ignored at JSON parse time); removing a field will, so sync this
 * file when removing/renaming fields in `AdResource`.
 */
export interface AdLocation {
  latitude: number;
  longitude: number;
}

export interface AdImage {
  id: number;
  url: string;
  thumb?: string;
  large?: string;
  mime_type: string;
  is_primary: boolean;
}

export interface AdQuarter {
  id?: string;
  name: string;
  city_name?: string;
}

export interface AdType {
  id: string;
  name: string;
}

export interface AdUser {
  id: string;
  username?: string;
  firstname: string;
  lastname?: string;
  avatar?: string | null;
  phone_number?: string | null;
  phone_is_whatsapp?: boolean;
  email?: string;
  is_verified?: boolean;
  trust_tier?: string;
  trust_score?: number;
}

export interface Ad {
  id: string;
  slug?: string;
  title: string;
  description: string;
  adresse: string;
  price: number | null;
  price_period?: 'mois' | 'jour' | null;
  surface_area?: number | null;
  bedrooms?: number | null;
  bathrooms?: number | null;
  has_parking?: boolean;
  location?: AdLocation | null;
  status: string;
  is_currently_available: boolean;
  is_unlocked: boolean;
  is_favorited?: boolean;
  is_verified?: boolean;
  is_boosted?: boolean;
  is_subscription_sponsored?: boolean;
  sponsorship_tier?: string;
  rating?: number | null;
  reviews_count: number;
  view_count?: number;
  keyscore?: number | null;
  images: AdImage[];
  total_images: number;
  has_3d_tour: boolean;
  transaction_type?: 'location' | 'vente' | null;
  quarter?: AdQuarter;
  type?: AdType;
  user?: AdUser;
  created_at?: string;
  available_from?: string | null;
  /** Equipment slugs (resolved to labels via /property-attributes). */
  attributes?: string[];
  /** Optional pre-formatted distance from the user, set by the search/feed endpoint. */
  distance?: number | null;
}

export interface AdFeedResponse {
  data: Ad[];
  meta?: {
    next_cursor?: string | null;
    per_page?: number;
  };
  total_approximate?: number;
}
