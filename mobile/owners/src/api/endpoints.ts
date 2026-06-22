/**
 * Stable endpoint identifiers for the **owner / bailleur** app. Mirrors
 * the real Laravel routes in `routes/api/*.php` (verified against the
 * backend, not guessed). Centralised so a backend route rename only
 * touches this file.
 *
 * Base URL is `…/api/v1` (see `client.ts`), so paths here are relative
 * to that prefix.
 */
export const ENDPOINTS = {
  auth: {
    login: '/auth/login',
    /** Owners register through the AGENT flow (role = agent). */
    register: '/auth/registerAgent',
    logout: '/auth/logout',
    logoutAll: '/auth/logout-all',
    me: '/auth/me',
    changePassword: '/auth/change-password',
    forgotPassword: '/auth/forgot-password',
    resetPassword: '/auth/reset-password',
    verifyEmailOtp: '/auth/verify-email-otp',
    resendVerification: '/auth/resend-verification',
    checkEmail: '/auth/check-email',
  },
  users: {
    update: (id: string) => `/users/${encodeURIComponent(id)}`,
  },
  /** Reference data — cities, quarters, ad types, equipment attributes. */
  ref: {
    cities: '/cities',
    quarters: '/quarters',
    adTypes: '/ad-types',
    propertyAttributes: '/property-attributes',
    geoCity: '/geo/city',
    geoQuarter: '/geo/quarter',
  },
  /** Generic ad CRUD + status transitions (owner-scoped on the backend). */
  ads: {
    create: '/ads',
    detail: (slugOrId: string) => `/ads/${encodeURIComponent(slugOrId)}`,
    update: (id: string) => `/ads/${encodeURIComponent(id)}`,
    delete: (id: string) => `/ads/${encodeURIComponent(id)}`,
    publish: (id: string) => `/ads/${encodeURIComponent(id)}/publish`,
    setStatus: (id: string) => `/ads/${encodeURIComponent(id)}/set-status`,
    toggleVisibility: (id: string) =>
      `/ads/${encodeURIComponent(id)}/toggle-visibility`,
    setAvailability: (id: string) =>
      `/ads/${encodeURIComponent(id)}/set-availability`,
    autosave: (id: string) => `/ads/${encodeURIComponent(id)}/autosave`,
    editDraft: (id: string) => `/ads/${encodeURIComponent(id)}/edit-draft`,
    editDraftApply: (id: string) =>
      `/ads/${encodeURIComponent(id)}/edit-draft/apply`,
    rankEstimate: (id: string) =>
      `/ads/${encodeURIComponent(id)}/rank-estimate`,
    aiEnhanceDescription: '/ads/ai/enhance-description',
    aiEnhanceTitle: '/ads/ai/enhance-title',
  },
  /** Owner-scoped "my" surfaces. */
  my: {
    ads: '/my/ads',
    adsAnalytics: '/my/ads/analytics',
    adAnalytics: (id: string) => `/my/ads/${encodeURIComponent(id)}/analytics`,
    stats: '/my/stats',
    // Boost
    boostStatus: (id: string) => `/my/ads/${encodeURIComponent(id)}/boost-status`,
    boost: (id: string) => `/my/ads/${encodeURIComponent(id)}/boost`,
    boostRoi: (id: string) => `/my/ads/${encodeURIComponent(id)}/boost/roi`,
    duplicate: (id: string) => `/my/ads/${encodeURIComponent(id)}/duplicate`,
    // QR + placarde (print)
    adQr: (id: string) => `/my/ads/${encodeURIComponent(id)}/qr-code`,
    adQrImage: (id: string) => `/my/ads/${encodeURIComponent(id)}/qr-code/image`,
    adPlacarde: (id: string) => `/my/ads/${encodeURIComponent(id)}/placarde`,
    adPlacardePreview: (id: string) =>
      `/my/ads/${encodeURIComponent(id)}/placarde/preview`,
    // Profile marketing assets
    profileQr: '/my/profile/qr-code',
    profileQrImage: '/my/profile/qr-code/image',
    businessCard: '/my/profile/business-card',
    businessCardPreview: '/my/profile/business-card/preview',
    profilePlacarde: '/my/profile/placarde',
    // Tenants
    tenants: '/my/tenants',
    tenant: (id: string) => `/my/tenants/${encodeURIComponent(id)}`,
    // Lease contracts
    leaseContracts: '/my/lease-contracts',
    leaseContract: (id: string) =>
      `/my/lease-contracts/${encodeURIComponent(id)}`,
    leaseGenerate: (adId: string) =>
      `/my/lease-contracts/${encodeURIComponent(adId)}/generate`,
    leaseRenew: (id: string) =>
      `/my/lease-contracts/${encodeURIComponent(id)}/renew`,
    leaseTerminate: (id: string) =>
      `/my/lease-contracts/${encodeURIComponent(id)}/terminate`,
    // Expenses / P&L
    expenses: (adId: string) => `/my/ads/${encodeURIComponent(adId)}/expenses`,
    profitLoss: (adId: string) =>
      `/my/ads/${encodeURIComponent(adId)}/profit-loss`,
    expense: (id: string) => `/my/expenses/${encodeURIComponent(id)}`,
    // Documents
    documents: (adId: string) => `/my/ads/${encodeURIComponent(adId)}/documents`,
    document: (id: string) => `/my/documents/${encodeURIComponent(id)}`,
    // Viewing reservations (landlord inbox)
    viewingReservations: '/my/viewing-reservations',
    deleteAccount: '/my/account',
  },
  /** Per-ad viewing availability schedules. */
  availability: {
    list: (adId: string) => `/ads/${encodeURIComponent(adId)}/availability`,
    create: (adId: string) => `/ads/${encodeURIComponent(adId)}/availability`,
    update: (adId: string, scheduleId: string) =>
      `/ads/${encodeURIComponent(adId)}/availability/${encodeURIComponent(scheduleId)}`,
    delete: (adId: string, scheduleId: string) =>
      `/ads/${encodeURIComponent(adId)}/availability/${encodeURIComponent(scheduleId)}`,
  },
  /** Reservation actions (confirm / no-show / notes). */
  reservations: {
    confirm: (id: string) => `/reservations/${encodeURIComponent(id)}/confirm`,
    noShow: (id: string) => `/reservations/${encodeURIComponent(id)}/no-show`,
    notes: (id: string) => `/reservations/${encodeURIComponent(id)}/notes`,
  },
  /** Subscriptions + credits + payments. */
  subscriptions: {
    plans: '/subscriptions/plans',
    current: '/subscriptions/current',
    subscribe: '/subscriptions/subscribe',
    cancel: '/subscriptions/cancel',
    autoRenew: '/subscriptions/auto-renew',
    history: '/subscriptions/history',
  },
  boost: {
    packs: '/boost-packs',
  },
  credits: {
    balance: '/credits/balance',
    packages: '/credits/packages',
  },
  payments: {
    history: '/payments/history',
    publicStatus: (txRef: string) =>
      `/payments/${encodeURIComponent(txRef)}/public-status`,
  },
  reviews: {
    forAd: (adId: string) => `/ads/${encodeURIComponent(adId)}/reviews`,
    respond: (reviewId: string) =>
      `/reviews/${encodeURIComponent(reviewId)}/respond`,
  },
  notifications: {
    list: '/notifications',
    unreadCount: '/notifications/unread-count',
    markRead: (id: string) => `/notifications/${encodeURIComponent(id)}/read`,
    markAllRead: '/notifications/read-all',
    fcmToken: '/fcm/token',
  },
} as const;
