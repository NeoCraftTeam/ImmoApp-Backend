/**
 * Mobile mirror of the backend `AdResource::toArray()` shape, with the
 * owner-specific fields the management screens read (status, draft
 * payload, boost, view counters). Extra keys are ignored at parse time;
 * removing/renaming a backend field requires syncing this file.
 */
export interface AdLocation {
  latitude: number;
  longitude: number;
}

export interface AdImage {
  id: number | string;
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
  city?: { id: string; name: string };
}

export interface AdType {
  id: string;
  name: string;
}

export interface AdOwnerUser {
  id: string;
  username?: string;
  firstname: string;
  lastname?: string;
  display_name?: string;
  avatar?: string | null;
  agency_name?: string | null;
  phone_number?: string | null;
  phone_is_whatsapp?: boolean;
  email?: string;
  is_verified?: boolean;
  trust_score?: number;
  trust_tier?: string;
}

/** Ad status — mirrors backend `AdStatus` enum. */
export type AdStatus =
  | 'draft'
  | 'pending'
  | 'available'
  | 'reserved'
  | 'rent'
  | 'sold'
  | 'declined';

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
  status: AdStatus;
  status_label?: string;
  is_visible?: boolean;
  is_currently_available?: boolean;
  available_from?: string | null;
  available_to?: string | null;
  transaction_type?: 'location' | 'vente' | null;
  attributes?: string[];
  // Charges / conditions
  deposit_amount?: string | null;
  minimum_lease_duration?: string | null;
  charges_forfaitaires?: boolean | null;
  charges_montant_forfait?: number | null;
  charges_eau?: number | null;
  charges_electricite?: number | null;
  charges_autres?: string | null;
  // Marketing / ranking
  is_verified?: boolean;
  is_boosted?: boolean;
  boost_expires_at?: string | null;
  boost_score?: number;
  sponsorship_tier?: string;
  keyscore?: number | null;
  // Counters
  rating?: number | null;
  reviews_count?: number;
  view_count?: number;
  views_count_today?: number;
  views_count_week?: number;
  // Media + relations
  images: AdImage[];
  total_images?: number;
  has_3d_tour?: boolean;
  quarter?: AdQuarter;
  type?: AdType;
  user?: AdOwnerUser;
  created_at?: string;
  updated_at?: string;
  /** Pending edit-draft for a published ad (live-edit workflow). */
  draft_payload?: Record<string, unknown> | null;
  canonical_url?: string;
}

export interface PaginatedAds {
  data: Ad[];
  meta?: {
    current_page?: number;
    last_page?: number;
    per_page?: number;
    total?: number;
    next_cursor?: string | null;
  };
  links?: { next?: string | null };
}
