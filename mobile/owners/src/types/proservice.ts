export interface ProService {
  id: string;
  slug: string;
  name: string;
  description?: string;
  price?: number;
  price_credits?: number;
  duration_days?: number;
  icon?: string | null;
  highlighted?: boolean;
}

export interface TrustScore {
  score: number;
  level?: string;
  factors?: { key: string; label: string; value: number; max: number; status?: string }[];
  recommendations?: string[];
}
