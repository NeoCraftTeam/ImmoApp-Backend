---
sidebar_position: 6
title: "UI Components"
---

# UI Components

## Overview

This document covers the key reusable UI components in `src/components/`. Components are organised by domain/feature.

---

## Layout Components (`src/components/layout/`)

### `Navbar`

The main application navigation bar. Supports both desktop (with dropdown menus) and mobile (with a slide-out drawer).

**Props**: None (uses context — `useAuth`, `useNavbarState`, `useComparator`)

**Features**:
- Transparent on landing page, solid on scroll
- Role-aware navigation (customer vs owner links)
- Notification badge (unread count)
- Comparator badge (selected listings count)
- Dark mode toggle
- Language selector (future)
- Mobile hamburger menu

---

### `Footer`

Full-width site footer with links to legal pages, social media, app download links.

---

### `BottomNav`

Mobile bottom navigation bar. Only shown in **PWA standalone mode** (detected via `useIsStandalone`).

**Navigation items**: Home, Search, Publish, Favourites, Profile

---

## UI Primitives (`src/components/ui/`)

### `AppLoader`

Full-screen loading spinner shown while the app initialises or navigates.

```typescript
<AppLoader message="Chargement..." />
```

---

### `CookieBanner`

GDPR cookie consent banner. Shows on first visit, persists consent to localStorage.

```typescript
// Shown when no consent cookie found
// Provides: Accept All | Accept Necessary | Manage
```

---

### `ErrorBoundary`

React class error boundary with a fallback UI for unhandled render errors.

```typescript
<ErrorBoundary fallback={<ErrorFallback />}>
 <SomeComponent />
</ErrorBoundary>
```

---

### `FadeIn`

Stagger animation wrapper using Framer Motion. Children fade in sequentially.

```typescript
<FadeIn stagger={0.1}>
 <Card />
 <Card />
 <Card />
</FadeIn>
```

---

### `LogoutOverlay`

Full-screen overlay shown during logout with an animated farewell message and spinner. Displayed for 3.5 seconds.

---

### `PageTransition`

Wraps page content in a Framer Motion fade-in/fade-out transition.

```typescript
<PageTransition>
 <PageContent />
</PageTransition>
```

---

### `PushPrompt`

Web Push subscription request UI. Shows when:
- The browser supports Push notifications
- The user hasn't been asked yet (or dismissed > 30 days ago)
- The user is authenticated

Renders as a bottom-anchored card with "Enable Notifications" / "Later" buttons.

---

### `RouteProgressBar`

NProgress-style thin loading bar at the top of the page during navigation transitions.

---

### `SplashTransition`

Full-screen animated splash overlay shown on the first visit to the auth route group. Displays for 1400ms then fades out, revealing the auth form.

---

### `WelcomeModal`

First-time user onboarding modal. Shown after registration or first login. Introduces key features with step-by-step tour.

---

### `SkipLink`

Accessibility "skip to main content" link, visible only on keyboard focus. Required for WCAG compliance.

---

### `ConfirmDialog`

Reusable confirmation dialog (used throughout the owner panel for destructive actions).

```typescript
const { confirm } = useConfirmDialog();

const handleDelete = async () => {
 const confirmed = await confirm({
 title: 'Supprimer l\'annonce ?',
 description: 'Cette action est irréversible.',
 confirmLabel: 'Supprimer',
 variant: 'danger',
 });
 if (confirmed) await adsService.destroy(adId);
};
```

---

## Ad Components (`src/components/ads/`)

### `AdCard`

Property listing card displayed in search results and the home feed.

**Key features**:
- Primary image with lazy loading
- Price badge (formatted in XOF)
- Favourite toggle button (heart icon)
- Comparison checkbox
- Status badge (available, reserved, etc.)
- Agency or owner avatar
- Surface, bedroom, bathroom chips

---

### `AdDetail`

Full property detail page composition component. Renders all sections: images, map, tour, contact, booking.

---

### `AdImages`

Swipeable image gallery for ad photos with lightbox. Uses embla-carousel or similar.

---

### `AdTour`

Photo Sphere Viewer integration for 3D virtual tours. Supports:
- Equirectangular panoramas (full 360°)
- Partial panoramas (with `haov`/`vaov` clipping)
- Scene navigation via hotspots
- Keyboard controls (`src/lib/psvKeyboardActions.ts`)
- Mobile gyroscope support
- Full-screen mode

---

### `ComparatorBar`

Floating toolbar at the bottom of the screen showing selected comparison items (up to 4). Provides quick remove and "Compare" navigation.

---

## PWA Components (`src/components/pwa/`)

### `ServiceWorkerRegistrar`

Registers the service worker (`/sw.js`) on mount. Handles update notifications.

```typescript
// src/components/pwa/ServiceWorkerRegistrar.tsx
useEffect(() => {
 if ('serviceWorker' in navigator) {
 navigator.serviceWorker.register('/sw.js').then((registration) => {
 // Check for updates
 registration.addEventListener('updatefound', handleUpdate);
 });
 }
}, []);
```

---

### `PWAInstallPrompt` / `OwnerPWAInstallPrompt`

Captures the `beforeinstallprompt` event and shows a custom "Add to Home Screen" prompt. Owner version shows an owner-themed prompt.

---

### `OwnerManifestSwitch`

Dynamically switches the PWA web app manifest between the customer manifest and the owner manifest based on the current route, enabling different app names and icons for each PWA.

---

## Survey Components (`src/components/surveys/`)

### `SurveyPromptOrBanner`

Conditionally shows either:
- A **banner** at the top of the dashboard (subtle, dismissible)
- A **modal prompt** (for new surveys the user hasn't seen)

The display mode is controlled by the user's `preferences.survey_postponed_ids`.

---

### `SurveyForm`

Multi-question survey form supporting 4 question types:
- `multiple_choice` — Radio group (single selection)
- `checkbox` — Checkbox group (multiple selections)
- `rating` — Star rating widget (1-5)
- `text` — Free-text textarea

```typescript
<SurveyForm
 survey={survey}
 onSubmit={async (answers) => {
 await surveysService.submitResponse(survey.id, {
 answers,
 anonymous: false,
 });
 }}
/>
```

---

## SEO Components (`src/components/seo/`)

### `JsonLd`

Injects JSON-LD structured data into the page `<head>` for SEO.

```typescript
// Organisation schema (landing page)
<JsonLd
 type="Organization"
 data={{
 name: 'KeyHome',
 url: 'https://keyhome.cm',
 logo: 'https://keyhome.cm/icons/icon-512x512.png',
 }}
/>

// Breadcrumb schema (ad detail)
<JsonLd
 type="BreadcrumbList"
 data={breadcrumbs}
/>
```

---

### `WebVitals`

Tracks Core Web Vitals (LCP, FID, CLS, TTFB, FCP) and sends them to Google Analytics.

```typescript
// Uses web-vitals library
import { onCLS, onFID, onLCP } from 'web-vitals';
```

---

## Emotion + MUI Integration (`src/components/MuiEmotionRegistry.tsx`)

Required for **SSR compatibility** of MUI/Emotion styles. Ensures styles are collected server-side and not re-applied client-side (preventing FOUC).

```typescript
// src/components/MuiEmotionRegistry.tsx
'use client';
import createCache from '@emotion/cache';
import { useServerInsertedHTML } from 'next/navigation';
import { CacheProvider } from '@emotion/react';

export function MuiEmotionRegistry({ nonce, children }) {
 const cache = createCache({ key: 'mui', nonce, prepend: true });

 useServerInsertedHTML(() => (
 <style
 key={cache.key}
 data-emotion={`${cache.key} ${Object.keys(cache.inserted).join(' ')}`}
 dangerouslySetInnerHTML={{
 __html: Object.values(cache.inserted).join(' '),
 }}
 nonce={nonce}
 />
 ));

 return <CacheProvider value={cache}>{children}</CacheProvider>;
}
```

---

## Providers Summary

| Provider | File | Purpose |
|---|---|---|
| `AuthProvider` | `src/providers/AuthProvider.tsx` | Auth state, login/logout |
| `ComparatorProvider` | `src/providers/ComparatorProvider.tsx` | Ad comparison cart (max 4) |
| `FavoritesProvider` | `src/providers/FavoritesProvider.tsx` | Favourites (localStorage + API) |
| `OwnerThemeProvider` | `src/providers/OwnerThemeProvider.tsx` | Owner-specific MUI theme |
| `QueryProvider` | `src/providers/QueryProvider.tsx` | TanStack Query setup |
| `ThemeProvider` | `src/providers/ThemeProvider.tsx` | Light/dark theme management |
| `ToastProvider` | `src/providers/ToastProvider.tsx` | notistack snack bar |

---

## Related Documentation

- [Theme](./theme.md) — Design tokens and MUI customisation
- [Hooks](./hooks.md) — Hooks used by components
- [API Services](./api-services.md) — Services called by components
