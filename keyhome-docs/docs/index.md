---
sidebar_position: 1
title: "KeyHome Frontend — Overview"
---

# KeyHome Frontend — Technical Documentation

## Project Overview

**KeyHome** is a pan-African real estate marketplace targeting West African markets (XOF/XAF currency zones — Cameroon, Ivory Coast, Senegal, etc.). The frontend is a Next.js 16 Progressive Web App that enables property seekers, landlords/agents, and platform administrators to search, list, book viewings, and manage rental contracts.

---

## Tech Stack

| Category | Technology | Version |
|---|---|---|
| Framework | Next.js (App Router) | 16 |
| UI Library | React | 19 |
| Component System | MUI (Material UI) | 7 |
| Styling | Tailwind CSS | v4 |
| Authentication | Clerk | latest |
| Server State | TanStack Query (React Query) | 5 |
| Animation | Framer Motion | latest |
| Maps | Mapbox GL JS | latest |
| 360° Tours | Photo Sphere Viewer | latest |
| Charts | Recharts | latest |
| 3D Graphics | Three.js | latest |
| i18n | next-intl | latest |
| Unit Tests | Vitest | latest |
| E2E Tests | Playwright | latest |
| HTTP Client | Axios | latest |
| Forms | React Hook Form + Zod | latest |
| Notifications | notistack | latest |

---

## Architecture

### App Router & Route Groups

The application uses Next.js App Router with four distinct route groups, each with its own layout and authentication context:

```
src/app/
 (auth)/ # Unauthenticated auth flows (login, register, verify)
 (dashboard)/ # Authenticated customer experience
 (owner)/ # Owner/Agent management panel
 (landing)/ # Marketing & public pages
 layout.tsx # Root layout (ClerkProvider, PWA, analytics)
 page.tsx # Landing page redirect
 [...slug]/ # Catch-all for dynamic public pages
```

### Middleware (CSP & Route Guards)

`src/proxy.ts` acts as Clerk-powered middleware with:
- Per-request **nonce-based Content Security Policy** headers
- **Role-based route protection** (owner cookie guard, customer path guards)
- Automatic redirects for authenticated users on the landing page

### Authentication Architecture

The app supports **dual authentication**:
1. **Laravel Sanctum** (email/password) — primary for customers and owners
2. **Clerk** (OAuth/social) — Google, Facebook, Apple via redirect flow

The `AuthProvider` manages in-memory token storage (XSS protection), session migrations, role-based redirects, and a 3.5-second logout overlay.

### Data Fetching

All API calls go through a central Axios instance in `src/lib/api.ts`:
- Automatic **CSRF token** fetching for Sanctum
- **401 auth-expired** event dispatch
- **419 CSRF retry** logic
- **30-second timeout** with per-request override support

TanStack Query wraps all data fetching with:
- `staleTime: 5 minutes`
- `gcTime: 10 minutes`
- No retry on 401/403
- No refetch on window focus

---

## User Roles

| Role | Description | Entry Point |
|---|---|---|
| **Visitor** | Unauthenticated user browsing listings | `/` (landing) |
| **Tenant / Customer** | Authenticated user searching & booking properties | `/home` |
| **Owner / Agent (Bailleur)** | Property owner or agency managing listings | `/owner/dashboard` |
| **Admin** | Platform administrator | `/admin` (Filament panel via SSO) |

---

## Route Summary

### Public / Visitor Routes

| Route | Purpose |
|---|---|
| `/` | Landing/marketing homepage |
| `/search` | Advanced property search |
| `/home` | Main listings feed (redirects if unauthenticated) |
| `/immobilier` | Real estate info page |
| `/agences` | Agency directory |
| `/bailleurs` | Landlord info page |
| `/proprietaires` | Property owner info |
| `/blog`, `/blog/*` | Articles & blog posts |
| `/conditions` | Terms of service |
| `/confidentialite` | Privacy policy |
| `/credits` | Credits purchase page |
| `/sondage/*` | Public survey pages |
| `/type-bien` | Property type browser |

### Customer (Authenticated) Routes

| Route | Purpose |
|---|---|
| `/login`, `/register` | Authentication |
| `/forgot-password`, `/reset-password` | Password recovery |
| `/verify-email`, `/verify-otp` | Email OTP verification |
| `/complete-profile` | Post-OAuth profile setup |
| `/home` | Main feed & search results |
| `/nearby` | Geolocation-based nearby listings |
| `/profile` | User profile (PRIVATE) |
| `/my/reservations` | Viewing booking history |
| `/notifications` | Push notification inbox |
| `/publish` | Ad creation wizard |
| `/search-alerts` | Saved search alerts |
| `/comparaisons` | Property comparison tool |
| `/payments` | Payment history |
| `/parametres` | Account settings |

### Owner Routes

| Route | Purpose |
|---|---|
| `/owner`, `/owner/login`, `/owner/register` | Owner auth |
| `/owner/dashboard` | Analytics & ad management hub |
| `/owner/ads` | Manage property listings |
| `/owner/availability` | Viewing schedule management (Zap) |
| `/owner/lease-contracts` | Lease contract generation & e-signature |
| `/owner/tenants` | Tenant management |
| `/owner/financials` | Expenses & profit/loss |
| `/owner/payments` | Payment settings & history |
| `/owner/viewings` | Incoming viewing reservations |
| `/owner/reviews` | Review management |
| `/owner/pro-services` | Pro service purchases |
| `/owner/subscriptions` | Subscription management |
| `/owner/equipe` | Team & invitations |
| `/owner/security` | Login history & security |

### Special Routes

| Route | Purpose |
|---|---|
| `/payment-success` | Flutterwave payment redirect callback |
| `/tour-proxy` | 3D virtual tour player proxy |
| `/sso-callback` | OAuth SSO callback handler |
| `/health` | Health check API endpoint |
| `/offline` | PWA offline fallback page |

---

## Environment Variables

```bash
# Required
NEXT_PUBLIC_API_URL=https://api.keyhome.cm/api/v1
NEXT_PUBLIC_MAPBOX_TOKEN=pk.eyJ1...
NEXT_PUBLIC_CLERK_PUBLISHABLE_KEY=pk_live_...
CLERK_SECRET_KEY=sk_live_...
NEXT_PUBLIC_VAPID_PUBLIC_KEY=BHj...
NEXT_PUBLIC_OWNER_PANEL=next # 'next' = integrated bailleur UI, 'laravel' = Filament panel

# Optional
NEXT_PUBLIC_SENTRY_DSN=https://...
NEXT_PUBLIC_GA_MEASUREMENT_ID=G-XXXXXXXX
ANALYZE=true # Bundle analysis
```

---

## Development Setup

```bash
# 1. Clone & enter the frontend directory
cd keyhome-frontend-next

# 2. Install dependencies
npm ci

# 3. Copy environment file
cp .env.example .env.local
# Edit .env.local with your API URL and keys

# 4. Start development server
npm run dev

# 5. Open browser at http://localhost:3000
```

### Available Scripts

| Script | Purpose |
|---|---|
| `npm run dev` | Start Next.js development server |
| `npm run build` | Production build |
| `npm run start` | Start production server |
| `npm run lint` | Run ESLint |
| `npm run test` | Run Vitest unit tests |
| `npm run test:e2e` | Run Playwright E2E tests |

---

## Project Structure

```
src/
 app/ # Next.js App Router pages & layouts
 (auth)/ # Auth route group
 (dashboard)/ # Customer route group
 (owner)/ # Owner route group
 (landing)/ # Marketing route group
 components/ # Reusable React components
 ads/ # Ad listing & detail components
 auth/ # Authentication UI
 dashboard/ # Dashboard-specific components
 landing/ # Marketing/landing components
 layout/ # Navbar, Footer, BottomNav
 maps/ # Mapbox integration
 notifications/ # Notification UI
 owner/ # Owner panel components
 payment/ # Payment flow components
 pwa/ # PWA install prompts & SW
 seo/ # JSON-LD & meta
 surveys/ # Survey UI
 ui/ # Generic UI primitives
 viewing/ # Viewing/booking UI
 hooks/ # Custom React hooks (16)
 lib/ # Utilities & helpers
 providers/ # React Context providers
 services/ # API service layer (22 files)
 theme/ # MUI design tokens & themes
 types/ # TypeScript type definitions
```

---

## Documentation Index

- **[Visitor Role](./roles/visitor/index.md)** — Public browsing, search, landing page
- **[Tenant Role](./roles/tenant/index.md)** — Authenticated customer experience
- **[Owner Role](./roles/owner/index.md)** — Property management panel
- **[Admin Role](./roles/admin/index.md)** — Platform administration
- **[Components](./components/index.md)** — UI components, services, hooks
