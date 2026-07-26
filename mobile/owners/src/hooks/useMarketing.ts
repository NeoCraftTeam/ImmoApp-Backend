import { useQuery } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type { QrMeta } from '@/types/owner';

/**
 * GET /my/ads/{id}/qr-code — QR data-URI + the canonical ad URL it
 * encodes. The data-URI renders directly in an <Image> so we never need
 * a client-side QR library.
 */
export function useAdQr(adId: string | undefined) {
  return useQuery<{ data: QrMeta }, Error, QrMeta>({
    queryKey: ['ad-qr', adId],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: QrMeta }>(ENDPOINTS.my.adQr(adId as string));
      return data;
    },
    select: (p) => p.data,
    enabled: !!adId,
    staleTime: 30 * 60 * 1000,
  });
}

/** GET /my/profile/qr-code — owner profile QR data-URI + profile URL. */
export function useProfileQr(enabled = true) {
  return useQuery<{ data: QrMeta }, Error, QrMeta>({
    queryKey: ['profile-qr'],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: QrMeta }>(ENDPOINTS.my.profileQr);
      return data;
    },
    select: (p) => p.data,
    enabled,
    staleTime: 30 * 60 * 1000,
  });
}
