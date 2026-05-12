---
sidebar_position: 3
title: "API Services"
---

# API Services

## Overview

All API communication is centralised in `src/services/` (22 service files). Every service is a plain object exporting async functions. They all use the shared Axios instance from `src/lib/api.ts`.

**Base URL**: `NEXT_PUBLIC_API_URL` (e.g. `https://api.keyhome.cm/api/v1`)

---

## Shared Axios Instance (`src/lib/api.ts`)

```typescript
import axios from 'axios';
import { getInMemoryToken } from '@/lib/auth-session';

const api = axios.create({
 baseURL: process.env.NEXT_PUBLIC_API_URL,
 timeout: 30_000, // 30-second global timeout
 withCredentials: true, // Include cookies (CSRF, session)
});

// Request interceptor — inject Bearer token
api.interceptors.request.use((config) => {
 const token = getInMemoryToken();
 if (token) config.headers['Authorization'] = `Bearer ${token}`;
 return config;
});

// Response interceptor — handle 401, 419 (CSRF), 429 (rate-limit)
api.interceptors.response.use(
 (response) => response,
 async (error) => {
 if (error.response?.status === 419) {
 // CSRF failure — refetch CSRF token and retry once
 await fetchCsrfToken();
 return api.request(error.config);
 }
 if (error.response?.status === 401) {
 window.dispatchEvent(new Event('auth:expired'));
 }
 if (error.response?.status === 429) {
 window.dispatchEvent(new Event('rate:limited'));
 }
 return Promise.reject(error);
 }
);
```

---

## Service Files Reference

### `auth.service.ts`

Authentication endpoints (login, register, OTP, Clerk OAuth, password reset).

| Function | Method | Endpoint | Description |
|---|---|---|---|
| `login(payload)` | POST | `/auth/login` | Email/password login |
| `registerCustomer(payload)` | POST | `/auth/registerCustomer` | Customer registration |
| `registerAgent(payload)` | POST | `/auth/registerAgent` | Owner/agent registration |
| `me()` | GET | `/auth/me` | Get current authenticated user |
| `clerkExchange(intent?)` | POST | `/auth/clerk/exchange` | Exchange Clerk JWT for Sanctum token |
| `verifyClerkOtp(otp)` | POST | `/auth/clerk/verify-otp` | Verify Clerk OTP |
| `completeClerkProfile(data)` | POST | `/auth/clerk/complete-profile` | Complete OAuth profile |
| `logout()` | POST | `/auth/logout` | Invalidate session |
| `forgotPassword(email)` | POST | `/auth/forgot-password` | Request password reset |
| `resetPassword(data)` | POST | `/auth/reset-password` | Reset with token |
| `updatePassword(data)` | POST | `/auth/update-password` | Change password (authenticated) |
| `resendVerification(email?)` | POST | `/auth/email/resend` | Resend OTP email |
| `verifyEmailOtp(email, otp)` | POST | `/auth/verify-email-otp` | Verify email OTP |
| `completeOnboarding()` | POST | `/auth/onboarding-complete` | Mark onboarding done |
| `trackHomeVisit()` | POST | `/auth/track-home-visit` | Track home tab visit |
| `updatePreferences(prefs)` | PATCH | `/auth/preferences` | Update user preferences |
| `getOAuthRedirectUrl(provider, params)` | GET | `/auth/oauth/:provider/redirect` | Get OAuth redirect URL |
| `checkEmail(email)` | POST | `/auth/check-email` | Check email availability |

---

### `ads.service.ts`

Property listing operations.

| Function | Method | Endpoint | Description |
|---|---|---|---|
| `list(params)` | GET | `/ads` | Paginated ad list |
| `show(id)` | GET | `/ads/:id` | Single ad (UUID or slug) |
| `search(params)` | GET | `/ads/search` | Full-text + facet search |
| `nearby(params)` | GET | `/ads/nearby` | Geolocation-based nearby ads |
| `nearbyForUser(userId, params)` | GET | `/ads/:userId/nearby` | Nearby ads for a user's location |
| `autocomplete(field, q)` | GET | `/ads/autocomplete` | Field value autocomplete |
| `facets()` | GET | `/ads/facets` | Search filter facets |
| `create(formData)` | POST | `/ads` | Create listing (multipart, 120s timeout) |
| `update(id, formData)` | POST | `/ads/:id` | Update listing (`_method=PUT`, 120s timeout) |
| `destroy(id)` | DELETE | `/ads/:id` | Delete listing |
| `toggleVisibility(id)` | POST | `/ads/:id/toggle-visibility` | Toggle public visibility |
| `enhanceDescription(data)` | POST | `/ads/ai/enhance-description` | AI description enhancement |
| `setStatus(id, status)` | POST | `/ads/:id/set-status` | Change ad status |
| `getStats()` | GET | `/stats/landing` | Platform stats (for landing page) |
| `recentlyViewed()` | GET | `/my/recently-viewed` | User's recently viewed ads |
| `trackView(id)` | POST | `/ads/:id/view` | Track ad view (fire & forget) |
| `getTour(id)` | GET | `/ads/:id/tour` | Get 3D tour config |
| `uploadTourScenes(id, formData)` | POST | `/ads/:id/tour/scenes` | Upload panoramas (600s timeout) |
| `updateHotspots(id, sceneId, hotspots)` | PATCH | `/ads/:id/tour/scenes/:sceneId/hotspots` | Update navigation hotspots |
| `deleteTour(id)` | DELETE | `/ads/:id/tour` | Delete entire tour |
| `getNeighborhoodScorecard(id, force?)` | GET | `/ads/:id/neighborhood-scorecard` | Neighbourhood POI scores |

---

### `owner.service.ts`

Owner-specific operations. This is the largest service file.

**Analytics**

| Function | Method | Endpoint |
|---|---|---|
| `getAnalytics(params)` | GET | `/my/ads/analytics` |

**Ad Management**

| Function | Method | Endpoint |
|---|---|---|
| `getMyAds(params)` | GET | `/my/ads` |
| `duplicateAd(id)` | POST | `/my/ads/:id/duplicate` |
| `bulkUpdateAdStatus(data)` | PUT | `/my/ads/bulk-update` |
| `bulkDeleteAds(data)` | POST | `/my/ads/bulk-delete` |

**Lease Contracts**

| Function | Method | Endpoint |
|---|---|---|
| `getLeaseContracts(params)` | GET | `/my/lease-contracts` |
| `getLeaseContract(id)` | GET | `/my/lease-contracts/:id` |
| `updateLeaseContract(id, data)` | PUT | `/my/lease-contracts/:id` |
| `downloadLeaseContract(id)` | GET | `/my/lease-contracts/:id/download` |
| `generateLeaseContract(id, data)` | POST | `/my/lease-contracts/:id/generate` |
| `enhanceLeaseConditions(data)` | POST | `/my/lease-contracts/ai/enhance-conditions` |

**Tenants**

| Function | Method | Endpoint |
|---|---|---|
| `getTenants(params)` | GET | `/my/tenants` |
| `getTenant(id)` | GET | `/my/tenants/:id` |
| `createTenant(data)` | POST | `/my/tenants` |
| `updateTenant(id, data)` | PUT | `/my/tenants/:id` |
| `deleteTenant(id)` | DELETE | `/my/tenants/:id` |

**Expenses & Finance**

| Function | Method | Endpoint |
|---|---|---|
| `getExpenses(adId, params)` | GET | `/my/ads/:id/expenses` |
| `createExpense(adId, data)` | POST | `/my/ads/:id/expenses` |
| `deleteExpense(id)` | DELETE | `/my/expenses/:id` |
| `getProfitLoss(adId)` | GET | `/my/ads/:id/profit-loss` |

**Boost**

| Function | Method | Endpoint |
|---|---|---|
| `boostAd(id, data)` | POST | `/my/ads/:id/boost` |
| `selfBoostAd(id, data)` | POST | `/my/ads/:id/boost` |
| `unboostAd(id)` | DELETE | `/my/ads/:id/boost` |
| `getBoostStatus(id)` | GET | `/my/ads/:id/boost-status` |
| `getBoostPlans()` | GET | `/my/boost-plans` |

**Viewing Reservations**

| Function | Method | Endpoint |
|---|---|---|
| `getViewingReservations(params)` | GET | `/my/viewing-reservations` |
| `confirmReservation(id)` | POST | `/reservations/:id/confirm` |
| `cancelReservation(id, data)` | DELETE | `/reservations/:id` |
| `updateReservationNotes(id, data)` | PATCH | `/reservations/:id/notes` |

**Availability (Zap)**

| Function | Method | Endpoint |
|---|---|---|
| `getAvailabilities(adId)` | GET | `/ads/:id/availability` |
| `createAvailability(adId, data)` | POST | `/ads/:id/availability` |
| `updateAvailability(adId, scheduleId, data)` | PUT | `/ads/:id/availability/:scheduleId` |
| `deleteAvailability(adId, scheduleId)` | DELETE | `/ads/:id/availability/:scheduleId` |
| `getAvailabilityCalendar(adId, params)` | GET | `/ads/:id/availability/calendar` |

**Team**

| Function | Method | Endpoint |
|---|---|---|
| `getTeam()` | GET | `/my/team` |
| `inviteTeamMember(data)` | POST | `/my/team/invite` |
| `acceptTeamInvitation(token)` | POST | `/my/team/invitations/:token/accept` |
| `revokeTeamInvitation(id)` | DELETE | `/my/team/invitations/:id` |
| `removeTeamMember(id)` | DELETE | `/my/team/members/:id` |

**Security**

| Function | Method | Endpoint |
|---|---|---|
| `getLoginHistory(params)` | GET | `/my/login-history` |
| `clearLoginHistory()` | DELETE | `/my/login-history` |

**E-Signatures**

| Function | Method | Endpoint |
|---|---|---|
| `getSignatureRequests(contractId)` | GET | `/my/lease-contracts/:id/signatures` |
| `createSignatureRequest(contractId, data)` | POST | `/my/lease-contracts/:id/signatures` |
| `getPublicSignatureRequest(token)` | GET | `/signatures/:token` |
| `signSignatureRequest(token)` | POST | `/signatures/:token/sign` |
| `declineSignatureRequest(token, data)` | POST | `/signatures/:token/decline` |

---

### `viewings.service.ts`

Tenant viewing booking operations.

| Function | Method | Endpoint |
|---|---|---|
| `getSlots(adId, date)` | GET | `/ads/:adId/slots` |
| `reserve(adId, data)` | POST | `/ads/:adId/reservations` |
| `myReservations(params)` | GET | `/my/reservations` |
| `cancel(id, reason?)` | DELETE | `/reservations/:id` |

---

### `payments.service.ts`

Flutterwave payment operations.

| Function | Method | Endpoint |
|---|---|---|
| `initialize(adId)` | POST | `/payments/initialize/:adId` |
| `flutterwaveInitiate(data)` | POST | `/payments/initiate_payment` |
| `flutterwaveVerify(txRef)` | POST | `/payments/verify_payment` |
| `flutterwaveCancel(txRef)` | POST | `/payments/cancel_payment` |
| `getHistory(params)` | GET | `/payments/history` |

---

### `users.service.ts`

User profile operations.

| Function | Method | Endpoint |
|---|---|---|
| `list(params)` | GET | `/users` |
| `show(id)` | GET | `/users/:id` |
| `update(id, formData)` | POST | `/users/:id` |
| `updateProfile(id, data)` | PUT | `/users/:id` |
| `getPublicProfile(id)` | GET | `/users/:id/public-profile` |
| `recommendations.list()` | GET | `/recommendations` |
| `unlockedAds.list()` | GET | `/my/unlocked-ads` |

---

### `searchAlerts.service.ts`

Saved search alert management.

| Function | Method | Endpoint |
|---|---|---|
| `list()` | GET | `/search-alerts` |
| `create(data)` | POST | `/search-alerts` |
| `update(id, data)` | PUT | `/search-alerts/:id` |
| `remove(id)` | DELETE | `/search-alerts/:id` |

---

### `surveys.service.ts` & `publicSurveys.service.ts`

| Function | Method | Endpoint |
|---|---|---|
| `surveys.getActive()` | GET | `/surveys/active` |
| `surveys.get(id)` | GET | `/surveys/:id` |
| `surveys.submitResponse(id, data)` | POST | `/surveys/:id/responses` |
| `surveys.hasAnswered(id)` | GET | `/surveys/:id/has-answered` |
| `publicSurveys.list()` | GET | `/public/surveys` |
| `publicSurveys.get(slug, params)` | GET | `/public/surveys/:slug` |
| `publicSurveys.submit(slug, data)` | POST | `/public/surveys/:slug/respond` |

---

### `notifications.service.ts`

| Function | Method | Endpoint |
|---|---|---|
| `fetchNotifications(params)` | GET | `/notifications` |
| `fetchUnreadCount()` | GET | `/notifications/unread-count` |
| `markNotificationAsRead(id)` | POST | `/notifications/:id/read` |
| `markAllNotificationsAsRead()` | POST | `/notifications/read-all` |
| `deleteNotification(id)` | DELETE | `/notifications/:id` |

---

### `reviews.service.ts`

| Function | Method | Endpoint |
|---|---|---|
| `create(data)` | POST | `/reviews` |

---

### `subscriptions.service.ts`

| Function | Method | Endpoint |
|---|---|---|
| `getPlans()` | GET | `/subscriptions/plans` |
| `getCurrent()` | GET | `/subscriptions/current` |
| `getHistory(params)` | GET | `/subscriptions/history` |

---

### `estimator.service.ts`

Market pricing tools.

| Function | Method | Endpoint |
|---|---|---|
| `estimate.estimate(params)` | GET | `/rent-estimate` |
| `heatmap.get(params)` | GET | `/price-heatmap` |
| `keyScore.get(adId)` | GET | `/ads/:id/keyscore` |

---

### `agency.service.ts`

| Function | Method | Endpoint |
|---|---|---|
| `getProfile(id)` | GET | `/agencies/:id` |

---

### Other Services

| Service | Primary Endpoint |
|---|---|
| `cities.service.ts` | `/cities` |
| `property-attributes.service.ts` | `/property-attributes` |
| `geo.service.ts` | `/geo/*` |
| `ad-reports.service.ts` | `/ads/:id/reports` |
| `credits.service.ts` | `/credits` |

---

## Error Handling

All services propagate Axios errors. Use `extractErrorMessage()` from `src/lib/error-messages.ts` to get user-friendly messages:

```typescript
import { extractErrorMessage } from '@/lib/error-messages';

try {
 await adsService.create(formData);
} catch (error) {
 const message = extractErrorMessage(error);
 enqueueSnackbar(message, { variant: 'error' });
}
```

```typescript
// src/lib/error-messages.ts
export function extractErrorMessage(error: unknown): string {
 if (axios.isAxiosError(error)) {
 return error.response?.data?.message
 ?? error.response?.data?.error
 ?? error.message
 ?? 'Une erreur est survenue';
 }
 if (error instanceof Error) return error.message;
 return 'Une erreur inconnue est survenue';
}
```

---

## Related Documentation

- [AuthProvider](./auth-provider.md) — Token & auth state
- [Middleware](./middleware.md) — Route protection
- [Hooks](./hooks.md) — TanStack Query wrappers
