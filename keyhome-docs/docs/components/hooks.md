---
sidebar_position: 5
title: "Custom Hooks"
---

# Custom Hooks

## Overview

The application defines **16 custom React hooks** in `src/hooks/`. These hooks encapsulate UI logic, API integration, browser API wrappers, and state management patterns.

---

## `useAnalytics`

**File**: `src/hooks/useAnalytics.ts`

Captures UTM parameters from the URL on first load and enriches GA4 events with them.

```typescript
export function useAnalytics() {
 const track = (name: FunnelEvent, params?: Record<string, unknown>) => {
 const utmParams = getSessionUtmParams(); // From sessionStorage
 window.gtag?.('event', name, { ...params, ...utmParams });
 };

 return { track };
}

type FunnelEvent =
 | 'ad_view'
 | 'contact_click'
 | 'payment_start'
 | 'payment_success'
 | 'registration_start'
 | 'registration_complete';
```

**Storage**: UTM params are captured once per session and stored in `sessionStorage` via `src/lib/utm.ts`.

---

## `useAutoSave`

**File**: `src/hooks/useAutoSave.ts`

Debounced localStorage persistence for form drafts. Used in the Ad creation wizard to prevent data loss on navigation.

```typescript
export function useAutoSave<T>({
 key,
 data,
 enabled = true,
}: {
 key: string;
 data: T;
 enabled?: boolean;
}) {
 // Debounce: 2000ms
 // Returns:
 return {
 savedAt: Date | null,
 hasDraft: boolean,
 restoreDraft(): T | null,
 clearDraft(): void,
 };
}
```

**Usage**:
```typescript
const { hasDraft, restoreDraft, clearDraft, savedAt } = useAutoSave({
 key: 'ad-wizard-draft',
 data: formValues,
});

// On component mount, restore draft if available
useEffect(() => {
 if (hasDraft) {
 const draft = restoreDraft();
 if (draft) form.reset(draft);
 }
}, []);
```

---

## `useCountUp`

**File**: `src/hooks/useCountUp.ts`

Animated number counter triggered by intersection observer (counts up when element scrolls into view).

```typescript
export function useCountUp({
 end: number,
 duration?: number, // Default: 2000ms
 start?: number, // Default: 0
 decimals?: number, // Default: 0
 triggerOnce?: boolean, // Default: true
}) {
 return {
 value: number, // Current animated value
 ref: RefObject, // Attach to the element to trigger animation
 };
}
```

**Features**:
- Ease-out cubic easing function
- Respects `prefers-reduced-motion` (shows final value immediately)
- `triggerOnce` prevents re-animation on scroll back

**Usage**:
```typescript
const { value, ref } = useCountUp({ end: 12450, duration: 2000 });
return <span ref={ref}>{value.toLocaleString('fr-FR')}</span>;
```

---

## `useGreeting`

**File**: `src/hooks/useGreeting.ts`

Returns a time-appropriate French greeting string.

```typescript
export function useGreeting(): string {
 const hour = new Date().getHours();
 if (hour >= 5 && hour < 12) return 'Bonjour';
 if (hour >= 12 && hour < 18) return 'Bon après-midi';
 if (hour >= 18 && hour < 22) return 'Bonsoir';
 return 'Bonne nuit';
}
```

---

## `useIsStandalone`

**File**: `src/hooks/useIsStandalone.ts`

Detects if the app is running as an installed PWA (standalone/fullscreen mode).

```typescript
export function useIsStandalone(): boolean {
 // Checks:
 // - window.matchMedia('(display-mode: standalone)')
 // - window.matchMedia('(display-mode: fullscreen)')
 // - (navigator as any).standalone (iOS Safari)
}
```

Used by the dashboard layout to conditionally show the `BottomNav` navigation bar for PWA users.

---

## `useLandingStats`

**File**: `src/hooks/useLandingStats.ts`

Fetches platform-wide statistics for the landing page and auth pages.

```typescript
export function useLandingStats() {
 return {
 stats: {
 ads_count: number;
 cities_count: number;
 users_count: number;
 } | undefined,
 isLoading: boolean,
 };
}
```

**Query**: `staleTime: 15 minutes`, cached under key `['landing-stats']`.

---

## `useLandingTestimonials`

**File**: `src/hooks/useLandingTestimonials.ts`

Returns static or API-fetched testimonials for the landing page carousel.

---

## `useNavbarState`

**File**: `src/hooks/useNavbarState.ts`

Manages all navbar UI state (dropdowns, mobile drawer, comparator badge, active route detection).

```typescript
export function useNavbarState() {
 return {
 // Desktop dropdowns
 openDropdown: string | null,
 openDropdownMenu: (key: string) => void,
 closeDropdown: () => void,

 // Mobile drawer
 isMobileDrawerOpen: boolean,
 openMobileDrawer: () => void,
 closeMobileDrawer: () => void,

 // Comparator
 comparatorCount: number,

 // Route detection
 isActiveRoute: (path: string) => boolean,
 };
}
```

---

## `useOutlinedInputLabelShrink`

**File**: `src/hooks/useOutlinedInputLabelShrink.ts`

Manages MUI `OutlinedInput` label shrink state when an input has a start adornment icon. Without this hook, the label overlaps the icon.

```typescript
export function useOutlinedInputLabelShrink(hasContent: boolean) {
 return {
 shrink: boolean, // Whether to shrink the label
 onFocus: FocusHandler, // Set shrink to true
 onBlur: BlurHandler, // Set shrink back to hasContent value
 };
}
```

---

## `usePayment`

**File**: `src/hooks/usePayment.ts`

Manages Flutterwave payment initiation and state.

```typescript
export function usePayment() {
 return {
 isLoading: boolean,
 error: string | null,
 response: FlutterwaveInitiateResponse | null,
 initiatePayment(payload: FlutterwaveInitiatePayload): Promise<void>,
 resetPayment(): void,
 };
}
```

**Features**:
- On success: auto-redirects to `payment_link` (Flutterwave hosted page)
- Persists `tx_ref` to `sessionStorage` for the `/payment-success` callback page
- Handles different payment methods (mobile money OTP vs card redirect)

---

## `usePushNotifications`

**File**: `src/hooks/usePushNotifications.ts`

Manages Web Push API subscription lifecycle.

```typescript
export function usePushNotifications() {
 return {
 isSupported: boolean, // Browser supports Push API
 permission: NotificationPermission, // 'default' | 'granted' | 'denied'
 isSubscribed: boolean, // Active push subscription exists
 isDismissed: boolean, // User dismissed the prompt
 subscribe(): Promise<void>,
 unsubscribe(): Promise<void>,
 dismiss(): void, // Saves dismissal to localStorage
 };
}
```

**Features**:
- Registers service worker before subscribing
- Uses `NEXT_PUBLIC_VAPID_PUBLIC_KEY` for VAPID authentication
- Sends subscription object to backend via `POST /pwa/subscribe`
- Dismissal persisted to localStorage for 30 days

---

## `useRecentlyViewed`

**File**: `src/hooks/useRecentlyViewed.ts`

Tracks and retrieves recently viewed property listings.

```typescript
export function useRecentlyViewed() {
 return {
 items: Ad[], // Up to 10 items (localStorage + API merged)
 addRecentlyViewed(ad: Ad): void,
 clearRecentlyViewed(): void,
 };
}
```

**Strategy**:
1. For unauthenticated users: localStorage only (up to 10 items)
2. For authenticated users: localStorage + API sync via `GET /my/recently-viewed`
3. On mount: merges local and server items, deduplicates, sorts by recency

---

## `useSearchHistory`

**File**: `src/hooks/useSearchHistory.ts`

Persists user's recent search queries to localStorage.

```typescript
export function useSearchHistory() {
 return {
 history: string[], // Up to 8 recent queries
 addSearch(query: string): void,
 removeSearch(query: string): void,
 clearHistory(): void,
 getSuggestions(input: string): string[], // Case-insensitive filtering
 };
}
```

**Storage key**: `keyhome_search_history`

---

## `useSoundFeedback`

**File**: `src/hooks/useSoundFeedback.ts`

Plays audio feedback sounds (success ding, error buzz) for key user actions.

```typescript
export function useSoundFeedback() {
 return {
 playSuccess(): void,
 playError(): void,
 };
}
```

Uses the Web Audio API (`AudioContext`) to generate sounds programmatically — no audio file dependencies.

---

## `useTransactionStatus`

**File**: `src/hooks/useTransactionStatus.ts`

Polls or monitors a Flutterwave payment transaction status after the user returns from the payment page.

```typescript
export function useTransactionStatus(txRef: string | null) {
 return {
 status: 'pending' | 'success' | 'failed' | null,
 isLoading: boolean,
 error: string | null,
 };
}
```

Used by the `/payment-success` page to verify payment and show the appropriate confirmation UI.

---

## `useUserLocation`

**File**: `src/hooks/useUserLocation.ts`

Geolocation API wrapper with caching and accuracy validation.

```typescript
export function useUserLocation() {
 return {
 location: { lat: number; lng: number } | null,
 loading: boolean,
 error: GeolocationPositionError | null,
 refresh(): void,
 };
}
```

**Features**:
- **10-minute cache** in sessionStorage to avoid repeated permission prompts
- **5km accuracy threshold** — rejects readings with accuracy > 5000m
- Uses `watchPosition` for continuous updates when enabled
- Falls back gracefully when permission denied

---

## `useViewingResponseSync`

**File**: `src/hooks/useViewingResponseSync.ts`

Synchronises viewing reservation state across browser tabs using the `BroadcastChannel` API.

```typescript
export function useViewingResponseSync(reservationId: string) {
 // Broadcasts reservation status changes to other open tabs
 // Useful when the confirmation email link opens in a new tab
}
```

---

## TanStack Query Integration

Most hooks that fetch data use TanStack Query. The query key factory is in `src/lib/query-keys.ts`:

```typescript
// src/lib/query-keys.ts
export const queryKeys = {
 ads: {
 all: ['ads'] as const,
 list: (params: SearchParams) => ['ads', 'list', params] as const,
 detail: (id: string) => ['ads', 'detail', id] as const,
 tour: (id: string) => ['ads', 'tour', id] as const,
 },
 user: {
 me: ['user', 'me'] as const,
 profile: (id: string) => ['user', 'profile', id] as const,
 },
 reservations: {
 mine: (params?: object) => ['reservations', 'mine', params] as const,
 },
 searchAlerts: {
 all: ['search-alerts'] as const,
 },
 // ... more keys
};
```

---

## Related Documentation

- [AuthProvider](./auth-provider.md) — `useAuth()` hook
- [API Services](./api-services.md) — Services used by hooks
