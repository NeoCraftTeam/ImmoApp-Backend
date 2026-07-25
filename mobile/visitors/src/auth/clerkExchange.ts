import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';

export type ClerkExchangeResult =
  | { kind: 'success'; accessToken: string }
  | { kind: 'otp_required'; emailHint: string | null }
  | { kind: 'email_verification_required'; email: string; message: string };

interface ClerkExchangeResponse {
  access_token?: string;
  token?: string;
  state?: string;
  email_hint?: string | null;
  email_verification_required?: boolean;
  email?: string;
  message?: string;
}

/**
 * Swap a Clerk session JWT for a Sanctum bearer token.
 * OAuth (Google/Facebook/GitHub) : compte créé immédiatement, sans OTP Laravel.
 */
export async function exchangeClerkForSanctum(clerkToken: string): Promise<ClerkExchangeResult> {
  const { data } = await apiClient.post<ClerkExchangeResponse>(
    ENDPOINTS.auth.clerkExchange,
    {
      login_context: 'client',
      registration_intent: 'customer',
    },
    {
      headers: {
        Authorization: `Bearer ${clerkToken}`,
      },
    },
  );

  if (data?.state === 'otp_required') {
    return {
      kind: 'otp_required',
      emailHint: typeof data.email_hint === 'string' ? data.email_hint : null,
    };
  }

  if (data?.email_verification_required) {
    return {
      kind: 'email_verification_required',
      email: data.email ?? '',
      message: data.message ?? 'Veuillez vérifier votre adresse email.',
    };
  }

  const accessToken = data?.access_token ?? data?.token;
  if (typeof accessToken !== 'string' || accessToken === '') {
    throw new Error('Réponse de connexion invalide.');
  }

  return { kind: 'success', accessToken };
}

export async function verifyClerkOtp(clerkToken: string, otp: string): Promise<string> {
  const { data } = await apiClient.post<ClerkExchangeResponse>(
    ENDPOINTS.auth.clerkVerifyOtp,
    {
      otp,
      login_context: 'client',
      registration_intent: 'customer',
    },
    {
      headers: {
        Authorization: `Bearer ${clerkToken}`,
      },
    },
  );

  const accessToken = data?.access_token ?? data?.token;
  if (typeof accessToken !== 'string' || accessToken === '') {
    throw new Error('Réponse de vérification invalide.');
  }

  return accessToken;
}
