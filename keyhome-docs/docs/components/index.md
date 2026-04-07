---
sidebar_position: 1
title: "Components Overview"
---

# Components & Services Overview

This section documents the reusable frontend components, API services, hooks, utilities, and design system used throughout the KeyHome frontend.

## Structure

```
src/
 components/ # React UI components
 services/ # API service layer (22 files)
 hooks/ # Custom React hooks (16 hooks)
 providers/ # React Context providers
 lib/ # Utility functions & helpers
 theme/ # MUI design tokens & themes
```

## Component Categories

| Category | Location | Description |
|---|---|---|
| Ad Components | `src/components/ads/` | Listing cards, search, details, tours |
| Auth UI | `src/components/auth/` | Social login, OTP verification |
| Dashboard | `src/components/dashboard/` | Customer dashboard widgets |
| Landing | `src/components/landing/` | Marketing/homepage components |
| Layout | `src/components/layout/` | Navbar, Footer, BottomNav |
| Maps | `src/components/maps/` | Mapbox integration |
| Notifications | `src/components/notifications/` | Notification inbox |
| Owner | `src/components/owner/` | Owner panel components |
| Payment | `src/components/payment/` | Payment flow, unlock |
| PWA | `src/components/pwa/` | Install prompts, SW registrar |
| SEO | `src/components/seo/` | JSON-LD, Web Vitals |
| Surveys | `src/components/surveys/` | Survey prompt, form |
| UI | `src/components/ui/` | Primitives (loader, toast, dialog) |
| Viewing | `src/components/viewing/` | Slot picker, reservation cards |

## Documentation Index

- **[AuthProvider](./auth-provider.md)** — Authentication context & dual auth
- **[API Services](./api-services.md)** — All 22 service files documented
- **[Middleware](./middleware.md)** — CSP, route guards, Clerk integration
- **[Hooks](./hooks.md)** — All 16 custom hooks
- **[UI Components](./ui-components.md)** — Key reusable components
- **[Theme](./theme.md)** — Design tokens, MUI customisation, dark mode
