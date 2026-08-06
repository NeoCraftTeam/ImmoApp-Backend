import { RESOLVED_BASE_URL } from '@/api/client';

function apiOrigin(): string {
  return RESOLVED_BASE_URL.replace(/\/api\/v1\/?$/i, '');
}

/**
 * Normalise avatar / cover URLs from the API for React Native `<Image>`.
 * Handles absolute https, protocol-relative `//`, and legacy relative paths
 * (`avatars/...`, `/storage/...`) that the web resolves via the document origin.
 */
export function resolveMediaUrl(raw: string | null | undefined): string | null {
  if (raw == null) {
    return null;
  }

  const trimmed = raw.trim();
  if (trimmed === '' || trimmed === '0') {
    return null;
  }

  if (trimmed.startsWith('//')) {
    return `https:${trimmed}`;
  }

  if (/^https?:\/\//i.test(trimmed)) {
    return trimmed;
  }

  const origin = apiOrigin();
  if (trimmed.startsWith('/')) {
    return `${origin}${trimmed}`;
  }

  if (trimmed.startsWith('storage/')) {
    return `${origin}/${trimmed}`;
  }

  return `${origin}/storage/${trimmed}`;
}
