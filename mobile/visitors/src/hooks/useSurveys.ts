import { useMutation, useQuery } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type { Survey, SurveyAnswer, SurveyListResponse } from '@/types/survey';

export function useSurveys() {
  return useQuery<SurveyListResponse, Error, Survey[]>({
    queryKey: ['public-surveys'],
    queryFn: async () => {
      const { data } = await apiClient.get<SurveyListResponse>(
        ENDPOINTS.surveys.publicList,
      );
      return data;
    },
    select: (payload) => (Array.isArray(payload?.data) ? payload.data : []),
    staleTime: 5 * 60 * 1000,
  });
}

export function useSurvey(slug: string | undefined) {
  return useQuery<{ data: Survey } | Survey, Error, Survey>({
    queryKey: ['public-survey', slug],
    queryFn: async () => {
      if (!slug) throw new Error('Missing survey slug');
      const { data } = await apiClient.get(ENDPOINTS.surveys.publicShow(slug));
      return data;
    },
    select: (payload) =>
      ('data' in (payload as { data?: unknown })
        ? (payload as { data: Survey }).data
        : (payload as Survey)),
    enabled: Boolean(slug),
    staleTime: 5 * 60 * 1000,
  });
}

export function useSubmitSurvey(slug: string | undefined) {
  return useMutation({
    mutationFn: async (input: { answers: SurveyAnswer[]; anonymous?: boolean }) => {
      if (!slug) throw new Error('Missing survey slug');
      const { data } = await apiClient.post(
        ENDPOINTS.surveys.publicSubmit(slug),
        input,
      );
      return data;
    },
  });
}
