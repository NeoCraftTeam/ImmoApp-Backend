import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type { TrustScore } from '@/types/proservice';

interface TrustScoreEnvelope {
  data?: TrustScore | null;
  consent_required?: boolean;
  consent_declined?: boolean;
}

export interface TrustScoreState {
  score: TrustScore | null;
  /** L'utilisateur n'a jamais répondu à la demande de consentement RGPD. */
  consentRequired: boolean;
  /** L'utilisateur a explicitement refusé — le score est désactivé. */
  consentDeclined: boolean;
}

/**
 * GET /my/trust-score — détail des facteurs de confiance + recos.
 * Le backend gate le calcul derrière un consentement RGPD explicite :
 * la réponse porte `consent_required` / `consent_declined` quand le
 * score n'est pas disponible.
 */
export function useTrustScore(enabled = true) {
  return useQuery<TrustScoreEnvelope | TrustScore, Error, TrustScoreState>({
    queryKey: ['trust-score'],
    queryFn: async () => {
      const { data } = await apiClient.get<TrustScoreEnvelope | TrustScore>(
        ENDPOINTS.trust.score,
      );
      return data;
    },
    select: (p) => {
      const envelope = (p ?? {}) as TrustScoreEnvelope;
      const inner = envelope.data ?? null;
      const looksLikeScore =
        inner === null && p != null && typeof (p as TrustScore).score === 'number';
      return {
        score: inner ?? (looksLikeScore ? (p as TrustScore) : null),
        consentRequired: Boolean(envelope.consent_required),
        consentDeclined: Boolean(envelope.consent_declined),
      };
    },
    enabled,
    staleTime: 5 * 60 * 1000,
  });
}

/** POST /my/trust-score/consent — activer/désactiver le score (RGPD). */
export function useTrustScoreConsent() {
  const qc = useQueryClient();
  return useMutation<void, Error, boolean>({
    mutationFn: async (consent) => {
      await apiClient.post(ENDPOINTS.trust.consent, { consent });
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['trust-score'] });
    },
  });
}
