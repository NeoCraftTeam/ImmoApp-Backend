/**
 * Stable endpoint identifiers used by the TanStack Query keys + Axios
 * calls. Keeping them centralised means a backend route rename only
 * touches this file (and not 12 scattered string literals).
 */
export const ENDPOINTS = {
  auth: {
    login: '/auth/login',
    register: '/auth/register',
    logout: '/auth/logout',
    me: '/me',
  },
  ads: {
    feed: '/ads/feed',
    list: '/ads',
    detail: (slugOrId: string) => `/ads/${encodeURIComponent(slugOrId)}`,
    keyscore: (id: string) => `/ads/${encodeURIComponent(id)}/keyscore`,
    scorecard: (id: string) => `/ads/${encodeURIComponent(id)}/neighborhood-scorecard`,
  },
  geo: {
    directions: '/directions',
  },
} as const;
