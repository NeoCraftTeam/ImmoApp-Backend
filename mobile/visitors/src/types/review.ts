/**
 * Review (Avis) — mirror of the backend `ReviewResource` shape used by
 * `GET /ads/{ad}/reviews` and `POST /reviews`.
 */
export interface ReviewUser {
  id: string;
  firstname: string;
  avatar?: string | null;
}

export interface Review {
  id: string;
  rating: number;
  comment?: string | null;
  created_at: string;
  user: ReviewUser;
  response?: {
    body: string;
    created_at: string;
  } | null;
}

export interface ReviewListResponse {
  data: Review[];
  meta?: {
    average_rating?: number | null;
    reviews_count?: number;
  };
}
