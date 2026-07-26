/**
 * Authenticated owner — returned by `GET /auth/me`. Mirrors the backend
 * `UserResource`, trimmed to fields the owner app reads.
 */
export interface AuthUser {
  id: string;
  firstname: string;
  lastname?: string;
  email: string;
  phone_number?: string | null;
  phone_is_whatsapp?: boolean;
  avatar?: string | null;
  /** UserResource expose city_id + city_name (pas de champ `city`). */
  city_id?: string | null;
  city_name?: string | null;
  username?: string | null;
  bio?: string | null;
  role?: string;
  agency_name?: string | null;
  agency_id?: string | null;
  point_balance?: number;
  is_verified?: boolean;
  trust_score?: number | null;
  trust_tier?: string | null;
  trust_tier_label?: string | null;
  notification_email?: boolean;
  notification_push?: boolean;
  created_at?: string;
}
