import { useMutation } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type { ParsedSearchParams } from '@/types/nlp-search';

/**
 * Natural-language search — `POST /search/parse` turns a free-form
 * sentence into structured filters (same contract as the web hero's
 * "Recherche IA" tab). Heavily rate-limited server-side; callers
 * should fall back to a plain keyword search on error.
 */
export function useNaturalSearchParse() {
  return useMutation<ParsedSearchParams, Error, string>({
    mutationFn: async (query) => {
      const { data } = await apiClient.post<ParsedSearchParams>(
        ENDPOINTS.searchParse,
        { q: query, display_currency: 'XOF' },
      );
      return data;
    },
  });
}
