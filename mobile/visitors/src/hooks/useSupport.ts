import { useMutation } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';

export function useContactSupport() {
  return useMutation({
    mutationFn: async (input: {
      name: string;
      email: string;
      subject: string;
      message: string;
    }) => {
      const { data } = await apiClient.post(ENDPOINTS.support.contact, input);
      return data;
    },
  });
}
