import { useMutation, useQueryClient } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type { AuthUser } from '@/types/user';

/** PUT /users/{id} — patch owner profile fields. */
export function useUpdateProfile(userId: string | undefined) {
  const qc = useQueryClient();
  return useMutation<AuthUser, Error, Partial<AuthUser>>({
    mutationFn: async (patch) => {
      if (!userId) throw new Error('Missing user id');
      const { data } = await apiClient.put<{ data?: AuthUser } | AuthUser>(
        ENDPOINTS.users.update(userId),
        patch,
      );
      return (data as { data?: AuthUser }).data ?? (data as AuthUser);
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['me'] }),
  });
}

/** Multipart avatar upload — POST /users/{id} with `_method=PUT`. */
export function useUploadAvatar(userId: string | undefined) {
  const qc = useQueryClient();
  return useMutation<AuthUser, Error, { uri: string; name: string; type: string }>({
    mutationFn: async (file) => {
      if (!userId) throw new Error('Missing user id');
      const form = new FormData();
      form.append('avatar', file as unknown as Blob);
      form.append('_method', 'PUT');
      const { data } = await apiClient.post<{ data?: AuthUser } | AuthUser>(
        ENDPOINTS.users.update(userId),
        form,
        { headers: { 'Content-Type': 'multipart/form-data' } },
      );
      return (data as { data?: AuthUser }).data ?? (data as AuthUser);
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['me'] }),
  });
}

/** POST /auth/change-password. */
export function useChangePassword() {
  return useMutation({
    mutationFn: async (input: {
      current_password: string;
      new_password: string;
      new_password_confirmation: string;
    }) => {
      const { data } = await apiClient.post(ENDPOINTS.auth.changePassword, input);
      return data;
    },
  });
}
