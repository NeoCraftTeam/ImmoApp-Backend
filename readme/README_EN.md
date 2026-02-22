# KeyHome — Complete Technical Documentation (English)

> **Generated:** 2026-02-22 | **Stack:** Laravel 12 · Next.js 16 · Expo 54 · PostgreSQL/PostGIS · MeiliSearch  
> **Repository root:** `/Users/feze/Developer/Laravel/ImmoApp-Backend`

---

## 1. PROJECT IDENTITY

### One-sentence summary
**KeyHome is a full-stack real-estate SaaS platform for French-speaking West/Central Africa that lets property owners and agencies list, promote, and monetise rental/sale listings while giving buyers a personalised, AI-powered search experience.**

### One-paragraph summary
KeyHome is composed of a Laravel 12 REST API backend, a Next.js 16 web frontend authenticated via Clerk, three Filament 4 admin/management panels (Admin, Agency, Bailleur/Owner), and two Expo React-Native mobile shells (one per panel). Listings are searchable via MeiliSearch full-text + PostGIS geo-search. Revenue is generated through pay-per-unlock contacts (FedaPay, XOF), agency subscription plans, and boosted listings. A weighted-scoring recommendation engine personalises the home feed. The entire stack runs on Docker Compose with Traefik reverse proxy and an optional Prometheus/Grafana monitoring profile.

### Target users
| User type | Entry point |
|-----------|-------------|
| Property seekers | Next.js web app (`keyhome-frontend-next`) |
| Individual landlords (Bailleurs) | Filament `/owner` panel + Expo `mobile/bailleur` shell |
| Real-estate agencies | Filament `/agency` panel + Expo `mobile/agency` shell |
| Platform administrators | Filament `/admin` panel |

### Problem solved
Fragmented, low-quality listing portals in the African market → KeyHome provides verified listings with AI-recommended matches, paywall-protected contact details (anti-spam), and a professional back-office for agents.

### Architecture type
**Monorepo** hosting four distinct applications sharing one backend API:
- Laravel REST API (monolith)
- Next.js 16 SPA/SSR (web frontend)
- Two Expo WebView shells (iOS/Android)
- Three Filament panels (web-based back-offices)

---

## 2. TECH STACK INVENTORY

### Backend (`/`)
| Tool | Version | Status |
|------|---------|--------|
| PHP | ^8.4 | ✅ Current |
| Laravel | ^12.0 | ✅ Current |
| Filament | ~4.0 | ✅ Current |
| Laravel Sanctum | ^4.0 | ✅ Current |
| Laravel Scout | ^10.21 | ✅ Current |
| Laravel Socialite | ^5.24 | ✅ Current |
| Laravel Telescope | ^5.15 | ✅ Current |
| Laravel Pulse | * | ✅ Current |
| Laravel Nightwatch | ^1.21 | ✅ Current |
| Spatie MediaLibrary | ^11.14 | ✅ Current |
| Spatie Activitylog | ^4.11 | ✅ Current |
| Clickbar Magellan (PostGIS) | ^2.0 | ✅ Current |
| FedaPay PHP SDK | ^0.4.7 | ✅ Current |
| MeiliSearch PHP | ^1.16 | ✅ Current |
| Sentry Laravel | ^4.20 | ✅ Current |
| Darkaonline L5-Swagger | ^9.0 | ✅ Current |
| Filament Socialite | ^3.1 | ✅ Current |
| Flowframe Laravel Trend | * | ✅ Current |
| Laravolt Avatar | ^6.3 | ✅ Current |
| Pest PHP | ^4.1 | ✅ Current |
| Larastan | ^3.0 | ✅ Current |
| Rector | ^2.1 | ✅ Current |
| PHP Pint | ^1.13 | ✅ Current |

### Frontend (`/keyhome-frontend-next`)
| Tool | Version | Status |
|------|---------|--------|
| Next.js | 16.1.6 | ✅ Current |
| React | 19.2.3 | ✅ Current |
| TypeScript | ^5 | ✅ Current |
| Clerk (Next.js) | ^6.38.1 | ✅ Current |
| MUI (Material UI) | ^7.3.7 | ✅ Current |
| TanStack React Query | ^5.90.21 | ✅ Current |
| Mapbox GL | ^3.18.1 | ✅ Current |
| React Map GL | ^8.1.0 | ✅ Current |
| React Hook Form | ^7.71.1 | ✅ Current |
| Zod | ^4.3.6 | ✅ Current |
| Axios | ^1.13.5 | ✅ Current |
| Tailwind CSS | ^4 | ✅ Current |
| date-fns | ^4.1.0 | ✅ Current |

### Mobile (`/mobile/agency` & `/mobile/bailleur`)
| Tool | Version | Status |
|------|---------|--------|
| Expo SDK | ~54.0.33 | ✅ Current |
| React Native | 0.81.5 | ✅ Current |
| React | 19.1.0 | ✅ Current |
| react-native-webview | 13.15.0 | ✅ Current |
| expo-notifications | ~0.32.16 | ✅ Current |
| expo-image-picker | ~17.0.10 | ✅ Current |
| expo-location | ~19.0.8 | ✅ Current |
| expo-haptics | ~15.0.8 | ✅ Current |
| expo-auth-session | ~7.0.10 | ✅ Current |
| react-native-maps | 1.20.1 | ✅ Current |

### Infrastructure
| Tool | Version |
|------|---------|
| Docker / Docker Compose | Latest stable |
| Nginx | Alpine |
| PostgreSQL + PostGIS | 15-3.3-alpine |
| Redis | Alpine |
| MeiliSearch | v1.10 |
| Traefik | External (reverse proxy) |
| Prometheus + Grafana | Latest (optional profile) |
| GitLab CI/CD | .gitlab-ci.yml |

### Package managers
- **Backend:** Composer 2
- **Frontend:** pnpm (primary), npm (lock file present)
- **Mobile:** npm

### Environment variables

**Backend (`.env`)**
```
APP_NAME, APP_KEY, APP_ENV, APP_URL, APP_DEBUG
DB_CONNECTION=pgsql, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD
REDIS_HOST, REDIS_PORT
MAIL_MAILER, MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD
MEILISEARCH_HOST, MEILISEARCH_KEY
FEDAPAY_PUBLIC_KEY, FEDAPAY_SECRET_KEY, FEDAPAY_ENVIRONMENT
GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, GOOGLE_REDIRECT_URI
FACEBOOK_CLIENT_ID, FACEBOOK_CLIENT_SECRET, FACEBOOK_REDIRECT_URI
APPLE_CLIENT_ID, APPLE_CLIENT_SECRET, APPLE_REDIRECT_URI
CLERK_PUBLISHABLE_KEY, CLERK_SECRET_KEY, CLERK_JWKS_URL, CLERK_WEBHOOK_SECRET
SENTRY_LARAVEL_DSN
NIGHTWATCH_TOKEN
FRONTEND_URL, EMAIL_VERIFY_CALLBACK, EMAIL_CALLBACK_URL
SANCTUM_STATEFUL_DOMAINS, SANCTUM_TOKEN_PREFIX=kh_
TRUSTED_PROXIES
```

**Frontend (`.env.local`)**
```
NEXT_PUBLIC_API_URL, NEXT_PUBLIC_MAPBOX_TOKEN
NEXT_PUBLIC_CLERK_PUBLISHABLE_KEY, CLERK_SECRET_KEY
NEXT_PUBLIC_CLERK_SIGN_IN_URL, NEXT_PUBLIC_CLERK_SIGN_UP_URL
NEXT_PUBLIC_CLERK_SIGN_IN_FALLBACK_REDIRECT_URL, NEXT_PUBLIC_CLERK_SIGN_UP_FALLBACK_REDIRECT_URL
```

**Mobile (`.env`)**
```
EXPO_PUBLIC_BASE_URL  (e.g. https://api.keyhome.neocraft.dev/agency)
```

---

## 3. ARCHITECTURE MAP

```
┌─────────────────────────────────────────────────────────────────────┐
│                          CLIENT LAYER                               │
│                                                                     │
│  ┌─────────────────┐  ┌──────────────────┐  ┌──────────────────┐  │
│  │  Next.js 16 Web │  │ Expo Agency App  │  │ Expo Bailleur App│  │
│  │  (Clerk Auth)   │  │ (WebView Bridge) │  │ (WebView Bridge) │  │
│  │  port 3000      │  │ → /agency panel  │  │ → /owner panel   │  │
│  └────────┬────────┘  └────────┬─────────┘  └────────┬─────────┘  │
└───────────┼────────────────────┼─────────────────────┼────────────┘
            │ HTTPS              │ HTTPS               │ HTTPS
            ▼                   ▼                     ▼
┌─────────────────────────────────────────────────────────────────────┐
│                   TRAEFIK REVERSE PROXY                             │
└─────────────────────────────┬───────────────────────────────────────┘
                              │
            ┌────────────────┬┴───────────────┐
            │                │                │
            ▼                ▼                ▼
     ┌─────────────┐  ┌─────────────┐  ┌──────────────┐
     │  Nginx Web  │  │  Nginx Web  │  │  Nginx Web   │
     │  /api/v1/*  │  │  /admin     │  │  /agency     │
     │             │  │             │  │  /owner      │
     └──────┬──────┘  └──────┬──────┘  └──────┬───────┘
            │                │                │
            └────────────────┴────────────────┘
                             │
                     ┌───────▼───────┐
                     │  PHP-FPM App  │
                     │  (Laravel 12) │
                     └───────┬───────┘
                             │
         ┌───────────────────┼───────────────────┐
         │                   │                   │
         ▼                   ▼                   ▼
  ┌─────────────┐   ┌──────────────┐   ┌─────────────────┐
  │  PostgreSQL │   │  MeiliSearch │   │     Redis        │
  │  + PostGIS  │   │   v1.10      │   │   (cache/queue)  │
  └─────────────┘   └──────────────┘   └─────────────────┘
         │
         │ External Services
         ├──► FedaPay (payments, XOF)
         ├──► Clerk (JWT auth, webhooks)
         ├──► Google / Facebook / Apple OAuth
         ├──► Sentry (error tracking)
         └──► Nightwatch (monitoring agent)
```

### Entry points
| Surface | Entry point |
|---------|-------------|
| REST API | `routes/api.php` → `public/index.php` |
| Filament Admin | `app/Providers/Filament/AdminPanelProvider.php` → `/admin` |
| Filament Agency | `app/Providers/Filament/AgencyPanelProvider.php` → `/agency` |
| Filament Bailleur | `app/Providers/Filament/BailleurPanelProvider.php` → `/owner` |
| Next.js | `keyhome-frontend-next/src/app/layout.tsx` |
| Mobile Agency | `mobile/agency/index.js` → `App.js` |
| Mobile Bailleur | `mobile/bailleur/index.js` → `App.js` |

### Data flow (web user browsing ads)
```
User opens Next.js → Clerk session verified → middleware.ts injects token
→ GET /api/v1/ads (Axios, Bearer token)
→ Laravel AdController@index (optional auth middleware)
→ Eloquent query on `ad` table (PostgreSQL)
→ MeiliSearch for full-text / Scout search endpoint
→ AdResource JSON response
→ TanStack Query caches result → React renders listing cards
```

### Authentication & authorisation model
| Layer | Mechanism |
|-------|-----------|
| Web frontend | Clerk (JWT) → exchanged for Laravel Sanctum token via `/auth/clerk/exchange` |
| Filament panels | Session-based (cookie), 2FA (TOTP + Email) |
| Mobile OAuth | Expo `expo-auth-session` → native Google OAuth → `/auth/oauth/google` |
| API authorisation | Laravel Policies per model (Ad, Agency, Payment, Subscription…) |
| Rate limiting | Throttle middleware on every sensitive route (5-60 req/min) |

---

## 4. DIRECTORY STRUCTURE

```
ImmoApp-Backend/
├── app/                     # Laravel application code
│   ├── Actions/             # Single-purpose action classes
│   ├── Console/             # Artisan commands & scheduler
│   ├── Enums/               # PHP 8.1 enums (AdStatus, UserRole, PaymentType…)
│   ├── Exceptions/          # Custom exceptions (InvalidStatusTransition)
│   ├── Filament/            # Filament resources, pages, widgets for 3 panels
│   │   ├── Admin/           # 46 resources + 9 widgets
│   │   ├── Agency/          # Agency-facing resources
│   │   ├── Bailleur/        # Owner-facing resources
│   │   ├── Exports/         # CSV/Excel export classes
│   │   └── Imports/         # Bulk import classes
│   ├── Http/
│   │   ├── Controllers/Api/V1/  # 14 REST controllers
│   │   ├── Middleware/          # 5 custom middlewares
│   │   ├── Requests/            # 15 form request validators
│   │   └── Resources/           # 11 API resources (JSON transformers)
│   ├── Mail/                # 20 Mailable classes
│   ├── Models/              # 17 Eloquent models
│   ├── Notifications/       # 4 notification classes
│   ├── Observers/           # 2 model observers
│   ├── Policies/            # 9 authorisation policies
│   ├── Providers/           # Service providers + 4 Filament panel providers
│   ├── Services/            # 8 service classes (RecommendationEngine, FedaPay…)
│   └── Swagger/             # OpenAPI annotations
├── database/
│   ├── migrations/          # 57 migration files (Aug 2025 – Feb 2026)
│   ├── seeders/             # 10 seeders
│   └── factories/           # 10 model factories
├── routes/
│   ├── api.php              # All API routes (v1), 250 lines
│   └── web.php              # Filament + health-check routes
├── resources/
│   └── js/filament-native-bridge.js  # JS bridge injected in WebViews
├── keyhome-frontend-next/   # Next.js 16 web frontend
│   └── src/
│       ├── app/             # Next.js App Router pages
│       ├── components/      # Reusable React components
│       ├── services/        # API client services (Axios)
│       ├── lib/             # Utilities and helpers
│       ├── providers/       # React context providers
│       └── types/           # TypeScript type definitions
├── mobile/
│   ├── agency/              # Expo app for agency panel
│   └── bailleur/            # Expo app for bailleur panel
├── tests/
│   ├── Feature/             # 25 feature/integration tests
│   └── Unit/                # 3 unit tests
├── .docker/                 # Docker configs (nginx, monitoring)
├── .gitlab-ci.yml           # CI/CD pipeline (lint, test, build, deploy)
├── docker-compose.yml       # 7 core services + 6 monitoring
├── Dockerfile               # PHP-FPM image
└── README.md                # Original project readme
```

### Most important files
| File | Purpose |
|------|---------|
| `app/Models/Ad.php` | Core listing model: stateful FSM, boost, geo, media, Scout indexing |
| `app/Models/User.php` | Multi-role user: Filament contracts, 2FA, OAuth, soft-delete |
| `app/Services/RecommendationEngine.php` | Weighted-scoring AI recommender with temporal decay |
| `app/Services/FedaPayService.php` | Payment gateway abstraction (unlock + subscription) |
| `app/Services/SubscriptionService.php` | Agency subscription lifecycle |
| `routes/api.php` | Entire API surface, versioned, rate-limited |
| `docker-compose.yml` | Full infrastructure definition |
| `.gitlab-ci.yml` | CI/CD pipeline |
| `resources/js/filament-native-bridge.js` | JS injected into mobile WebViews |
| `keyhome-frontend-next/src/middleware.ts` | Next.js auth middleware (Clerk) |

---

## 5. DATABASE & DATA MODEL

**ORM:** Eloquent (Laravel)  
**Driver:** PostgreSQL 15 + PostGIS 3.3  
**Total migrations:** 57  
**Strategy:** Sequential timestamped migrations, no down() reversal on late migrations

### Tables (17 core + system)

| Table | Key columns | Relations |
|-------|-------------|-----------|
| `users` | id(uuid), firstname, lastname, email, role, type, agency_id, clerk_id, google_id… | → agencies, ads, payments, reviews |
| `ad` | id(uuid), title, slug, price, surface_area, bedrooms, bathrooms, location(Point), status, is_boosted… | → users, quarters, ad_type, media |
| `ad_type` | id, name | ← ads |
| `city` | id, name | ← quarters, users |
| `quarter` | id, name, city_id | ← ads |
| `payments` | id(uuid), user_id, ad_id, amount, type(UNLOCK/SUBSCRIPTION), status, fedapay_transaction_id | → users, ads |
| `invoices` | id, payment_id, amount, issued_at | → payments |
| `subscriptions` | id, agency_id, plan_id, status, billing_period, starts_at, ends_at | → agencies, plans |
| `subscription_plans` | id, name, price_monthly, price_yearly, features(json) | ← subscriptions |
| `agencies` | id, name, description, logo | ← users, ads |
| `reviews` | id, user_id, ad_id, rating, comment | → users, ads |
| `ad_interactions` | id, user_id, ad_id, type(view/favorite/share…), created_at | → users, ads |
| `unlocked_ads` | id, user_id, ad_id | → users, ads |
| `property_attributes` | id, name, label, icon | (global reference table) |
| `settings` | key, value | (key-value store) |
| `activity_log` | id, log_name, description, subject_type, causer_type… | (Spatie activitylog) |
| `media` | id, model_type, model_id, collection_name, file_name… | (Spatie MediaLibrary) |
| `notifications` | id, type, notifiable_id, data, read_at | (Laravel notifications) |
| `personal_access_tokens` | id, tokenable_id, name, token, abilities | (Sanctum) |
| `telescope_entries` | (Laravel Telescope debug) | — |
| `pulse_*` | (Laravel Pulse metrics) | — |
| `imports` / `exports` | (Filament import/export jobs) | — |
| `socialite_users` | user_id, provider, provider_user_id | → users |
| `cache` / `jobs` / `failed_jobs` | Laravel system tables | — |

### Notable indexes & constraints (from audit migration)
- Unique index on `ad_interactions(user_id, ad_id, type)` for deduplication
- Composite index on `ad(status, is_visible, available_from, available_to)` for filtered queries
- PostGIS spatial index on `ad.location`
- Soft-delete indexes on `users` and `ad`

---

## 6. API SURFACE

**Base URL:** `/api/v1`  
**Auth:** Bearer token (Sanctum) via `Authorization: Bearer kh_*`  
**Total endpoints:** ~55

### Authentication (`/auth`)
| Method | Path | Auth | Description |
|--------|------|------|-------------|
| POST | `/auth/registerCustomer` | No | Register a customer account |
| POST | `/auth/registerAgent` | No | Register a landlord/agency agent |
| POST | `/auth/login` | No | Email/password login → Sanctum token |
| POST | `/auth/logout` | Yes | Revoke current token |
| POST | `/auth/refresh` | Yes | Refresh token |
| GET | `/auth/me` | Yes | Authenticated user profile |
| POST | `/auth/forgot-password` | No | Send password reset email |
| POST | `/auth/reset-password` | No | Reset password with token |
| GET | `/auth/email/verify/{id}/{hash}` | No | Verify email address |
| POST | `/auth/email/resend` | Yes | Resend verification email |
| POST | `/auth/update-password` | Yes | Change password |
| POST | `/auth/clerk/exchange` | No | Exchange Clerk JWT for Sanctum token |
| POST | `/auth/clerk/verify-otp` | No | Verify Clerk OTP |
| POST | `/auth/clerk/complete-profile` | No | Complete profile after Clerk signup |
| POST | `/auth/oauth/{provider}` | No | OAuth token exchange (google/facebook/apple) |
| GET | `/auth/oauth/{provider}/redirect` | No | OAuth redirect |
| GET | `/auth/oauth/{provider}/callback` | No | OAuth callback |
| POST | `/auth/oauth/{provider}/link` | Yes | Link OAuth provider to account |
| DELETE | `/auth/oauth/{provider}/unlink` | Yes | Unlink OAuth provider |

### Ads
| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/ads` | Optional | List all available ads |
| GET | `/ads/search` | Optional | Full-text search (MeiliSearch) |
| GET | `/ads/autocomplete` | Optional | Search autocomplete |
| GET | `/ads/facets` | Optional | Filter facets |
| GET | `/ads/nearby` | Optional | Geo-search by coordinates |
| GET | `/ads/{id}` | Optional | Single ad detail |
| POST | `/ads` | Yes | Create new ad |
| PUT | `/ads/{ad}` | Yes | Update ad |
| DELETE | `/ads/{id}` | Yes | Soft-delete ad |
| GET | `/ads/{user}/nearby` | Yes | User's nearby ads |
| POST | `/ads/{ad}/toggle-visibility` | Yes | Show/hide ad |
| POST | `/ads/{ad}/set-status` | Yes | Change ad status |
| POST | `/ads/{ad}/set-availability` | Yes | Set availability dates |

### Interactions & Analytics
| Method | Path | Auth | Description |
|--------|------|------|-------------|
| POST | `/ads/{ad}/view` | Yes | Track view |
| POST | `/ads/{ad}/favorite` | Yes | Toggle favorite |
| POST | `/ads/{ad}/impression` | Yes | Track impression |
| POST | `/ads/{ad}/share` | Yes | Track share |
| POST | `/ads/{ad}/contact-click` | Yes | Track contact click |
| POST | `/ads/{ad}/phone-click` | Yes | Track phone click |
| GET | `/my/favorites` | Yes | User's favorited ads |
| GET | `/my/unlocked-ads` | Yes | Ads unlocked by user |
| GET | `/my/ads/analytics` | Yes | Overall analytics dashboard |
| GET | `/my/ads/{ad}/analytics` | Yes | Per-ad analytics |

### Payments & Subscriptions
| Method | Path | Auth | Description |
|--------|------|------|-------------|
| POST | `/payments/initialize/{ad}` | Yes | Create FedaPay unlock transaction |
| POST | `/payments/verify/{ad}` | Yes | Verify unlock payment |
| POST | `/payments/webhook` | No | FedaPay webhook receiver |
| GET | `/payments/callback` | No | FedaPay redirect callback |
| GET | `/payments/unlock-price` | No | Current unlock price (from settings) |
| GET | `/subscriptions/plans` | No | List subscription plans |
| GET | `/subscriptions/current` | Yes | Agency's current subscription |
| POST | `/subscriptions/subscribe` | Yes | Subscribe to a plan |
| POST | `/subscriptions/cancel` | Yes | Cancel subscription |
| GET | `/subscriptions/history` | Yes | Subscription history |

### Other endpoints
| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/recommendations` | Yes | Personalised AI recommendations |
| GET | `/cities` | No | List cities |
| GET | `/quarters` | No | List quarters |
| GET | `/agencies` | No | List agencies |
| GET | `/users` | Yes | List users (admin) |
| GET | `/notifications` | Yes | User notifications |
| POST | `/notifications/read-all` | Yes | Mark all notifications read |
| GET | `/ads/{ad}/reviews` | No | Ad reviews |
| POST | `/reviews` | Yes | Submit a review |
| GET | `/property-attributes` | No | Global property attribute list |
| POST | `/clerk/webhook` | No | Clerk webhook handler |

---

## 7. FRONTEND STRUCTURE

### Next.js (`/keyhome-frontend-next/src`)

#### Pages (App Router)
| Route | Page | Auth required |
|-------|------|---------------|
| `/` | Landing / redirect | No |
| `/login` | Login with Clerk | No |
| `/register` | Registration with Clerk | No |
| `/forgot-password` | Password reset request | No |
| `/reset-password` | New password form | No |
| `/verify-email` | Email verification gate | No |
| `/verify-otp` | OTP verification | No |
| `/complete-profile` | Post-OAuth profile form | No |
| `/auth/callback` | Clerk SSO callback | No |
| `/sso-callback` | Generic SSO callback | No |
| `/home` | Feed & recommendations | Yes |
| `/ads/[id]/[slug]` | Ad detail, contact, reviews | Yes |
| `/search` | Full-text search results | Yes |
| `/nearby` | Map-based geo-search | Yes |
| `/profile` | User profile management | Yes |
| `/payments` | Payment history | Yes |
| `/payment-success` | Post-payment confirmation | Yes |
| `/conditions` | Terms of service | No |
| `/confidentialite` | Privacy policy | No |

#### State management
- **Server state:** TanStack React Query (all API calls, caching, invalidation)
- **Auth state:** Clerk (`useUser`, `useAuth` hooks)
- **Form state:** React Hook Form + Zod validation
- **Local UI state:** `useState` / `useReducer` (no Redux/Zustand)

#### Key components (`/src/components`)
- `ads/` — Ad cards, ad detail, ad list
- `auth/` — Login/register forms, protected route wrapper
- `layout/` — Navbar, sidebar, footer
- `maps/` — Mapbox map with geo-search
- `reviews/` — Review list and submission form
- `ui/` — Design system primitives (buttons, inputs…)
- `ErrorBoundary.tsx` — Global React error boundary

#### Styling
- **Tailwind CSS v4** (utility classes)
- **MUI v7** (component library: inputs, modals, snackbars)
- **Emotion** (CSS-in-JS, required by MUI)
- **globals.css** — global resets and custom properties

#### i18n
No i18n library installed. UI is primarily in French; no multi-language support exists yet.

### Mobile apps (`/mobile/agency` & `/mobile/bailleur`)
Both apps follow the **WebView-bridge pattern**:
- A single `App.js` renders a `react-native-webview` pointing at the corresponding Filament panel URL
- A JavaScript bridge (`INJECTED_JS`) is injected before content loads, exposing `window.KeyHomeBridge` to the web page: `pickImage`, `takePhoto`, `getLocation`, `registerPush`, `signInGoogle`
- `NativeService.js` handles incoming messages from the WebView and dispatches to native capabilities (camera, location, notifications via Expo)
- The Filament panels themselves include `resources/js/filament-native-bridge.js` and a `filament.mobile-bridge` Blade view to detect the WebView context and respond to the bridge

| Feature | Agency app | Bailleur app |
|---------|------------|--------------|
| Primary colour | Blue `#2563eb` | Green `#10b981` |
| Panel URL | `/agency` | `/owner` |
| Android back-button | ✅ | ✅ |
| Offline banner | ✅ | ✅ |
| Haptic feedback | ✅ | ✅ |
| Error/retry screen | ✅ | ✅ |
| Push notifications | ✅ (Expo) | ✅ (Expo) |

---

## 8. TESTING

**Framework:** Pest PHP v4 (backend)  
**Frontend tests:** None (no Jest/Vitest/Playwright configured)  
**Mobile tests:** None

### Backend test inventory
| Test file | Type | Coverage area |
|-----------|------|---------------|
| `AuthTest.php` | Feature | Login, logout, token |
| `AuthEndpointsTest.php` | Feature | Full auth endpoint suite |
| `OAuthAuthenticationTest.php` | Feature | Google/Facebook/Apple + Clerk OAuth |
| `ClerkExchangeTest.php` | Feature | Clerk JWT → Sanctum exchange |
| `EmailVerificationFlowTest.php` | Feature | Email verify lifecycle |
| `PasswordResetTest.php` | Feature | Forgot/reset password |
| `AdCrudTest.php` | Feature | Create/read/update/delete ads |
| `AdListTest.php` | Feature | Ad listing, filters |
| `AdNearbyTest.php` | Feature | Geo-search |
| `AdStatusTransitionTest.php` | Feature | FSM transitions validation |
| `AdPolicyTest.php` | Feature | Authorization policies |
| `AdAnalyticsTest.php` | Feature | Dashboard analytics |
| `PaymentTest.php` | Feature | FedaPay initialize/webhook |
| `PaymentFlowTest.php` | Feature | End-to-end payment flow |
| `SubscriptionTest.php` | Feature | Subscribe, cancel, history |
| `RecommendationTest.php` | Feature | Cold-start + personalized |
| `BailleurIsolationTest.php` | Feature | Tenant data isolation |
| `CriticalSecurityTest.php` | Feature | Auth bypass, SQL injection… |
| `SecurityTest.php` | Feature | Rate limiting, CSRF |
| `PerformanceTest.php` | Feature | Query count / N+1 |
| `MailTemplatesTest.php` | Feature | Email rendering |
| `MfaConfigurationTest.php` | Feature | 2FA setup |
| `SpaAuthenticationTest.php` | Feature | Clerk SPA token flow |
| `CrudEndpointsTest.php` | Feature | Generic CRUD coverage |
| `ExampleTest.php` | Feature | Health check |
| `Unit/` (3 files) | Unit | Model logic, helpers |

**Total tests:** ~28 files, estimated 150–200 assertions  
**Estimated coverage:** ~45–55% (strong on auth + payments, weak on Filament UI + frontend)

### Most critical untested areas
1. Filament panels (no UI/browser tests)
2. Next.js frontend (zero tests)
3. Mobile WebView bridge logic
4. FedaPay webhook signature verification
5. MeiliSearch indexing sync

---

## 9. CODE QUALITY

### Backend
- **Strict types:** `declare(strict_types=1)` on every file ✅
- **PHPStan level:** Configured via `phpstan.neon` (Larastan ~level 5-6)
- **Formatting:** PHP Pint (PSR-12 + Laravel preset) — CI enforced
- **Refactoring:** Rector with `driftingly/rector-laravel` rules
- **TODOs:** A few `// TODO` comments in Filament resources (non-critical)
- **Console.log equivalent:** `\Log::error()` used appropriately; no stray `dd()` detected
- **Quality rating: 7.5/10** — Excellent architecture and strict types; some inline route closures in `api.php` break SRP

### Frontend (Next.js)
- **TypeScript:** Enabled (`tsconfig.json`); likely some implicit `any` types in service files
- **ESLint:** Configured via `eslint.config.mjs` (next/recommended)
- **No test coverage at all** — significant risk
- **Quality rating: 6/10** — Good component structure, but no tests and potentially inconsistent typing

### Mobile
- **No TypeScript** (pure JavaScript `.js`)
- `console.warn/error` used intentionally for WebView events (acceptable)
- **Quality rating: 7/10** — Clean, well-commented single-file apps; architecture is intentionally simple

---

## 10. BUILD & DEPLOY

### Local development
```bash
# Backend (from root)
composer install
php artisan migrate --seed
composer run dev   # starts php artisan serve + queue + pail + vite concurrently

# Frontend
cd keyhome-frontend-next
pnpm install
pnpm dev           # Next.js dev server on :3000

# Mobile (Agency)
cd mobile/agency
npm install
npx expo start --ios   # or --android
```

### Docker (production)
```bash
docker compose up -d                        # Core stack (7 services)
docker compose --profile monitoring up -d   # + Prometheus/Grafana
docker compose --profile debug up pgadmin   # + PgAdmin
```

**Services:**
| Container | Image | Role |
|-----------|-------|------|
| `keyhome-backend` | Custom PHP-FPM | Laravel app |
| `keyhome-worker` | Same image | Queue worker (emails, notifications) |
| `keyhome-web` | nginx:alpine | Web server |
| `keyhome-db` | postgis/postgis:15-3.3-alpine | Database |
| `keyhome-redis` | redis:alpine | Cache + queues |
| `keyhome-meilisearch` | getmeili/meilisearch:v1.10 | Search engine |
| `keyhome-nightwatch-agent` | laravelphp/nightwatch-agent | Monitoring agent |

### CI/CD (GitLab CI — `.gitlab-ci.yml`)
**Stages:** `lint → test → build → deploy`
- Lint: PHP Pint + PHPStan
- Test: Pest with PostgreSQL service
- Build: Docker image build + push to registry
- Deploy: SSH deploy to VPS with `docker compose pull && up -d`

### Environment requirements
- PHP 8.4+ (Docker: custom Dockerfile)
- Node.js 20+ (Next.js build)
- PostgreSQL 15 + PostGIS 3.3
- Redis (any recent version)
- MeiliSearch v1.10

---

## 11. KEY RISKS & TECHNICAL DEBT

| # | Issue | Severity | Description | Fix |
|---|-------|----------|-------------|-----|
| 1 | **Single payment gateway** | 🔴 Critical | Only FedaPay; no MTN/Orange Money fallback | Abstract `PaymentGatewayInterface`; add MTN MoMo adapter |
| 2 | **No frontend tests** | 🔴 Critical | Next.js has zero tests; regressions undetectable | Add Vitest unit tests + Playwright E2E |
| 3 | **No mobile tests** | 🔴 Critical | Expo apps have zero tests | Detox or Jest + RNTL |
| 4 | **Local media storage** | 🟠 High | Images stored on Docker volume; not CDN-delivered | Migrate to S3-compatible + Cloudflare R2 |
| 5 | **No push notifications active** | 🟠 High | Expo push infrastructure exists in deps but not wired to backend | Implement FCM/APNs device token registration |
| 6 | **Inline closures in routes** | 🟡 Medium | `api.php` has inline closures for `/my/unlocked-ads` and `/payments/unlock-price` | Extract to dedicated controllers |
| 7 | **No i18n** | 🟡 Medium | UI hardcoded in French; no English support | Install `next-intl`; add `lang/en/` translations |
| 8 | **recommendation N+1 risk** | 🟡 Medium | `RecommendationEngine` scores all visible ads in-memory | Add pagination or pre-compute scores via job |
| 9 | **Monorepo sprawl** | 🟡 Medium | Backend repo contains Next.js + 2 Expo apps (4 codebases, 4 package.json) | Consider nx/turborepo or split repos |
| 10 | **No rate-limit on Filament** | 🟡 Medium | Filament panel routes lack explicit rate limits | Add throttle middleware to panel providers |
| 11 | **clerk_id added late** | 🟢 Low | Clerk integration was bolted on (migration 2026-02-21) vs designed in | Documented; no action needed |
| 12 | **No down() migrations** | 🟢 Low | Late migrations omit `down()` method | Add reverse migrations for rollback safety |

---

## 12. SUMMARY TABLE

| Metric | Value |
|--------|-------|
| **Languages** | PHP 8.4, TypeScript 5, JavaScript (ES2022+) |
| **Frameworks** | Laravel 12, Next.js 16, Expo 54, Filament 4 |
| **Database tables** | ~24 (17 domain + 7 system) |
| **Migrations** | 57 |
| **API endpoints** | ~55 |
| **Next.js pages** | 19 |
| **Filament panels** | 3 (Admin, Agency, Bailleur) |
| **Mobile apps** | 2 (Expo WebView shells) |
| **Reusable components (Next.js)** | ~15–20 |
| **Backend tests (files)** | 28 (25 Feature + 3 Unit) |
| **Frontend tests** | 0 |
| **Mobile tests** | 0 |
| **External services** | 7 (FedaPay, Clerk, Google, Facebook, Apple, Sentry, Nightwatch) |
| **Docker services** | 7 core + 6 optional monitoring |
| **CI/CD** | GitLab CI (4 stages) |
| **Backend code quality** | **7.5 / 10** |
| **Frontend code quality** | **6 / 10** |
| **Mobile code quality** | **7 / 10** |
| **Overall quality** | **7 / 10** |

---

*Documentation generated by Antigravity — Senior Software Architect Analysis | 2026-02-22*
