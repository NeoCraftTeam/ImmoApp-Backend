import { useQuery } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type { ProService } from '@/types/proservice';

/** Forme d'un pack de boost renvoyé par /boost-packs. */
interface BoostPack {
  id: string;
  name: string;
  slug: string;
  description?: string | null;
  reach_description?: string | null;
  duration_days?: number;
  boost_score?: number;
  price_credits?: number;
  is_popular?: boolean;
}

interface BoostPacksResponse {
  data?: BoostPack[];
}

/**
 * GET /boost-packs — catalogue des packs de boost (payés en crédits, appliqués
 * à une annonce). Mappé vers `ProService` pour l'écran « Services Pro ».
 */
export function useProServices(enabled = true) {
  return useQuery<BoostPacksResponse, Error, ProService[]>({
    queryKey: ['pro-services'],
    queryFn: async () => {
      const { data } = await apiClient.get<BoostPacksResponse>(
        ENDPOINTS.proServices.list,
      );
      return data;
    },
    select: (p) =>
      (Array.isArray(p?.data) ? p.data : []).map((pack) => ({
        id: pack.id,
        slug: pack.slug,
        name: pack.name,
        description: pack.reach_description ?? pack.description ?? undefined,
        price_credits: pack.price_credits,
        duration_days: pack.duration_days,
        highlighted: pack.is_popular,
      })),
    enabled,
    staleTime: 5 * 60 * 1000,
  });
}
