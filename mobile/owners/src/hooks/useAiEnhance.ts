import { useMutation } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';

/** POST /ads/ai/enhance-title — IA suggestion titre. */
export function useEnhanceTitle() {
  return useMutation<string, Error, { title: string; description?: string }>({
    mutationFn: async (payload) => {
      const { data } = await apiClient.post<{ title: string }>(
        ENDPOINTS.ads.aiEnhanceTitle,
        payload,
      );
      return data.title;
    },
  });
}

/** POST /ads/ai/enhance-description — IA suggestion description. */
export function useEnhanceDescription() {
  return useMutation<
    string,
    Error,
    { title?: string; description: string; attributes?: string[] }
  >({
    mutationFn: async (payload) => {
      const { data } = await apiClient.post<{ description: string }>(
        ENDPOINTS.ads.aiEnhanceDescription,
        payload,
      );
      return data.description;
    },
  });
}
