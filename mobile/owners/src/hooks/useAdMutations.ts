import { useMutation, useQueryClient } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type { Ad, AdStatus } from '@/types/ad';

/** A picked image ready for multipart upload. */
export interface PickedImage {
  uri: string;
  name: string;
  type: string;
}

/**
 * The ad form payload — superset of the backend `AdRequest` fields. All
 * optional so the same shape serves both draft autosave (partial) and
 * full publish. `images` are new files to upload; existing images are
 * kept server-side unless explicitly replaced.
 */
export interface AdFormPayload {
  title?: string;
  description?: string;
  adresse?: string;
  price?: number | null;
  price_period?: 'mois' | 'jour' | null;
  transaction_type?: 'location' | 'vente' | null;
  surface_area?: number | null;
  bedrooms?: number | null;
  bathrooms?: number | null;
  has_parking?: boolean;
  type_id?: string;
  quarter_id?: string;
  latitude?: number | null;
  longitude?: number | null;
  attributes?: string[];
  deposit_amount?: string | null;
  minimum_lease_duration?: string | null;
  charges_forfaitaires?: boolean;
  charges_montant_forfait?: number | null;
  charges_eau?: number | null;
  charges_electricite?: number | null;
  charges_autres?: string | null;
  is_draft?: boolean;
  images?: PickedImage[];
}

/**
 * Serialise an `AdFormPayload` into multipart FormData. Laravel reads
 * scalars + array fields (`attributes[]`, `images[]`); booleans become
 * "1"/"0", null/undefined fields are skipped so a partial autosave only
 * touches the provided keys.
 */
function toFormData(payload: AdFormPayload): FormData {
  const form = new FormData();
  const append = (key: string, value: unknown) => {
    if (value === undefined || value === null) return;
    if (typeof value === 'boolean') {
      form.append(key, value ? '1' : '0');
      return;
    }
    form.append(key, String(value));
  };

  append('title', payload.title);
  append('description', payload.description);
  append('adresse', payload.adresse);
  append('price', payload.price);
  append('price_period', payload.price_period);
  append('transaction_type', payload.transaction_type);
  append('surface_area', payload.surface_area);
  append('bedrooms', payload.bedrooms);
  append('bathrooms', payload.bathrooms);
  append('has_parking', payload.has_parking);
  append('type_id', payload.type_id);
  append('quarter_id', payload.quarter_id);
  append('latitude', payload.latitude);
  append('longitude', payload.longitude);
  append('deposit_amount', payload.deposit_amount);
  append('minimum_lease_duration', payload.minimum_lease_duration);
  append('charges_forfaitaires', payload.charges_forfaitaires);
  append('charges_montant_forfait', payload.charges_montant_forfait);
  append('charges_eau', payload.charges_eau);
  append('charges_electricite', payload.charges_electricite);
  append('charges_autres', payload.charges_autres);
  append('is_draft', payload.is_draft);

  for (const slug of payload.attributes ?? []) {
    form.append('attributes[]', slug);
  }
  for (const img of payload.images ?? []) {
    form.append('images[]', img as unknown as Blob);
  }
  return form;
}

/** POST /ads — create a new ad (or draft when `is_draft` is true). */
export function useCreateAd() {
  const qc = useQueryClient();
  return useMutation<Ad, Error, AdFormPayload>({
    mutationFn: async (payload) => {
      const { data } = await apiClient.post<{ data: Ad } | Ad>(
        ENDPOINTS.ads.create,
        toFormData(payload),
        { headers: { 'Content-Type': 'multipart/form-data' } },
      );
      return (data as { data?: Ad }).data ?? (data as Ad);
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['my-ads'] });
      qc.invalidateQueries({ queryKey: ['owner-stats'] });
    },
  });
}

/** PUT /ads/{id} — update an existing ad (multipart, `_method=PUT`). */
export function useUpdateAd(id: string | undefined) {
  const qc = useQueryClient();
  return useMutation<Ad, Error, AdFormPayload>({
    mutationFn: async (payload) => {
      if (!id) throw new Error('Missing ad id');
      const form = toFormData(payload);
      form.append('_method', 'PUT');
      const { data } = await apiClient.post<{ data: Ad } | Ad>(
        ENDPOINTS.ads.update(id),
        form,
        { headers: { 'Content-Type': 'multipart/form-data' } },
      );
      return (data as { data?: Ad }).data ?? (data as Ad);
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['my-ads'] });
      qc.invalidateQueries({ queryKey: ['ad', id] });
    },
  });
}

/** PATCH /ads/{id}/autosave — progressive draft save (JSON, partial). */
export function useAutosaveAd(id: string | undefined) {
  return useMutation<void, Error, AdFormPayload>({
    mutationFn: async (payload) => {
      if (!id) return;
      // Autosave is text-only (no image upload); send JSON for speed.
      const { images: _images, ...rest } = payload;
      await apiClient.patch(ENDPOINTS.ads.autosave(id), rest);
    },
  });
}

/** POST /ads/{id}/publish — submit a draft for validation. */
export function usePublishAd() {
  const qc = useQueryClient();
  return useMutation<Ad, Error, string>({
    mutationFn: async (id) => {
      const { data } = await apiClient.post<{ data?: Ad }>(ENDPOINTS.ads.publish(id));
      return (data?.data ?? {}) as Ad;
    },
    onSuccess: (_d, id) => {
      qc.invalidateQueries({ queryKey: ['my-ads'] });
      qc.invalidateQueries({ queryKey: ['ad', id] });
      qc.invalidateQueries({ queryKey: ['owner-stats'] });
    },
  });
}

/** POST /ads/{id}/set-status — transition the ad status. */
export function useSetAdStatus() {
  const qc = useQueryClient();
  return useMutation<void, Error, { id: string; status: AdStatus }>({
    mutationFn: async ({ id, status }) => {
      await apiClient.post(ENDPOINTS.ads.setStatus(id), { status });
    },
    onSuccess: (_d, { id }) => {
      qc.invalidateQueries({ queryKey: ['my-ads'] });
      qc.invalidateQueries({ queryKey: ['ad', id] });
    },
  });
}

/** POST /ads/{id}/toggle-visibility — hide / show a published ad. */
export function useToggleVisibility() {
  const qc = useQueryClient();
  return useMutation<void, Error, string>({
    mutationFn: async (id) => {
      await apiClient.post(ENDPOINTS.ads.toggleVisibility(id));
    },
    onSuccess: (_d, id) => {
      qc.invalidateQueries({ queryKey: ['my-ads'] });
      qc.invalidateQueries({ queryKey: ['ad', id] });
    },
  });
}

/** DELETE /ads/{id}. */
export function useDeleteAd() {
  const qc = useQueryClient();
  return useMutation<void, Error, string>({
    mutationFn: async (id) => {
      await apiClient.delete(ENDPOINTS.ads.delete(id));
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['my-ads'] });
      qc.invalidateQueries({ queryKey: ['owner-stats'] });
    },
  });
}

/** POST /my/ads/{id}/duplicate — clone an ad as a new draft. */
export function useDuplicateAd() {
  const qc = useQueryClient();
  return useMutation<Ad, Error, string>({
    mutationFn: async (id) => {
      const { data } = await apiClient.post<{ data?: Ad } | Ad>(ENDPOINTS.my.duplicate(id));
      return (data as { data?: Ad }).data ?? (data as Ad);
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['my-ads'] });
    },
  });
}
