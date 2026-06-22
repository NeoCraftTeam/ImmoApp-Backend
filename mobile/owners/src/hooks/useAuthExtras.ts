import { useMutation, useQueryClient } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';

/**
 * Password reset flow + email OTP verification — each wraps a single
 * `apiClient.post`. Errors propagate up so screens render the API's
 * `message` via `extractApiErrorMessage`.
 */
export function useForgotPassword() {
  return useMutation({
    mutationFn: async (email: string) => {
      const { data } = await apiClient.post(ENDPOINTS.auth.forgotPassword, { email });
      return data;
    },
  });
}

export function useResetPassword() {
  return useMutation({
    mutationFn: async (input: {
      email: string;
      token: string;
      password: string;
      password_confirmation: string;
    }) => {
      const { data } = await apiClient.post(ENDPOINTS.auth.resetPassword, input);
      return data;
    },
  });
}

export function useVerifyEmailOtp() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (input: { email: string; otp: string }) => {
      const { data } = await apiClient.post<{ token?: string; access_token?: string }>(
        ENDPOINTS.auth.verifyEmailOtp,
        { email: input.email, otp_code: input.otp },
      );
      return data;
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['me'] });
    },
  });
}

export function useResendVerification() {
  return useMutation({
    mutationFn: async (email: string) => {
      const { data } = await apiClient.post(ENDPOINTS.auth.resendVerification, { email });
      return data;
    },
  });
}
