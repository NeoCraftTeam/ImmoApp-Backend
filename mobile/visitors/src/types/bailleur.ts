import type { Ad } from './ad';

/**
 * Public landlord profile — returned by `GET /users/{username}/public-profile`.
 * Surfaces the trust signals (rating, trust score, verified status) needed
 * to build user confidence before they engage with one of the ads.
 */
export interface BailleurProfile {
  id: string;
  firstname: string;
  lastname?: string;
  username: string;
  avatar?: string | null;
  bio?: string | null;
  city?: string | null;
  is_verified?: boolean;
  trust_score?: number | null;
  trust_tier?: string | null;
  rating?: number | null;
  reviews_count?: number;
  ads_count?: number;
  ads?: Ad[];
  follower_count?: number;
  created_at?: string;
}

export interface BailleurFollowState {
  is_following: boolean;
  follower_count?: number;
}
