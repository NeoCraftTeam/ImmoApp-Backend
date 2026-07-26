import { useMutation, useQueryClient } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type { AuthUser } from '@/types/user';

export function useUpdateProfile(userId: string | undefined) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (patch: Partial<AuthUser>) => {
      if (!userId) throw new Error('Missing user id');
      const { data } = await apiClient.put<AuthUser>(
        ENDPOINTS.users.update(userId),
        patch,
      );
      return data;
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['me'] });
    },
  });
}

export function useUploadAvatar(userId: string | undefined) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (file: { uri: string; name: string; type: string }) => {
      if (!userId) throw new Error('Missing user id');
      const form = new FormData();
      form.append('avatar', file as unknown as Blob);
      // Laravel ignores the method override unless _method is included
      // alongside POST — PUT with multipart breaks on some PHP setups.
      form.append('_method', 'PUT');
      const { data } = await apiClient.post<AuthUser>(
        ENDPOINTS.users.update(userId),
        form,
        { headers: { 'Content-Type': 'multipart/form-data' } },
      );
      return data;
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['me'] });
    },
  });
}

export function useChangePassword() {
  return useMutation({
    mutationFn: async (input: {
      current_password: string;
      new_password: string;
      new_password_confirmation: string;
    }) => {
      const { data } = await apiClient.post(
        ENDPOINTS.auth.changePassword,
        input,
      );
      return data;
    },
  });
}

/** Phrase de confirmation exacte exigée par le backend (GdprController). */
export const DELETE_ACCOUNT_CONFIRMATION = 'SUPPRIMER MON COMPTE';

/**
 * DELETE /my/account — suppression définitive du compte. Le backend exige
 * une phrase de confirmation exacte (`confirmation`) — sans elle la requête
 * échoue en 422. On l'envoie donc toujours.
 */
export function useDeleteAccount() {
  return useMutation({
    mutationFn: async () => {
      await apiClient.delete(ENDPOINTS.my.deleteAccount, {
        data: { confirmation: DELETE_ACCOUNT_CONFIRMATION },
      });
    },
  });
}
