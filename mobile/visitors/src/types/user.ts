/**
 * Authenticated user — returned by `GET /me`. The shape mirrors the
 * backend `UserResource` but trimmed to fields the visitor app reads.
 */
export interface AuthUser {
  id: string;
  firstname: string;
  lastname?: string;
  email: string;
  phone_number?: string | null;
  avatar?: string | null;
  /** UserResource expose `city_id` (uuid) + `city_name` — pas `city`. */
  city_id?: string | null;
  city_name?: string | null;
  username?: string | null;
  point_balance?: number;
  is_verified?: boolean;
  trust_score?: number | null;
  trust_tier?: string | null;
  notification_email?: boolean;
  notification_push?: boolean;
  /** ISO timestamp — last time the user opened the home tab while authenticated.
   *  Drives the "Bon retour parmi nous" greeting (>24 h since last visit). */
  last_home_visit_at?: string | null;
  created_at?: string;
}
