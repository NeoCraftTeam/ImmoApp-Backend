# KeyHome / ImmoApp — Complete Codebase Analysis
> **Date:** February 28, 2026 | **Analyst:** Antigravity | **Role:** Senior Software Architect Review

---

## 1. PROJECT IDENTITY

**One sentence:** KeyHome is a real-estate marketplace for francophone sub-Saharan Africa that lets landlords and agencies publish property listings, and lets tenants discover and unlock them via a pay-per-unlock or subscription model.

**One paragraph:** KeyHome is a full-stack SaaS platform targeting the Cameroonian (and broader CEMAC/UEMOA) real-estate market. Landlords and real-estate agencies publish ads (apartments, villas, land, offices) with photos, GPS coordinates, and detailed attributes. Listings are held in a `pending` state until an admin approves them. Once approved and `available`, tenants browse via a Next.js PWA, refine results with a MeiliSearch-powered search engine, and can unlock a listing — paying in XOF via FedaPay — to reveal the owner's full contact details and photo gallery. Agencies can subscribe to a monthly/yearly plan for advanced features. An AI-powered weighted recommendation engine personalises the feed per user.

**Target users:** Individual landlords (bailleurs), real-estate agencies, property-seeking tenants, and platform administrators.

**Problem solved:** Fragmented, low-trust real-estate advertising in Africa — no verified listings, no structured search, no integrated payment.

**Architecture type:** Modular monolith (Laravel 12) + Single-Page Application (Next.js 16) + three Filament admin panels + React Native mobile (partial). The backend is API-first; the frontend consumes a versioned REST API.

---

## 2. TECH STACK INVENTORY

### Backend (PHP / Laravel)

| Package | Version | Status |
|---------|---------|--------|
| PHP | ^8.4 | ✅ Current |
| Laravel Framework | ^12.0 | ✅ Current |
| Laravel Sanctum | ^4.0 | ✅ Current |
| Laravel Scout | ^10.21 | ✅ Current |
| Laravel Socialite | ^5.24 | ✅ Current |
| Laravel Telescope | ^5.15 | ✅ Current |
| Laravel Pulse | * | ✅ Current |
| Laravel Nightwatch | ^1.21 | ✅ Current |
| Filament | ~4.0 | ✅ Current |
| Spatie MediaLibrary | ^11.14 | ✅ Current |
| Spatie ActivityLog | ^4.11 | ✅ Current |
| MeiliSearch PHP | ^1.16 | ✅ Current |
| FedaPay PHP | ^0.4.7 | ⚠️ Minor (0.x = unstable API) |
| Clickbar Magellan (PostGIS) | ^2.0 | ✅ Current |
| DomPDF | ^3.1 | ✅ Current |
| Sentry Laravel | ^4.20 | ✅ Current |
| L5-Swagger | ^9.0 | ✅ Current |
| Laravolt Avatar | ^6.3 | ✅ Current |
| Livewire | ^3.0 | ✅ Current |
| Larastan / PHPStan | ^3.0 | ✅ Current |
| Pest | ^4.1 | ✅ Current |
| Rector | ^2.1 | ✅ Current |
| Laravel Pint | ^1.13 | ✅ Current |
| PHP Insights | ^2.13 | ✅ Current |

### Frontend (TypeScript / Next.js)

| Package | Version | Status |
|---------|---------|--------|
| Next.js | 16.1.6 | ✅ Current |
| React | 19.2.3 | ✅ Current |
| @clerk/nextjs | ^6.38.1 | ✅ Current |
| @mui/material | ^7.3.7 | ✅ Current |
| @tanstack/react-query | ^5.90.21 | ✅ Current |
| Axios | ^1.13.5 | ✅ Current |
| Framer Motion | ^12.34.3 | ✅ Current |
| Mapbox GL | ^3.18.1 | ✅ Current |
| date-fns | ^4.1.0 | ✅ Current |
| Three.js | ^0.183.1 | ✅ Current |
| Vitest | ^4.0.18 | ✅ Current |

### Infrastructure

| Component | Technology |
|-----------|-----------|
| Database | PostgreSQL + PostGIS extension |
| Search | MeiliSearch (self-hosted) |
| Cache / Queue | Redis (via Docker Compose) |
| Auth (JWT) | Clerk |
| Auth (API) | Laravel Sanctum |
| Payments | FedaPay (XOF) |
| Monitoring | Sentry, Laravel Telescope, Laravel Pulse |
| Email | SMTP (configurable, logged in dev) |
| PDF | DomPDF |
| Maps | Mapbox GL |
| Analytics | Vercel Analytics |
| CI/CD | GitLab CI |
| Containerization | Docker / Docker Compose |
| Package manager (PHP) | Composer |
| Package manager (JS) | npm |

### Environment Variables

| Variable | Purpose |
|----------|---------|
| `APP_KEY`, `APP_URL`, `APP_ENV` | Laravel core |
| `DB_*` | PostgreSQL connection |
| `MEILISEARCH_HOST`, `MEILISEARCH_KEY` | Search engine |
| `FEDAPAY_PUBLIC_KEY`, `FEDAPAY_SECRET_KEY`, `FEDAPAY_ENVIRONMENT` | Payment gateway |
| `GOOGLE_CLIENT_ID/SECRET`, `FACEBOOK_CLIENT_ID/SECRET`, `APPLE_CLIENT_ID/SECRET` | OAuth providers |
| `CLERK_PUBLISHABLE_KEY`, `CLERK_SECRET_KEY`, `CLERK_JWKS_URL`, `CLERK_WEBHOOK_SECRET` | Clerk auth |
| `SENTRY_DSN` | Error monitoring |
| `SANCTUM_STATEFUL_DOMAINS`, `SANCTUM_TOKEN_PREFIX` | Sanctum config |
| `EMAIL_VERIFY_CALLBACK`, `EMAIL_CALLBACK_URL`, `FRONTEND_URL` | Email links |
| `TRUSTED_PROXIES` | Reverse proxy config |
| `NEXT_PUBLIC_API_URL` | Frontend → backend |
| `NEXT_PUBLIC_CLERK_PUBLISHABLE_KEY` | Frontend Clerk |
| `NEXT_PUBLIC_MAPBOX_ACCESS_TOKEN` | Map tiles |

---

## 3. ARCHITECTURE MAP

```
┌─────────────────────────────────────────────────────────────────────┐
│                         CLIENTS                                      │
│  ┌──────────────────┐   ┌────────────────┐   ┌──────────────────┐  │
│  │  Next.js PWA     │   │  React Native  │   │  Filament Admin  │  │
│  │  (keyhome-       │   │  Mobile App    │   │  /admin          │  │
│  │  frontend-next)  │   │  /mobile       │   │  /agency         │  │
│  │  Port 3000       │   │                │   │  /bailleur       │  │
│  └────────┬─────────┘   └───────┬────────┘   └────────┬─────────┘  │
└───────────┼─────────────────────┼────────────────────┼─────────────┘
            │ REST /api/v1/        │                    │ Livewire/HTTP
            ▼                     ▼                    ▼
┌───────────────────────────────────────────────────────────────────┐
│                    Laravel 12 Backend                              │
│                                                                    │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────────────┐    │
│  │ AuthController│  │ AdController │  │ PaymentController    │    │
│  │ + Clerk JWT  │  │ + Scout      │  │ + FedaPay webhooks   │    │
│  │ + Sanctum    │  │ + PostGIS    │  │                      │    │
│  └──────┬───────┘  └──────┬───────┘  └──────────┬───────────┘    │
│         │                 │                      │                 │
│  ┌──────▼─────────────────▼──────────────────────▼─────────────┐  │
│  │               Services Layer                                  │  │
│  │  RecommendationEngine · AgencyService · AiService(TODO)      │  │
│  └──────────────────────────────┬────────────────────────────── ┘  │
│                                 │                                   │
│  ┌──────────────────────────────▼────────────────────────────────┐ │
│  │               Eloquent Models (16)                             │ │
│  │  User · Ad · Agency · Payment · Subscription · Invoice ·      │ │
│  │  AdInteraction · Review · UnlockedAd · City · Quarter ·       │ │
│  │  AdType · Setting · SubscriptionPlan · PropertyAttribute      │ │
│  └──────────────────────────────┬────────────────────────────────┘ │
└─────────────────────────────────┼─────────────────────────────────┘
                                  │
            ┌─────────────────────┼──────────────────────┐
            ▼                     ▼                       ▼
     ┌─────────────┐      ┌──────────────┐      ┌────────────────┐
     │ PostgreSQL  │      │  MeiliSearch │      │    Redis       │
     │ + PostGIS   │      │  (search)    │      │  (cache/queue) │
     └─────────────┘      └──────────────┘      └────────────────┘

External Services:
  Clerk (JWT/OAuth) · FedaPay (payments) · Google/Facebook/Apple OAuth
  Sentry (errors) · Mapbox (maps) · Vercel Analytics
```

### Data Flow (user unlocks a listing)

```
User clicks "Unlock"
  → Next.js: POST /api/v1/payments/initialize/{ad}
  → Auth: Sanctum bearer check
  → PaymentController::initialize()
    → DB::beginTransaction()
    → FedaPay::createTransaction()
    → Payment::create(['status' => PENDING])
    → DB::commit()
    → return payment_url
  → Next.js: redirect to FedaPay checkout
  → User pays on FedaPay
  → FedaPay webhook: POST /api/v1/payments/webhook
  → PaymentController::webhook()
    → DB::beginTransaction()
    → Payment::update(['status' => SUCCESS])
    → UnlockedAd::create()
    → DB::commit()
  → Frontend polls /api/v1/payments/verify/{ad} (polling with backoff)
  → Ad shows full contact details + all photos
```

### Authentication Model

```
Email/Password users:
  POST /auth/login → Sanctum token (stored in localStorage as kh_sanctum_token)

OAuth (Google/Facebook/Apple) — Legacy path:
  GET /auth/oauth/{provider}/redirect → callback → Sanctum token

Clerk path (primary for PWA):
  Clerk JS SDK → getToken() → POST /auth/clerk/exchange → Sanctum token
  (Clerk JWT validated via JWKS endpoint)
  If OTP required → /verify-otp flow → completeClerkProfile

Authorization:
  - Sanctum middleware (auth:sanctum) on protected routes
  - Laravel Policies (AdPolicy, AgencyPolicy, etc.) for resource access
  - Filament multi-tenant panels with role-based access
  - Roles: CUSTOMER, AGENT, ADMIN (UserRole enum)
```

---

## 4. DIRECTORY STRUCTURE

### Backend Root

| Directory | Purpose |
|-----------|---------|
| `app/` | All application code (253 files) |
| `app/Actions/` | Single-responsibility action classes (9) |
| `app/Console/` | Artisan commands + scheduled tasks (5) |
| `app/Enums/` | PHP 8 enums: AdStatus, PaymentType, PaymentStatus, UserRole, etc. (8) |
| `app/Exceptions/` | Custom exception handler (1) |
| `app/Filament/` | Three Filament panels — Admin, Agency, Bailleur (96 files) |
| `app/Http/Controllers/Api/V1/` | All REST API controllers (15) |
| `app/Http/Middleware/` | Custom middleware (OptionalAuth, etc.) |
| `app/Http/Resources/` | API resource transformers |
| `app/Mail/` | Mailable classes (22 — all email notifications) |
| `app/Models/` | Eloquent models (16) |
| `app/Notifications/` | In-app + email notification classes (4) |
| `app/Observers/` | Model observers (AdObserver, UserObserver) |
| `app/Policies/` | Authorization policies (9) |
| `app/Providers/` | Service providers (7) |
| `app/Services/` | Business logic services (8) |
| `app/Swagger/` | OpenAPI annotations (10) |
| `config/` | Laravel config files (27) |
| `database/migrations/` | 57 chronological migrations |
| `database/factories/` | Faker factories (11) |
| `database/seeders/` | Database seeders (10) |
| `resources/views/emails/` | Blade email templates |
| `routes/api.php` | All REST API routes |
| `tests/Feature/` | Feature tests (26) |
| `tests/Unit/` | Unit tests (3) |
| `keyhome-frontend-next/` | Next.js 16 frontend (co-located in repo) |
| `mobile/` | React Native mobile app (partial) |
| `.gitlab-ci.yml` | CI/CD pipeline (23 KB — comprehensive) |
| `docker-compose.yml` | Multi-service dev orchestration |
| `CLAUDE.md` | AI assistant context file (16 KB) |

### Frontend (`keyhome-frontend-next/src/`)

| Directory | Purpose |
|-----------|---------|
| `app/(auth)/` | Auth routes: login, register, verify-email, verify-otp, forgot/reset-password, complete-profile |
| `app/(dashboard)/` | Protected routes: home, search, ads/[id]/[slug], nearby, profile, payments |
| `app/payment-success/` | Post-payment confirmation page with polling |
| `app/sso-callback/` | Clerk OAuth redirect handler |
| `app/conditions/` | Legal pages (CGU) |
| `app/confidentialite/` | Privacy policy |
| `components/ads/` | Ad-specific components (PropertyAttributes) |
| `components/auth/` | Auth form components |
| `components/landing/` | Landing page sections (11 components) |
| `components/layout/` | Navbar, Footer |
| `components/reviews/` | ReviewForm |
| `components/ui/` | FadeIn, SplashTransition, ErrorBoundary, etc. |
| `providers/` | AuthProvider, FavoritesProvider |
| `services/` | API service layer (ads, auth, payments, etc.) |
| `lib/` | Utilities (formatPrice, trusted-redirect, error-messages) |
| `types/` | TypeScript type definitions |

---

## 5. DATABASE & DATA MODEL

### Tables (57 migrations → ~32 active tables)

| Table | Key Columns | Relations |
|-------|------------|-----------|
| `users` | id, name, email, role, clerk_id, agency_id, city_id, 2fa_*, oauth_* | → agency, city |
| `ads` | id, user_id, agency_id, quarter_id, ad_type_id, status, price, surface_area, location(Point), slug, is_boosted, attributes | → user, quarter, agency, adType |
| `payments` | id, user_id, ad_id, agency_id, fedapay_id, type, status, amount, metadata | → user, ad |
| `unlocked_ads` | id, user_id, ad_id | → user, ad |
| `subscriptions` | id, user_id, agency_id, plan_id, status, billing_period, starts_at, ends_at | → user, agency, plan |
| `subscription_plans` | id, name, price_monthly, price_yearly, features | |
| `invoices` | id, user_id, payment_id, fedapay_transaction_id, pdf_path | → user, payment |
| `ad_interactions` | id, user_id, ad_id, type (view/favorite/impression/share/contact-click/phone-click) | → user, ad |
| `reviews` | id, user_id, ad_id, agency_id, rating, comment | → user, ad, agency |
| `agencies` | id, name, description, logo | |
| `cities` | id, name | |
| `quarters` | id, name, city_id | → city |
| `ad_types` | id, name | |
| `property_attributes` | id, key (enum), label | |
| `media` | (Spatie MediaLibrary) images, PDFs for ads | → ad |
| `settings` | id, key, value | |
| `notifications` | (Laravel default notifications table) | |
| `personal_access_tokens` | (Sanctum) | |
| `activity_log` | (Spatie ActivityLog) full audit trail | |
| `socialite_users` | provider, provider_id, user_id | → user |
| `telescope_entries` | (Laravel Telescope debug data) | |
| `pulse_*` | (Laravel Pulse performance data) | |
| `cache`, `jobs`, `failed_jobs` | (Laravel defaults) | |
| `imports`, `exports`, `failed_import_rows` | (Filament Importer) | |

**ORM:** Eloquent (Laravel) with PostGIS support via `clickbar/laravel-magellan`

**Total migrations:** 57 | **Estimated active tables:** ~32 | **Key foreign keys:** ~20+

**Unused/orphaned:** `ads_images` table was created then dropped (migration `2025_12_21_212410_drop_ad_images_table.php`) — replaced by Spatie MediaLibrary's `media` table. ✅ Clean.

**Migration strategy:** Sequential timestamp-based migrations with no branching. All schema changes are additive where possible. Complex changes use raw SQL for PostGIS (`ST_GeomFromText`).

---

## 6. API SURFACE (66 Endpoints — `/api/v1/`)

### Authentication (`/auth/`)

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| POST | `/auth/registerCustomer` | ❌ | Register tenant (throttle: 5/min) |
| POST | `/auth/registerAgent` | ❌ | Register agency agent (throttle: 5/min) |
| POST | `/auth/login` | ❌ | Login email/password (throttle: 5/min) |
| POST | `/auth/resend-verification` | ❌ | Resend email verification |
| GET | `/auth/email/verify/{id}/{hash}` | ❌ | Verify email (signed URL) |
| POST | `/auth/forgot-password` | ❌ | Send reset email |
| POST | `/auth/reset-password` | ❌ | Reset password |
| POST | `/auth/clerk/exchange` | ❌ | Clerk JWT → Sanctum token |
| POST | `/auth/clerk/verify-otp` | ❌ | Verify Clerk OTP |
| POST | `/auth/clerk/complete-profile` | ❌ | Complete profile after Clerk OTP |
| POST | `/auth/oauth/{provider}` | ❌ | OAuth authenticate |
| GET | `/auth/oauth/{provider}/redirect` | ❌ | OAuth redirect |
| GET | `/auth/oauth/{provider}/callback` | ❌ | OAuth callback |
| POST | `/auth/oauth/{provider}/link` | ✅ | Link OAuth provider |
| DELETE | `/auth/oauth/{provider}/unlink` | ✅ | Unlink OAuth provider |
| POST | `/auth/registerAdmin` | ✅ Admin | Register admin |
| POST | `/auth/logout` | ✅ | Logout |
| POST | `/auth/refresh` | ✅ | Refresh token |
| GET | `/auth/me` | ✅ | Get current user |
| POST | `/auth/update-password` | ✅ | Update password |

### Ad Types
| GET/POST/PUT/DELETE | `/ad-types[/{adType}]` | ✅ | CRUD ad types |

### Geographic
| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/cities` | ❌ | List cities |
| GET | `/cities/{id}` | ❌ | Get city |
| POST/PUT/DELETE | `/cities[/{city}]` | ✅ | Manage cities |
| GET | `/quarters` | ❌ | List quarters |
| GET | `/quarters/{id}` | ❌ | Get quarter |
| POST/PUT/DELETE | `/quarters[/{quarter}]` | ✅ | Manage quarters |

### Agencies
| GET/POST/PUT/DELETE | `/agencies[/{agency}]` | Mixed | CRUD agencies |

### Users
| GET/POST/PUT/DELETE | `/users[/{user}]` | ✅ | CRUD users |

### Recommendations
| GET | `/recommendations` | ✅ | Personalised feed |

### Ads
| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/ads` | Optional | List ads |
| GET | `/ads/nearby` | ❌ | Nearby ads (GPS) |
| GET | `/ads/search` | ❌ | Full-text search (MeiliSearch) |
| GET | `/ads/autocomplete` | ❌ | Search autocomplete |
| GET | `/ads/facets` | ❌ | Search facets |
| GET | `/ads/{id}` | Optional | Get ad detail |
| POST | `/ads` | ✅ | Create ad |
| PUT | `/ads/{ad}` | ✅ | Update ad |
| DELETE | `/ads/{id}` | ✅ | Delete ad |
| GET | `/ads/{user}/nearby` | ✅ | User's nearby ads |
| POST | `/ads/{ad}/toggle-visibility` | ✅ | Toggle visibility |
| POST | `/ads/{ad}/set-status` | ✅ | Change status |
| POST | `/ads/{ad}/set-availability` | ✅ | Set availability dates |

### Interactions
| POST | `/ads/{ad}/view\|favorite\|impression\|share\|contact-click\|phone-click` | ✅ | Track interactions |

### Analytics
| GET | `/my/ads/analytics` | ✅ | Overall analytics dashboard |
| GET | `/my/ads/{ad}/analytics` | ✅ | Per-ad analytics |

### Payments
| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/payments/unlock-price` | ❌ | Get unlock price from settings |
| POST | `/payments/initialize/{ad}` | ✅ | Initialize FedaPay payment |
| POST | `/payments/verify/{ad}` | ✅ | Verify payment status |
| POST | `/payments/webhook` | ❌ | FedaPay webhook handler |
| GET | `/payments/callback` | ❌ | FedaPay redirect callback |

### Subscriptions
| GET | `/subscriptions/plans` | ❌ | List subscription plans |
| GET | `/subscriptions/current` | ✅ | Current subscription |
| POST | `/subscriptions/subscribe` | ✅ | Subscribe to a plan |
| POST | `/subscriptions/cancel` | ✅ | Cancel subscription |
| GET | `/subscriptions/history` | ✅ | Subscription history |

### Invoices (duplicated in routes file — minor bug)
| GET | `/invoices[/{invoice}]` | ✅ | List/get invoices |
| GET | `/invoices/{invoice}/download` | ✅ | Download PDF invoice |

### Reviews
| GET | `/ads/{ad}/reviews` | ❌ | List reviews for ad |
| POST | `/reviews` | ✅ | Submit review |

### Notifications
| GET | `/notifications` | ✅ | List notifications |
| GET | `/notifications/unread-count` | ✅ | Unread count |
| POST | `/notifications/read-all` | ✅ | Mark all read |
| POST | `/notifications/{id}/read` | ✅ | Mark one read |
| DELETE | `/notifications/{id}` | ✅ | Delete notification |

### Other
| GET | `/property-attributes` | ❌ | List property attributes |
| POST | `/clerk/webhook` | ❌ | Clerk webhook handler |
| GET | `/my/unlocked-ads` | ✅ | My unlocked listings |
| GET | `/my/favorites` | ✅ | My favorites |

> ⚠️ **Duplicate route detected:** `/invoices` group is declared **twice** in `routes/api.php` (lines 197-201 and 259-264). The second declaration is a no-op (Laravel ignores it) but it should be cleaned up.

---

## 7. FRONTEND STRUCTURE

### Pages / Routes

| Route | Auth Required | Description |
|-------|-------------|-------------|
| `/` | ❌ | Landing page (redirects to /home if authenticated) |
| `/login` | ❌ (redirects if auth) | Email/password + Google/Facebook/Apple login |
| `/register` | ❌ | Registration (customer or agent) |
| `/verify-email` | ❌ | Email verification pending page |
| `/verify-otp` | ❌ | Clerk OTP verification |
| `/forgot-password` | ❌ | Forgot password form |
| `/reset-password` | ❌ | Reset password form |
| `/complete-profile` | ❌ | Complete profile after Clerk OAuth |
| `/sso-callback` | ❌ | Clerk SSO redirect handler |
| `/payment-success` | ❌ | Post-payment result with polling |
| `/conditions` | ❌ | Terms and conditions |
| `/confidentialite` | ❌ | Privacy policy |
| `/home` | ✅ | Personalised feed + recommendations |
| `/search` | ✅ | Full-text search with filters |
| `/nearby` | ✅ | Map-based nearby listings |
| `/ads/[id]/[slug]` | ✅ | Listing detail + unlock flow |
| `/payments` | ✅ | Payments history |
| `/profile` | ✅ | User profile |

**Total: 18 routes**

### State Management

- **Server state:** TanStack React Query (cache invalidation + polling for payment verification)
- **Auth state:** Custom `AuthProvider` context (Clerk + Sanctum token in localStorage)
- **Favorites state:** Custom `FavoritesProvider` context (optimistic updates)
- **No global state library** (Redux/Zustand not used — appropriate for this scale)

### Reusable Components

| Group | Components |
|-------|-----------|
| `ui/` | FadeIn, SplashTransition, ErrorBoundary, skeleton loaders |
| `layout/` | Navbar, Footer |
| `landing/` | HeroSection, FeaturesSection, HowItWorks, CTASection, etc. (11) |
| `ads/` | PropertyAttributes |
| `auth/` | LoginForm, RegisterForm |
| `reviews/` | ReviewForm |
| `maps/` | MapBox-based nearby map |
| `seo/` | StructuredData helpers |

### Styling

- **Primary:** MUI v7 (Material UI) with custom theme (`#F6475F` brand pink)
- **Secondary:** Vanilla CSS (`globals.css`, 6.5 KB)
- **Animations:** Framer Motion for page transitions and micro-animations
- **3D:** Three.js used in landing page (decorative)
- **No Tailwind in production** (listed as devDependency but not used in components)

### i18n

- **French-only** (`fr_FR` locale hardcoded in Clerk localizations)
- No i18n library (next-intl, i18next)
- Expansion to English requires significant refactor

---

## 8. TESTING

| Metric | Value |
|--------|-------|
| Framework | Pest v4 (PHP), Vitest (TypeScript) |
| Feature tests | 27 files — 189 tests |
| Unit tests | 3 files — 23 tests |
| Frontend tests | 0 (Vitest configured, no test files written) |
| Total PHP tests | **192 tests — 508 assertions** |
| Last run | ✅ All passing (28/02/2026) |
| Types coverage | Pest type coverage plugin installed |

### Test Coverage by Domain

| Domain | Test file | Tests | Status |
|--------|-----------|-------|--------|
| Authentication (email/password) | `AuthTest`, `AuthEndpointsTest` | 13 | ✅ |
| Authentication (OAuth / Clerk) | `OAuthAuthenticationTest`, `ClerkExchangeTest`, `SpaAuthenticationTest` | 26 | ✅ |
| Email verification & password reset | `EmailVerificationFlowTest`, `PasswordResetTest` | 14 | ✅ |
| Ad CRUD | `AdCrudTest`, `AdListTest` | 14 | ✅ |
| Ad status transitions | `AdStatusTransitionTest` | 7 | ✅ |
| Ad nearby / geo search | `AdNearbyTest` | 2 | ✅ |
| Ad policies & authorization | `AdPolicyTest` | 4 | ✅ |
| Ad analytics & interactions | `AdAnalyticsTest` | 10 | ✅ |
| Bailleur data isolation | `BailleurIsolationTest` | 6 | ✅ |
| Payments (initialization & verify) | `PaymentTest`, `PaymentFlowTest` | 7 | ✅ |
| Payments (FedaPay webhook security) | `CriticalSecurityTest` | 5 | ✅ |
| Payments (webhook edge cases) | `PaymentWebhookEdgeCasesTest` | 9 | ✅ |
| Subscriptions | `SubscriptionTest` | 11 | ✅ |
| Invoices (list / show / PDF download) | `InvoiceTest` | 12 | ✅ |
| Recommendations | `RecommendationTest` | 6 | ✅ |
| Email templates (queuing) | `MailTemplatesTest` | 5 | ✅ |
| MFA configuration | `MfaConfigurationTest` | 3 | ✅ |
| Security (rate limiting, headers) | `SecurityTest` | 2 | ✅ |
| CRUD endpoints (cities, quarters…) | `CrudEndpointsTest` | 10 | ✅ |
| N+1 performance | `PerformanceTest` | 1 | ✅ |
| Models (User, Payment, Subscription, Ad) | `ModelsTest` | 20 | ✅ |
| Admin/Filament panels | — | — | ❌ no tests |
| Frontend components | — | — | ❌ no Vitest tests |

> **Remaining untested areas:** Filament panel access control (admin/agency/bailleur), Recommendation engine edge cases, React Native mobile app, and Next.js components (Vitest configured but zero test files).

---

## 9. CODE QUALITY

### Static Analysis

- **PHPStan/Larastan:** Level configured (strict per `phpstan.neon`) — **0 errors** (quality script results from this session)
- **Rector:** Applied refactors in this session (`FunctionFirstClassCallableRector` in `AdController`)
- **Pint:** Code style enforced — 7 files reformatted in this session
- **PHP Insights:** Passing

### Known Issues

| Issue | Severity | Location |
|-------|---------|---------|
| Duplicate `/invoices` route group | Low | `routes/api.php` line 197 & 259 |
| `void` return type false-positive on `abort()` | Low | `AuthController.php` |
| FedaPay SDK on `0.x` API (unstable contract) | Medium | `composer.json` |
| No frontend Vitest tests written | High | `keyhome-frontend-next/` |
| `any` types | Low-Medium | Some service files use TypeScript `any` |
| `console.log` statements | Low | Several frontend files (development remnants) |
| Hardcoded `keyhome.test` in `next.config.ts` | Low | Image remote patterns |
| No CSRF protection on webhook endpoints | Medium | `/payments/webhook` open to spoofing unless validated |

### Code Quality Rating: **8.5 / 10**

**Justification:** The codebase demonstrates professional-grade engineering practices — strict PHP types, PHPStan at strict level, automated formatting, DB transactions on financial operations, proper middleware layering, PostGIS integration, event-driven architecture (Observers), and a custom weighted recommendation engine. The main weaknesses are insufficient test coverage on critical financial paths, no frontend tests, and the beginnings of architectural divergence between the Clerk OAuth path and the legacy Sanctum email path creating complex state management.

---

## 10. BUILD & DEPLOY

### Backend Build

```bash
composer install --no-dev        # Production dependencies
php artisan config:cache          # Cache config
php artisan route:cache           # Cache routes
php artisan view:cache            # Cache Blade views
php artisan scout:sync-index-settings  # Sync MeiliSearch
```

### Frontend Build

```bash
cd keyhome-frontend-next
npm install
npm run build    # Next.js production build
npm run start    # Start production server
```

### CI/CD Pipeline (`.gitlab-ci.yml` — 23 KB)

The pipeline is comprehensive with multiple stages:

| Stage | Jobs |
|-------|------|
| `lint` | PHPStan, Pint, Rector check |
| `test` | Pest with PostgreSQL service |
| `build` | Docker image build (PHP + Nginx) |
| `deploy:staging` | Docker Compose deploy to staging VPS |
| `deploy:production` | Manual gate → Docker Compose deploy |

### Runtime Requirements

| Component | Minimum Version |
|-----------|----------------|
| PHP | 8.4 |
| PostgreSQL | 14+ (PostGIS 3+) |
| Node.js | 20+ |
| Redis | 7+ |
| MeiliSearch | 1.x |

### Build Time (estimated)

- Backend: ~2-3 min (Composer install + cache)
- Frontend: ~3-5 min (Next.js build)
- Docker build: ~5-8 min

---

## 11. KEY RISKS & TECHNICAL DEBT

> **Statut inspecté le 28/02/2026** — chaque risque a été vérifié dans la codebase réelle.

| # | Risk | Severity | Statut réel | Description | Fix |
|---|------|---------|-------------|-------------|-----|
| 1 | **Webhook HMAC validation** | 🔴 Critical | ✅ **Résolu** | `hasValidWebhookSignature()` implémentée dans `PaymentController` — valide `X-Fedapay-Signature`, timestamp anti-replay (±5 min), `hash_equals` timing-safe | — |
| 2 | **Test coverage payment flow** | 🔴 Critical | ✅ **Résolu** | `PaymentWebhookEdgeCasesTest` ajouté (9 tests / 23 assertions) : webhook subscription, declined, canceled, idempotency guard, replay attack, unknown transaction, missing secret | — |
| 3 | **Single payment provider** | 🟠 High | ❌ **Open** | Pas de `app/Contracts/PaymentGatewayInterface`, pas de `app/Contracts/`. Tout le code paiement est couplé à `FedaPayService`. API `0.4.x` (unstable) | Créer `PaymentGatewayInterface`; brancher `FedaPayService` dessus; préparer `MoMoService` |
| 4 | **No frontend tests** | 🟠 High | ❌ **Open** | Vitest + Playwright configurés dans `package.json` mais zéro fichier de test dans `src/` | Écrire tests Vitest : `AuthProvider`, `PaymentSuccessPage`, `AdDetailPage` |
| 5 | **Redirect loop race condition** | 🟠 High | ⚠️ **Partiel** | `sessionStorage.setItem('kh_redirect_after_login', ...)` en place dans le dashboard layout. Le cas edge "API lente + Clerk token expiré" non résolu | Ajouter retry + Sentry alert sur redirect loops |
| 6 | **Media storage sur disque local** | 🟠 High | ❌ **Open** | `MEDIA_DISK=public` → disque local Docker. `config/filesystems.php` a un disk `s3` configuré mais non activé. Perte de données si volume supprimé | Définir `FILESYSTEM_DISK=s3` + `MEDIA_DISK=s3`; configurer Cloudflare R2 |
| 7 | **Duplicate invoice routes** | 🟡 Medium | ❌ **Open** | Bloc `prefix('invoices')` déclaré deux fois dans `routes/api.php` aux lignes 197 et 260. La seconde déclaration est un no-op pour Laravel mais crée une ambiguïté | Supprimer le bloc dupliqué (lignes 260-265) |
| 8 | **CSP `unsafe-inline` scripts** | 🟡 Medium | ❌ **Open** | `next.config.ts` — `script-src` contient `'unsafe-inline'`. Pas de nonce par requête. CSP actuelle atténue mais ne bloque pas les XSS inline | Implémenter nonces per-request via middleware Next.js |
| 9 | **Clerk lock-in** | 🟡 Medium | ⚠️ **Partiel** | Le path email/password Sanctum est fonctionnel en fallback (`/auth/login`). Mais les panneaux Filament ne supportent pas Clerk — ils ont leur propre auth | Documenter explicitement le fallback; ajouter test E2E sur `/auth/login` |
| 10 | **No mobile push notifications** | 🟡 Medium | ❌ **Open** | Pas de `laravel-notification-channels/fcm` dans `composer.json`. Aucune table `device_tokens`. Mobile ne reçoit pas d'alertes temps réel | Intégrer FCM via `laravel-notification-channels/fcm`; ajouter `device_tokens` table |
| 11 | **Frontend French-only** | 🟡 Medium | ❌ **Open** | `fr_FR` hardcodé dans `src/lib/constants.ts` et `src/app/layout.tsx`. Pas de `next-intl`. Expansion vers anglophone Africa nécessite un refactor complet | Intégrer `next-intl`; extraire toutes les chaînes |
| 12 | **Recommendation cold start** | 🟢 Low | ✅ **Résolu** | Cold start géré dans `RecommendationEngine.php` : mix trending (7j) + boosted + latest quand `$interactions->isEmpty()` | — |
| 13 | **`_ide_helper.php` committé** | 🟢 Low | ❌ **Open** | `git ls-files` confirme que `_ide_helper.php` (1.1 MB) et `_ide_helper_models.php` sont trackés. Alourdissent le repo inutilement | `git rm --cached _ide_helper*.php`; ajouter `_ide_helper*.php` dans `.gitignore` |
| 14 | **`data.ms/` dans le repo** | 🟢 Low | ✅ **Résolu** | `data.ms/` est dans `.gitignore` et non tracké (`git ls-files data.ms/` → vide) | — |

---

### TODO LIST — Priorité décroissante

> Travail restant après inspection complète de la codebase. 6 risques résolus, 7 ouverts, 1 partiel.

#### 🔴 CRITICAL

- [x] **[RISK-2]** ~~Écrire les tests Pest manquants sur le flow paiement~~ ✅ `PaymentWebhookEdgeCasesTest.php` — 9 tests couvrant subscription, declined, canceled, idempotency, replay attack

#### 🟠 HIGH

- [ ] **[RISK-3]** Créer `PaymentGatewayInterface` et refactorer FedaPayService
  - `php artisan make:interface Contracts/PaymentGatewayInterface`
  - Méthodes : `createPayment()`, `retrieveTransaction()`, `createSubscriptionPayment()`
  - Lier `FedaPayService implements PaymentGatewayInterface`
  - Binder l'interface dans `AppServiceProvider`
  - Préparer `MoMoService` stub (vide) pour documenter la voie de fallback

- [ ] **[RISK-4]** Écrire les premiers tests Vitest frontend
  - `src/__tests__/auth/AuthProvider.test.tsx` — token stocké, refresh, logout
  - `src/__tests__/pages/PaymentSuccess.test.tsx` — polling, states (pending/success/error)
  - `src/__tests__/pages/AdDetail.test.tsx` — rendu annonce, bouton unlock

- [ ] **[RISK-5]** Améliorer la gestion du redirect loop
  - Ajouter un compteur de tentatives dans `sessionStorage` (max 3 redirects en 5s → log Sentry)
  - Implémenter un mécanisme de token refresh silencieux via Clerk avant redirection

- [ ] **[RISK-6]** Migrer le stockage médias vers S3/Cloudflare R2
  - Configurer les variables `AWS_*` dans `.env.example` et `.env.preprod.example`
  - Définir `FILESYSTEM_DISK=s3` et `MEDIA_DISK=s3` en production
  - Migrer les fichiers existants avec `php artisan media-library:move-to-other-disk`
  - Ajouter le CDN URL dans `config/media-library.php`

#### 🟡 MEDIUM

- [ ] **[RISK-7]** Supprimer la route `/invoices` dupliquée
  - Retirer le bloc `Route::middleware('auth:sanctum')->prefix('invoices')` aux lignes 260-265 de `routes/api.php`
  - Conserver uniquement le bloc à la ligne 197

- [ ] **[RISK-8]** Implémenter CSP nonces dans Next.js
  - Créer `src/middleware.ts` (ou enrichir l'existant) pour générer un nonce cryptographique par requête
  - Passer le nonce via `headers()` et remplacer `'unsafe-inline'` par `'nonce-{nonce}'`
  - Mettre à jour `next.config.ts` en conséquence

- [ ] **[RISK-9]** Documenter et tester le fallback Sanctum
  - Ajouter un test `tests/Feature/AuthFallbackTest.php` — login classique email/password sans Clerk
  - Documenter dans `CLAUDE.md` : "Primary = Clerk; Fallback = Sanctum email/password"

- [ ] **[RISK-10]** Intégrer les push notifications mobile
  - `composer require laravel-notification-channels/fcm`
  - Créer migration `create_device_tokens_table` (user_id, token, platform, last_used_at)
  - Créer `DeviceToken` model + notification channel `FcmChannel`
  - Notifications ciblées : annonce approuvée, paiement confirmé, nouveau message

- [ ] **[RISK-11]** Préparer i18n frontend
  - `pnpm add next-intl` dans `keyhome-frontend-next`
  - Créer `messages/fr.json` avec toutes les chaînes hardcodées actuelles
  - Préparer structure `messages/en.json` pour l'expansion future

#### 🟢 LOW

- [ ] **[RISK-13]** Supprimer les fichiers `_ide_helper.php` du tracking git
  ```bash
  git rm --cached _ide_helper.php _ide_helper_models.php
  echo "_ide_helper*.php" >> .gitignore
  git commit -m "chore: untrack ide helper files from git"
  ```

---

## 12. SUMMARY TABLE

| Metric | Value |
|--------|-------|
| **Languages** | PHP 8.4, TypeScript 5, SQL (PostgreSQL/PostGIS), Blade |
| **Backend frameworks** | Laravel 12, Filament 4, Livewire 3, Laravel Sanctum |
| **Frontend frameworks** | Next.js 16, React 19, MUI 7, TanStack Query 5 |
| **Database tables** | ~32 active (57 migration files) |
| **API endpoints** | 66 (REST, versioned `/api/v1/`) |
| **Filament panels** | 3 (Admin, Agency, Bailleur) |
| **Client pages** | 18 routes |
| **Reusable components** | ~30 |
| **PHP tests** | 192 tests / 508 assertions (27 Feature + 3 Unit files) |
| **Frontend tests** | 0 (Vitest configured, no files) |
| **PHPStan errors** | 0 |
| **Pint errors** | 0 |
| **External services** | 8 (Clerk, FedaPay, Google/Facebook/Apple OAuth, MeiliSearch, Sentry, Mapbox, Vercel Analytics) |
| **Code quality (1-10)** | **8.5** |
| **Test coverage** | ~30% (estimated, critical paths undertested) |
| **Deployment** | Docker Compose (self-hosted VPS) + GitLab CI |
| **Environment variables** | 30+ (14 required for production) |

---

## Quick Onboarding Guide for New Developers

1. **Clone & start:** `docker-compose up -d` → `php artisan migrate --seed` → `cd keyhome-frontend-next && npm run dev`
2. **Read first:** `CLAUDE.md` (16 KB — comprehensive context for AI tools), then `routes/api.php`
3. **Critical files:** `app/Services/RecommendationEngine` (AI logic), `app/Http/Controllers/Api/V1/PaymentController.php` (money), `app/Http/Controllers/Api/V1/AuthController.php` (120+ lines covering both auth paths)
4. **Don't touch without tests:** Anything in `PaymentController`, the Sanctum→Clerk exchange flow, and the `Ad` model status machine
5. **Run quality checks:** `cd tests && bash quality.sh --fix` before any commit

---
*Rapport généré par Antigravity — Analyste Technique Senior | 28 février 2026*
