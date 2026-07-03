/**
 * Stable endpoint identifiers used by the TanStack Query keys + Axios
 * calls. Keeping them centralised means a backend route rename only
 * touches this file (and not 12 scattered string literals).
 */
export const ENDPOINTS = {
  auth: {
    login: '/auth/login',
    register: '/auth/registerCustomer',
    logout: '/auth/logout',
    me: '/auth/me',
    changePassword: '/auth/update-password',
    forgotPassword: '/auth/forgot-password',
    resetPassword: '/auth/reset-password',
    verifyEmailOtp: '/auth/verify-email-otp',
    resendVerification: '/auth/resend-verification',
    updateUnverifiedEmail: '/auth/update-unverified-email',
    trackHomeVisit: '/auth/track-home-visit',
    checkEmail: '/auth/check-email',
    oauthRedirect: (provider: string) => `/auth/oauth/${provider}/redirect`,
    oauthExchange: '/auth/oauth/exchange-token',
  },
  users: {
    update: (id: string) => `/users/${encodeURIComponent(id)}`,
    publicProfile: (username: string) =>
      `/users/${encodeURIComponent(username)}/public-profile`,
  },
  bailleurs: {
    follow: (username: string) =>
      `/bailleurs/${encodeURIComponent(username)}/follow`,
  },
  agencies: {
    detail: (id: string) => `/agencies/${encodeURIComponent(id)}`,
  },
  ads: {
    feed: '/ads/feed',
    list: '/ads',
    search: '/ads/search',
    facets: '/ads/facets',
    nearby: '/ads/nearby',
    recommendations: '/recommendations',
    types: '/ad-types',
    detail: (slugOrId: string) => `/ads/${encodeURIComponent(slugOrId)}`,
    similar: (id: string) => `/ads/${encodeURIComponent(id)}/similar`,
    reviews: (id: string) => `/ads/${encodeURIComponent(id)}/reviews`,
    keyscore: (id: string) => `/ads/${encodeURIComponent(id)}/keyscore`,
    scorecard: (id: string) =>
      `/ads/${encodeURIComponent(id)}/neighborhood-scorecard`,
  },
  my: {
    favorites: '/my/favorites',
    unlockedAds: '/my/unlocked-ads',
    deleteAccount: '/my/account',
    reservations: '/my/reservations',
  },
  viewings: {
    slots: (adId: string) => `/ads/${encodeURIComponent(adId)}/slots`,
    reserve: (adId: string) => `/ads/${encodeURIComponent(adId)}/reservations`,
    cancel: (id: string) =>
      `/reservations/${encodeURIComponent(id)}`,
  },
  reviews: {
    create: '/reviews',
  },
  conversations: {
    list: '/conversations',
    detail: (uuid: string) => `/conversations/${encodeURIComponent(uuid)}`,
    messages: (uuid: string) =>
      `/conversations/${encodeURIComponent(uuid)}/messages`,
    attachments: (uuid: string) =>
      `/conversations/${encodeURIComponent(uuid)}/attachments`,
    typing: (uuid: string) =>
      `/conversations/${encodeURIComponent(uuid)}/typing`,
    read: (uuid: string) =>
      `/conversations/${encodeURIComponent(uuid)}/read`,
    archive: (uuid: string) =>
      `/conversations/${encodeURIComponent(uuid)}/archive`,
    unarchive: (uuid: string) =>
      `/conversations/${encodeURIComponent(uuid)}/unarchive`,
    unreadCount: '/conversations/unread-count',
    create: '/conversations',
  },
  messages: {
    delete: (uuid: string) => `/messages/${encodeURIComponent(uuid)}`,
    reactions: (uuid: string) =>
      `/messages/${encodeURIComponent(uuid)}/reactions`,
  },
  notifications: {
    list: '/notifications',
    unreadCount: '/notifications/unread-count',
    markRead: (id: string) =>
      `/notifications/${encodeURIComponent(id)}/read`,
    markAllRead: '/notifications/read-all',
    delete: (id: string) => `/notifications/${encodeURIComponent(id)}`,
    fcmToken: '/fcm/token',
  },
  searchAlerts: {
    list: '/search-alerts',
    create: '/search-alerts',
    previewCount: '/search-alerts/preview-count',
    update: (id: string) => `/search-alerts/${encodeURIComponent(id)}`,
    delete: (id: string) => `/search-alerts/${encodeURIComponent(id)}`,
  },
  payments: {
    history: '/payments/history',
    methods: '/payments/methods',
    publicStatus: (txRef: string) =>
      `/payments/${encodeURIComponent(txRef)}/public-status`,
    refunds: '/payments/refunds',
    refundRequest: (paymentId: string) =>
      `/payments/${encodeURIComponent(paymentId)}/refund-request`,
  },
  credits: {
    balance: '/credits/balance',
  },
  disputes: {
    list: '/disputes',
    detail: (id: string) => `/disputes/${encodeURIComponent(id)}`,
    create: '/disputes',
    evidence: (id: string) => `/disputes/${encodeURIComponent(id)}/evidences`,
    messages: (id: string) => `/disputes/${encodeURIComponent(id)}/messages`,
  },
  surveys: {
    publicList: '/public/surveys',
    publicShow: (slug: string) => `/public/surveys/${encodeURIComponent(slug)}`,
    publicSubmit: (slug: string) =>
      `/public/surveys/${encodeURIComponent(slug)}/respond`,
    hasAnswered: (id: string) =>
      `/surveys/${encodeURIComponent(id)}/has-answered`,
    submit: (id: string) =>
      `/surveys/${encodeURIComponent(id)}/responses`,
  },
  support: {
    contact: '/support/contact',
  },
  priceIndex: '/price-index',
  propertyAttributes: '/property-attributes',
  searchParse: '/search/parse',
  geo: {
    directions: '/directions',
  },
} as const;
