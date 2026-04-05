# AGENTS.md

This file is the **single source of truth** for every LLM (Cascade, Claude, Gemini, Copilot, etc.)
working on this repository. **Update it after every meaningful code change.**

## Project

KeyHome — multi-tenant real estate marketplace SaaS for francophone sub-Saharan Africa
(Cameroon / CEMAC / UEMOA, currency XOF/XAF).

**Monorepo layout:**
- `/` — Laravel 12 backend (API-first REST `/api/v1/`, Filament 4 admin panels)
- `keyhome-frontend-next/` — Next.js 16 PWA (customer-facing)
- `mobile/bailleur/` — React Native landlord app (partial)
- `mobile/agency/` — React Native agency app (partial)

**Active Git branches:**
- Backend: `preprod` (main working branch → GitLab CI → VPS)
- Frontend: `cedrickdev` (active dev branch, deploys to Vercel)
- Both also mirror to GitHub (`NeoCraftTeam/ImmoApp-Backend`, `NeoCraftTeam/keyhome-frontend-next`)

**Brand color:** `#F6475F` (crimson/pink). UI language: French only (`fr_FR` hardcoded).

## Remotes

```
Backend  → origin  = GitHub (NeoCraftTeam/ImmoApp-Backend)
         → gitlab  = GitLab (neocraft/immoapp-backend)
Frontend → origin  = GitLab (neocraft/keyhome-next)
         → github  = GitHub (NeoCraftTeam/keyhome-frontend-next)
```

## Build & Run

```bash
# Install dependencies
composer install
npm ci

# Local dev (server + queue + logs + vite in parallel)
composer run dev

# Build frontend assets
npm run build
```

## Testing

```bash
# Run all tests (Pest v4, RefreshDatabase globally)
php artisan test

# Run a single test file
php artisan test tests/Feature/AuthTest.php

# Run a single test by name
php artisan test --filter="it can login with valid credentials"

# Full quality pipeline (PHPStan + Rector + Pint + Tests + Insights)
./tests/quality.sh --fix
```

Tests require a PostgreSQL `testing` database (see `phpunit.xml`). Meilisearch driver is set to `null`
in tests. Payment gateway uses fake Flutterwave keys.

## Linting & Static Analysis

```bash
# Code style (Laravel Pint)
vendor/bin/pint              # fix
vendor/bin/pint --test       # check only

# Static analysis (PHPStan/Larastan level 5)
vendor/bin/phpstan analyse

# Rector automated refactoring
vendor/bin/rector process --dry-run
```

## Architecture

### Backend Layers
- **Controllers** (`app/Http/Controllers/Api/V1/`) — thin, `final` classes. Validation via Form Requests. No business logic.
- **Services** (`app/Services/`) — `final readonly` classes. Business logic lives here. Injected via constructor DI.
- **Models** (`app/Models/`) — Eloquent models with UUIDs, soft deletes, Spatie Media Library, Scout/Meilisearch.
- **Actions** (`app/Actions/`) — single-purpose action classes.
- **DTOs** (`app/DTOs/`) — immutable value objects (`LoginResult`, `RegistrationResult`).
- **Support** (`app/Support/`) — utility helpers (`ApiResponse`, `GeoLocation`, `PanelUrl`, `TourAssetToken`, etc.).

### Models (`app/Models/`)
`Ad`, `AdInteraction`, `AdReport`, `AdType`, `Agency`, `AnonymousSurveyAnswer`, `AnonymousSurveyResponse`,
`City`, `Document`, `EmailPreference`, `Expense`, `Invoice`, `LeaseContract`, `LeaseSignatureRequest`,
`LoginHistory`, `NewsletterCampaign`, `NewsletterSubscriber`, `NotificationPreference`, `Payment`,
`PersonalAccessToken`, `PointPackage`, `PointTransaction`, `PromoCode`, `PromoCodeUsage`,
`PropertyAttribute`, `PropertyAttributeCategory`, `PushSubscription`, `Quarter`, `QueueFailedJob`,
`Refund`, `Review`, `SearchAlert`, `SearchAlertMatch`, `Setting`, `SiteVisit`, `Subscription`,
`SubscriptionPlan`, `Survey`, `SurveyQuestion`, `SurveyResponse`, `TeamInvitation`, `Tenant`,
`TentativeReservation`, `UnlockedAd`, `User`, `Zap/Schedule`, `Zap/SchedulePeriod`.
- Concerns: `HasPropertyAttributes`, `HasVisibility`.
- Scopes: `LandlordScope`.

### Services (`app/Services/`)
- `LoginService`, `RegistrationService`, `TokenService`, `ClerkJwtService` — auth flows.
- `Payment/PaymentService` — orchestrator (Flutterwave gateway via `PaymentGatewayInterface`).
- `Payment/FlutterwavePaymentService` — Flutterwave implementation.
- `Payment/RefundService` — refund processing.
- `SubscriptionService` — plan management & renewals.
- `PointService` — credit wallet operations.
- `AdBoostService` — ad promotion logic.
- `AdReportService` — abuse reporting.
- `AgencyService` — agency CRUD.
- `ViewingScheduleService` / `ReservationService` — viewing calendar & booking.
- `LeaseContractService` — lease management.
- `TourService` / `PanoramaProcessor` — 360° virtual tours.
- `AiDescriptionEnhancer`, `AiDigestService`, `AiSearchService` — AI-powered features.
- `NaturalSearchRegexParser` — plain-language ad search.
- `RecommendationEngine` — personalised ad recommendations.
- `NeighborhoodScorecardService` — location scoring.
- `IsochroneService`, `DirectionsService` — geo/routing.
- `KeyScoreService` — proprietary property score.
- `FeatureFlagService` — runtime feature toggles.
- `AdminMetricsService` — dashboard analytics.
- `AcquisitionChannelClassifier`, `UtmAttributionService` — marketing attribution.
- `UserWelcomeService`, `WebPushService`, `NativeAppService` — notifications & mobile.
- `PropertyAttributeImportService` — bulk attribute import.
- `UserAgentParser` — browser/device detection.
- `Media/MediaPathGenerator` — Spatie media paths.

### Actions (`app/Actions/`)
- `CreateAd`, `UpdateAd`, `UnlockAd`, `HandlePostPaymentActions`, `SubmitAnonymousSurveyAction`.
- `Agency/`: `CreateAgencyAction`, `UpdateAgencyAction`, `DeleteAgencyAction`, `ListAgenciesAction`.
- `City/`: `CreateCityAction`, `UpdateCityAction`, `DeleteCityAction`, `ShowCityAction`, `ListCitiesAction`.
- `Reservation/`: `ConfirmReservationAction`.

### Payment System
- **Only gateway: Flutterwave** (FedaPay was removed). `PaymentGateway` enum has a single case `Flutterwave = 'flutterwave'`.
- Strategy pattern: `PaymentGatewayInterface` (`app/Contracts/`) implemented by `FlutterwavePaymentService`.
- `PaymentService` is the central orchestrator — injected via DI in `AppServiceProvider`. Accepts primary/fallback constructor injection for future extensibility.
- `Payment::gateway` column stored as **plain string** (not enum cast) — future gateways (Wave, Stripe, etc.) can be added without a migration.
- `Payment::isFlutterwave()` compares `$this->gateway === PaymentGateway::Flutterwave->value`.
- Webhook route: `POST /api/v1/webhooks/{gateway}` — gateway param constrained to `flutterwave` only.
- Amounts resolved server-side from `PointPackage`/`SubscriptionPlan` — never trust client amounts.
- DB locks (`lockForUpdate`) prevent double-spending on verification.
- Events: `PaymentInitiated`, `PaymentSucceeded`, `PaymentFailed`.

### Auth
- API: Laravel Sanctum (Bearer tokens + SPA session cookies).
- Filament panels: session-based with MFA (TOTP + email).
- Frontend uses Clerk for OAuth, exchanged for Sanctum tokens via `/auth/clerk/exchange`.
- Magic-link sign-in/sign-up supported.
- Social auth via Laravel Socialite (Apple provider included).
- **API rate limits**: CUSTOMER 300 req/min, AGENT with subscription 500 req/min, AGENT without subscription 300 req/min, ADMIN unlimited, guest 60 req/min.

### Filament Panels
- **Admin** (`app/Filament/Admin/`) — full platform management. Path: `/admin`.
  Provider: `app/Providers/Filament/AdminPanelProvider.php`.
  Resources: `AcquisitionUsers`, `ActivityLogs`, `AdReports`, `AdTypes`, `Ads`, `Agencies`, `Cities`,
  `NewsletterCampaigns`, `NewsletterSubscribers`, `Payments`, `PendingAds`, `PointPackages`,
  `PointTransactions`, `PromoCodes`, `PropertyAttributeCategories`, `PropertyAttributes`, `Quarters`,
  `Refunds`, `Reviews`, `SiteVisits`, `SubscriptionPlans`, `SubscriptionResource`, `Surveys`,
  `UnlockedAds`, `Users`.
- **Agency** (`app/Filament/Agency/`) — multi-tenant (Agency model). Path: `/agency`.
  Provider: `app/Providers/Filament/AgencyPanelProvider.php`.
  Resources: `Ads`, `Payments`, `Reviews`.
- Shared Filament components: `app/Filament/Concerns/`, `app/Filament/Forms/Components/`,
  `app/Filament/Pages/Auth/`, `app/Filament/Exports/`, `app/Filament/Imports/`.

### API Controllers (`app/Http/Controllers/Api/V1/`)
`AdAiController`, `AdAnalyticsController`, `AdController`, `AdGeoController`, `AdInteractionController`,
`AdPdfController`, `AdReportController`, `AdSearchController`, `AdStatusController`, `AdTypeController`,
`AgencyController`, `ApiMfaController`, `AuthController`, `BailleurFollowController`, `BoostController`,
`BulkAdController`, `CityController`, `ClerkAuthController`, `ClerkWebhookController`,
`CreditController`, `DirectionsController`, `DocController`, `DocumentController`,
`DuplicateAdController`, `EmailVerificationController`, `ExpenseController`, `GdprController`,
`HealthCheckController`, `InvoiceController`, `IsochroneController`, `KeyScoreController`,
`LeaseContractController`, `LoginHistoryController`, `MyAdsController`, `MyReviewsController`,
`NaturalSearchController`, `NeighborhoodScorecardController`, `NewsletterController`,
`NotificationController`, `NotificationPreferenceController`, `PasswordController`,
`PaymentController`, `PriceHeatmapController`, `PromoCodeController`, `PropertyAttributeController`,
`PublicSurveyController`, `PwaController`, `QuarterController`, `RecommendationController`,
`RefundController`, `RegistrationController`, `RentEstimatorController`, `ReviewController`,
`SearchAlertController`, `SignatureController`, `SocialAuthController`, `StatsController`,
`SubscriptionController`, `SurveyController`, `TeamController`, `TenantController`, `TourController`,
`UserController`, `UserPreferenceController`, `ViewingAvailabilityController`,
`ViewingReservationController`, `VisitTrackingController`.
Non-API controllers: `AnonymousSurveyController`, `EmailPreferenceController`, `MediaProxyController`,
`PanelSsoController`, `PwaManifestController`, `TourImageProxyController`.

### API Routes (`routes/api/`)
All prefixed `/api/v1/`: `auth.php`, `ads.php`, `payments.php`, `viewings.php`, `surveys.php`, `geo.php`.

### Middleware (`app/Http/Middleware/`)
`AddRequestId`, `CacheHeaders`, `CheckFeatureFlag`, `EnsureCorrectRoleForPanel`, `EnsureEmailIsVerified`,
`EnsureFrontendRequestsAreStateful`, `EnsureOwnerRole`, `EnsureTokenMatchesRole`, `EnsureUserIsActive`,
`LivewireLongRunningRequest`, `OptimizeWebViewResponse`, `OptionalAuth`, `PersistNativeSession`,
`RequireApiMfa`, `RequirePasswordChange`, `ResolveSanctumBearerUser`, `RoleScopedSession`,
`SanitizeInput`, `SecurityHeaders`, `VerifyCsrfToken`.

### Enums (`app/Enums/`)
`AdReportReason`, `AdReportScamReason`, `AdReportStatus`, `AdStatus`, `CancelledBy`,
`PaymentGateway`, `PaymentMethod`, `PaymentStatus`, `PaymentType`, `PointTransactionType`,
`PropertyAttribute`, `RefundStatus`, `ReservationStatus`, `SubscriptionStatus`,
`SurveyAnonymousAudience`, `TransactionType`, `UserRole`, `UserType`, `VerificationStatus`.

### Events & Listeners
- Events: `AdCreated`, `AdStatusTransitioned`, `PaymentFailed`, `PaymentInitiated`, `PaymentSucceeded`.
- Listeners: `AutoBoostNewAd`, `LogAuthenticationEvents`, `MatchSearchAlertsOnAdAvailable`,
  `NotifyAdminsOfPendingAd`, `NotifyOwnerOfStatusChange`, `SendAdminActivityEmails`,
  `SendBackupByEmailListener`, `SendWelcomeNotification`.

### Jobs (`app/Jobs/`)
`ExpireStaleReservationsJob`, `MatchSearchAlertsForAdJob`, `ProcessTourSceneJob`,
`SendNewsletterCampaignJob`, `SendNewsletterEmailJob`, `SendSearchAlertDigestJob`.

### Observers (`app/Observers/`)
`ActivityObserver`, `AdObserver`, `PaymentObserver`, `TentativeReservationObserver`, `UserObserver`.

### Console Commands (`app/Console/Commands/`)
`AnonymizeDeletedUsers`, `AutoHideStaleAds`, `BackfillTourPanoMetadata`, `CheckAdminAlerts`,
`CheckLeaseExpirations`, `CheckSubscriptionExpirations`, `CleanupStalePaymentsCommand`,
`CreateAdminCommand`, `CreateDemoUsersCommand`, `DiagnoseMailCommand`, `GenerateUtmLinkCommand`,
`HealthCheckCommand`, `MigrateAdImagesToSpatie`, `ProcessSubscriptionRenewals`, `PurgeExpiredData`,
`RecalculateQuarterPricing`, `ResetUserOnboardingCommand`, `SendEngagementEmails`, `SendMonthlyReport`,
`SendPostViewingThanks`, `SendSearchAlertDigests`, `SendTestPush`, `SyncMeilisearchSettings`,
`TestMultiTenancyFlow`, `UploadAttributesCommand`.

### Notifications & Mail
- Notifications support: database, email, web push, WhatsApp (`app/Channels/WhatsAppChannel.php`).
- ~45 Mailable classes covering auth, payments, ads, leases, subscriptions, surveys, newsletters.
- Mail concerns: `HasLocale`, `HasUnsubscribeLinks`.

### Infrastructure
- **Docker Compose** services: `app` (PHP-FPM), `worker` (queues: critical,payments,emails,default), `worker-tours` (queue: tours, timeout=900s), `nightwatch-agent`, `web` (Nginx), `db` (PostgreSQL+PostGIS), `redis`, `meilisearch`. Optional profiles: `debug` (pgadmin), `monitoring` (prometheus, grafana, node-exporter, cadvisor, postgres-exporter, redis-exporter).
- **Resource limits** (all services): `deploy.resources.limits` is set on every container to prevent OOM cascades. Reference values: `app` 1 GB/2 CPU, `worker` 512 MB/1 CPU, `worker-tours` 1 GB/2 CPU (image processing), `db` 2 GB/2 CPU, `redis` 512 MB/0.5 CPU, `meilisearch` 1 GB/1 CPU, `web` 256 MB/0.5 CPU.
- **Redis** is capped with `--maxmemory 384mb --maxmemory-policy allkeys-lru` so it never silently consumes all available RAM.
- **Storage (prod)**: Cloudflare R2 — `FILESYSTEM_DISK=r2`. Structure: `avatars/`, `agency-logos/`, `lease-contracts/`, `ads/`, `tours/`. `TOUR_STORAGE_DISK=r2`.
- **CI/CD (Backend)**: GitLab CI (`.gitlab-ci.yml`) with self-hosted runner + Container Registry — primary pipeline. GitHub Actions (`.github/workflows/`) mirrors for open-source visibility.
  - Stages: `prepare` → `quality` → `build_and_test` → `deploy` → `smoke_test` → `notify`.
  - `build_image` job: Docker multi-stage build, pushes to GitLab registry.
  - `test_suite` job: spins up PostgreSQL container, runs `php artisan test`.
  - `pg_isready` uses `-h 127.0.0.1 -p 5432` to verify TCP (not Unix socket) before test run.
- **CI/CD (Frontend)**: GitLab CI (`keyhome-frontend-next/.gitlab-ci.yml`) + Vercel (auto-deploy on push to `cedrickdev`).
  - `lint` job: `npm run lint` (ESLint). `format` job: `npm run format:check` (Prettier).
  - `typecheck` job: `npx tsc --noEmit`. `test` job: Vitest. `build` job: Next.js production build.
- **Proxy**: Traefik (HTTPS Let's Encrypt) in front of `web` service.
- **Preprod**: `docker-compose.preprod.yml` — shares prod DB/Redis/Meilisearch via external network.

### Key Packages
- `filament/filament ~4.0` + media-library plugin.
- `spatie/laravel-medialibrary ^11` — WebP conversions (thumb/medium/large).
- `spatie/laravel-activitylog ^4` — audit log.
- `spatie/laravel-backup ^10` — S3 backups.
- `clickbar/laravel-magellan ^2` — PostGIS helpers.
- `laraveljutsu/zap ^1.14` — viewing schedule engine (`app/Models/Zap/`).
- `laravel/scout + meilisearch/meilisearch-php` — full-text search.
- `laravel/sanctum ^4`, `laravel/socialite ^5`, `dutchcodingcompany/filament-socialite`.
- `laravel/pulse ^1`, `laravel/telescope ^5`, `laravel/nightwatch ^1` — observability.
- `sentry/sentry-laravel ^4` — error tracking.
- `laravel-notification-channels/webpush ^10` — push notifications.
- `darkaonline/l5-swagger ^9` — OpenAPI docs (`app/Docs/`, `app/Swagger/`).
- `flowframe/laravel-trend ^0.4` — time-series metrics.
- `barryvdh/laravel-dompdf ^3` — PDF generation.

### Key Conventions
- All PHP files use `declare(strict_types=1)`.
- Models use UUID primary keys (`HasUuids` trait).
- `Model::preventLazyLoading()` is enabled in non-production environments.
- Ad `status` is excluded from `$fillable` — use `transitionTo()` or `forceFill()`.
- Image uploads use Spatie Media Library with WebP conversions (thumb, medium, large).
- PostGIS for geolocation (`location` column on Ad/User models).
- Meilisearch for full-text search on Ads.
- Sensitive log data masked via `app/Logging/MaskSensitiveDataProcessor.php`.
- Policies live in `app/Policies/` — one per major model.
- **UUID vs slug lookups (PostgreSQL):** Never use `->where('id', $value)->orWhere('slug', $value)` directly — PostgreSQL throws `SQLSTATE[22P02]` when a non-UUID string is cast to the `uuid` column type. Always guard with a UUID format check first:
  ```php
  $isUuid = (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id);
  $query->where(fn ($q) => $isUuid ? $q->where('id', $id)->orWhere('slug', $id) : $q->where('slug', $id));
  ```
  This pattern is applied in `AdController::show()` and must be followed in any controller that accepts an `{id}` route parameter that may be either a UUID or a human-readable slug.
- **Dockerfile is multi-stage** (`Dockerfile`):
  - Stage `node-builder` (Node 20 Alpine): installs npm deps, stubs `vendor/filament/filament/resources/css/theme.css` so Vite can resolve the `@import` in `resources/css/filament/admin/theme.css` (actual Filament assets are not in Docker build context — `vendor/` is in `.dockerignore`), then runs `npm run build`.
  - Stage `stage-1` (PHP 8.x FPM Alpine): production image. **Does NOT have `shadow` package** — use Alpine busybox builtins (`deluser`/`addgroup`/`adduser`) instead of `usermod`/`groupmod` to set UID/GID for `www-data`.
  - `COPY composer.json composer.lock` precedes `RUN composer install` so the vendor layer is only rebuilt when `composer.lock` changes.
  - Static assets (`vendor:publish --tag=livewire:assets`, `filament:assets`) are baked into the image and must NOT be re-published at deploy time.
- **Deploy process** (both prod and preprod): pull image → start containers → `migrate --force` (with automatic rollback to previous image on failure) → `optimize:clear` → `bash resetFilamentLivewire.sh` (cache only) → `optimize` → `artisan up` → `l5-swagger:generate` (non-blocking, `|| true`). `composer install` is NOT run at deploy time — it must be in the Docker image.
- **`scout:import` is NOT run on every deploy.** Only `scout:sync-index-settings` runs on deploy. Full reindexing should be triggered manually or via a scheduled command when the mapping changes.
- **PHP JIT** is enabled in `.docker/php/opcache.ini` (`opcache.jit=tracing`, `opcache.jit_buffer_size=128M`). Do not disable unless debugging a JIT-specific crash.
- **`resetFilamentLivewire.sh`** only rebuilds runtime caches (config, routes, views, events, Filament components). Asset publishing has been removed from this script — it is now done at Docker build time.

## Frontend (`keyhome-frontend-next/`)

### Commands
```bash
cd keyhome-frontend-next
npm run dev           # development server
npm run build         # production build (Next.js)
npm run lint          # ESLint (zero-warning policy in CI)
npm run format        # Prettier --write
npm run format:check  # Prettier --check (used in CI)
npm run test          # Vitest unit tests
npm run test:e2e      # Playwright e2e tests
npm run typecheck     # tsc --noEmit
npm run test:coverage # Vitest + v8 coverage (no enforcement thresholds)
```

### Stack
Next.js 16, React 19, MUI v7, Tailwind v4, Clerk auth, TanStack Query v5, Framer Motion, Mapbox GL,
Photo Sphere Viewer (360° tours), Recharts, Three.js (landing page), next-intl (i18n — FR only),
Storybook, Vitest, Playwright.

### Structure
- `src/app/` — Next.js App Router pages. Key routes:
  - `(public)/` — listing pages, home, search, ad detail.
  - `(owner)/owner/` — landlord dashboard (ads, expenses, leases, viewings).
  - `credits/callback/` — Flutterwave payment return page with retry/polling logic.
  - `payment-success/` — ad unlock payment return page with extended polling.
- `src/components/` — shared UI components.
  - `ui/` — base primitives (shadcn-style + MUI hybrids).
  - `owner/` — landlord-specific components (`AdForm`, `AdFormPhotos`, `AdFormTour`, etc.).
  - `landing/` — marketing landing page components.
  - `surveys/` — survey form components (`SurveyForm`, `QuestionRenderer`).
  - `payment/` — payment history, checkout components.
- `src/services/` — 22 service files calling `/api/v1/` endpoints.
- `src/hooks/` — 16 custom hooks.
- `src/theme/` — `tokens.ts` (design tokens), `theme.ts` (MUI theme), `ownerTheme.ts` (bailleur).
- `src/types/` — TypeScript interfaces mirroring backend models.
- `src/tests/` — Vitest unit tests.
- `e2e/` — Playwright end-to-end tests.
- `.storybook/` — Storybook config. Uses `register-next-config.cjs` (CJS, `require()` intentional).

### Environment Variables
| Variable | Purpose |
|---|---|
| `NEXT_PUBLIC_API_URL` | Backend API base URL |
| `NEXT_PUBLIC_MAPBOX_TOKEN` | Mapbox GL maps |
| `NEXT_PUBLIC_CLERK_PUBLISHABLE_KEY` | Clerk auth |
| `CLERK_SECRET_KEY` | Clerk server-side |
| `NEXT_PUBLIC_VAPID_PUBLIC_KEY` | Web Push notifications |
| `NEXT_PUBLIC_OWNER_PANEL` | `next`=integrated UI, `laravel`=Filament panel |
| `NEXT_PUBLIC_MEILISEARCH_HOST` | MeiliSearch for client-side search |
| `NEXT_PUBLIC_MEILISEARCH_KEY` | MeiliSearch search-only API key |

### Frontend ESLint Rules (notable)
- `@typescript-eslint/no-explicit-any` — **error**. Never use `any`; use proper union types or `unknown`.
- `@typescript-eslint/no-require-imports` — **error**. No `require()` in TS/TSX files (CJS files are exempt with file-level `eslint-disable`).
- `react-hooks/immutability` — **error**. Cannot access or mutate refs/variables in `useCallback` closures that reference themselves (recursive pattern). Use block-level `/* eslint-disable/enable */` pairs when unavoidable.
- `react-hooks/globals` — **error**. Cannot reassign module-level variables inside a component's render body. Move to `useEffect`.
- `react-hooks/refs` — **error**. Cannot read `ref.current` during render. Store anchor elements in state instead of reading from a ref in JSX props.
- `jsx-a11y/alt-text` — **warning**. All `<img>` must have `alt`.

### Known Frontend Fixes (do not revert)
- **`src/components/ui/PhoneField.tsx`**: `anchorEl` is stored as `useState<HTMLButtonElement | null>`, updated via callback ref (`ref={setAnchorEl}`) on the trigger button. Do NOT revert to `useRef` + reading `.current` during render.
- **`src/components/ui/progressive-contact-form.tsx`**: `inputRef` is typed as `useRef<HTMLInputElement | HTMLTextAreaElement>`. Cast to `React.Ref<HTMLInputElement>` for `<Input>` and `React.Ref<HTMLTextAreaElement>` for `<Textarea>`. Never cast to `any`.
- **`src/components/surveys/QuestionRenderer.tsx`**: `value` prop is `string | number | string[] | null | undefined`. `onChange` callback is `(value: string | number | string[]) => void` — **no `null`** in the callback (MUI `Rating` null is handled internally with `?? 0`).
- **`src/components/landing/PageTransition.tsx`**: Module-level `_setActive`/`_setTarget` are assigned inside `useEffect(() => { ... })` (no dependency array) — NOT directly in render body.
- **`src/tests/components/AdCard.test.tsx`**: `import React from 'react'` at top level. The `framer-motion` mock uses this top-level import — no `require()` inside `vi.mock` factories. The `React.createElement` call casts `children as React.ReactNode` because `children` is destructured from `Record<string, unknown>` and `createElement` requires `ReactNode`.
- **`.storybook/register-next-config.cjs`**: Has `/* eslint-disable @typescript-eslint/no-require-imports */` at top — intentional CJS file.
- **`src/app/credits/callback/page.tsx`** and **`src/app/payment-success/page.tsx`**: Recursive `useCallback` patterns that assign `retryTimerRef.current = setTimeout(() => self(attempt+1), ...)` use `/* eslint-disable react-hooks/immutability */ ... /* eslint-enable */` block pairs (single-line `eslint-disable-next-line` only covers the first line of a multi-line statement).

### Onboarding Event Sequence
`AppTour close` → `kh:tour-completed` → [3 min delay] → `WelcomeModal (3 steps)` → `kh:welcome-dismissed` → `PushPrompt` → `kh:push-prompt-done` → Survey.
LocalStorage keys: `kh_tour_completed_at`, `kh:welcome-dismissed`, `APPTOUR_SHOWN_KEY`.

## Known CI/CD Gotchas

### Backend GitLab CI
- **`build_image` (Dockerfile)**: `vendor/` is in `.dockerignore`. The `node-builder` stage stubs `vendor/filament/filament/resources/css/theme.css` before `npm run build` to satisfy Vite's `@import`. Do NOT remove this stub.
- **`build_image` (Dockerfile Alpine)**: `php-fpm-alpine` does NOT include the `shadow` package. `usermod`/`groupmod` are unavailable (exit 127). Use `deluser www-data 2>/dev/null; delgroup www-data 2>/dev/null; addgroup -g 1000 -S www-data && adduser -u 1000 -D -S -H -G www-data www-data`.
- **`test_suite` (PostgreSQL)**: `pg_isready` without `-h` checks the Unix socket which becomes ready before TCP. Always use `pg_isready -U gitlab -h 127.0.0.1 -p 5432` in the wait loop.
- **`.dockerignore`**: `readme/` is ignored to prevent Docker cache invalidation from documentation commits. `vendor/` and `node_modules/` are always excluded.

### Frontend GitLab CI / Vercel
- **`format` job**: `npm run format:check` — runs Prettier check. Fix by running `npm run format` locally before pushing.
- **`lint` job**: `npm run lint` — zero errors allowed. Warnings are tolerated but minimised.
- **Vercel build**: runs `tsc --noEmit` as part of Next.js production build. TypeScript errors fail the build even if ESLint passes. Always check that interface changes don't break downstream callers.
- **`test_unit` job (coverage)**: Coverage thresholds have been **removed** from `vitest.config.ts`. The job passes/fails based on test results only. Coverage is still collected and reported (reporters: text, json, html, lcov, cobertura) as CI artifacts — do NOT re-add `thresholds` until the test suite has meaningful coverage.
