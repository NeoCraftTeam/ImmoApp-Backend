# KeyHome — Visitors (mobile)

Native iOS + Android app for KeyHome's visitor / browser flow.
**Not a webview** — fully native UI via Tamagui on top of Expo SDK 54
and React Native 0.81.

## Stack

| Layer            | Pick                                          | Why                                                                                                |
| ---------------- | --------------------------------------------- | -------------------------------------------------------------------------------------------------- |
| Runtime          | Expo SDK 54 (managed)                         | EAS Build, OTA updates, no Xcode/Android Studio gymnastics for the dev loop.                       |
| Routing          | `expo-router` v6                              | File-based — matches the Next.js mental model already established in the web app.                  |
| UI               | `tamagui` v1.122                              | Cross-platform primitives, compile-time style extraction, themable.                                |
| Server state     | `@tanstack/react-query` v5                    | Same library the web frontend uses — patterns transfer.                                            |
| HTTP             | `axios` v1.7                                  | Interceptors for the bearer token + 401 → sign-out.                                                |
| Token persistence| `expo-secure-store`                           | Keychain on iOS / EncryptedSharedPreferences on Android.                                           |
| Forms            | `react-hook-form` + `zod`                     | Same combo the frontend uses (currently only used on register; login is light enough without).    |
| Images           | `expo-image`                                  | On-disk cache, fade-in, native priority hints.                                                     |
| i18n             | `i18n-js` + `expo-localization`               | Detect device locale, fall back to French.                                                         |
| Type safety      | TypeScript strict + `noUncheckedIndexedAccess`| Same baseline as the backend's PHPStan / the frontend's tsc.                                       |

## Folder layout

```
mobile/visitors/
├── app/                              # File-based routes (expo-router)
│   ├── _layout.tsx                   # Root providers (Tamagui, Query, Session)
│   ├── index.tsx                     # Gate: onboarding vs home
│   ├── onboarding.tsx
│   ├── (auth)/
│   │   ├── _layout.tsx
│   │   ├── login.tsx
│   │   └── register.tsx
│   ├── home.tsx
│   └── ads/
│       └── [slug].tsx
├── src/
│   ├── api/                          # Axios client + endpoint registry
│   ├── auth/                         # SessionProvider + SecureStore helpers
│   ├── components/                   # Reusable UI (AdCard etc.)
│   ├── hooks/                        # TanStack Query hooks (useAdFeed, useAd)
│   ├── i18n/                         # French + English string tables
│   ├── providers/                    # Cross-cutting React contexts
│   ├── theme/                        # Brand tokens (mirrors web)
│   └── types/                        # API response types
├── assets/                           # Icons / splash / etc. (placeholder)
├── tamagui.config.ts
├── babel.config.js
├── metro.config.js
├── app.json                          # Expo config (bundle ids, plugins, extra)
├── package.json
└── tsconfig.json
```

## Install + run

```bash
cd mobile/visitors
pnpm install        # or npm install
cp .env.example .env
# edit .env to point EXPO_PUBLIC_API_BASE_URL at your Laravel backend
# (e.g. http://192.168.1.42:8000/api/v1 if running on LAN)

pnpm start          # opens Metro + the Expo dev menu
# press `i` for iOS simulator, `a` for Android, scan the QR for a real device
```

For production builds:

```bash
eas build --platform ios       # requires Apple Developer + EAS account
eas build --platform android   # builds an AAB ready for Play Store
```

## What's shipped (v0.4)

| Screen / feature                | File                                  | Status |
| ------------------------------- | ------------------------------------- | ------ |
| Onboarding (carousel)           | `app/onboarding.tsx`                  | ✅      |
| Login                           | `app/(auth)/login.tsx`                | ✅      |
| Register                        | `app/(auth)/register.tsx`             | ✅      |
| Tabs shell                      | `app/(tabs)/_layout.tsx`              | ✅      |
| Home feed                       | `app/(tabs)/home.tsx`                 | ✅      |
| Search (text + filter sheet)    | `app/(tabs)/search.tsx`               | ✅      |
| Favorites                       | `app/(tabs)/favorites.tsx`            | ✅      |
| Account (+ Tools section)       | `app/(tabs)/account.tsx`              | ✅      |
| Ad detail                       | `app/ads/[slug].tsx`                  | ✅      |
| Favorite toggle (card + detail) | `src/components/FavoriteButton.tsx`   | ✅      |
| Share sheet (ad detail)         | `app/ads/[slug].tsx`                  | ✅      |
| Compare flow (4-ad picker)      | `app/compare.tsx` + `CompareBar`      | ✅ v0.4 |
| Rent estimator                  | `app/estimator.tsx`                   | ✅ v0.4 |

Plus the foundation:

- Tamagui theme bridge mapping KeyHome brand tokens onto `$brand` / `$brandText` / `$success` / `$warning` / `$danger`.
- Axios singleton with Authorization interceptor + 401 auto-logout.
- TanStack Query client with mobile-tuned defaults (5 min stale, no window-focus refetch).
- `SessionProvider` with SecureStore-backed token persistence + sign-in / sign-up / sign-out.
- `useStorageState` hook (matches the canonical Expo Router auth pattern).
- i18n with French default + English fallback, device-locale detection.
- `expo-image` for cached, prioritised image rendering.

## What's NOT shipped yet — roadmap

These are wired into the audit but not implemented in this v0.4 cut:

- **Nearby**: map-backed proximity search (needs `@rnmapbox/maps`)
- **Ad detail map**: 3D tour viewer, NeighborhoodScorecard, DirectionsPanel
- **Compare**: 2–4 ad comparison sheet
- **Estimator**: rent-estimator widget
- **Messages**: in-app chat (Sanctum + Pusher)
- **Notifications**: push (expo-notifications) + in-app inbox
- **Viewing requests**: schedule + reschedule flow
- **Account**: profile + settings + currency picker + sign-out
- **Payments**: unlock-points purchase (Stripe Payment Element via WebView / native Stripe SDK)
- **Legal**: ToS / privacy / about pages

Each remaining screen has a corresponding web file in
`keyhome-frontend-next/src/app/...` that can serve as the source-of-truth
for layout, copy, and behaviour. The patterns established here
(`useAdFeed` / `useAd`, the `SessionProvider`, the Tamagui theme tokens)
mean each new screen is roughly a `app/<route>.tsx` file plus an
optional `src/hooks/use<Thing>.ts` query hook.

## Tests

No tests in v0.1 — when the screen surface is established, Jest +
`@testing-library/react-native` is the natural pick. Component tests
should target the hooks (mocked Axios) more than the screens (which
are mostly Tamagui layout).
