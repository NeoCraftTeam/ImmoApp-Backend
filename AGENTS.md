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
`TentativeReservation`, `TrustScore`, `UnlockedAd`, `User`, `Zap/Schedule`, `Zap/SchedulePeriod`.
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
  - `AiSearchService::parse()` — multi-provider NLP text search (Groq/Llama-3.3-70B → OpenAI GPT-4o-mini → Gemini 2.0 Flash → Together → Mistral) with circuit breakers + 24h cache.
  - `AiSearchService::parseFromImage()` — vision search: GPT-4o-vision → Gemini Vision fallback. Accepts base64 + MIME type, returns same JSON structure as `parse()`. Not cached (each image is unique).
- `NaturalSearchRegexParser` — plain-language ad search (regex fallback when all LLM providers fail).
- `RecommendationEngine` — personalised ad recommendations.
- `NeighborhoodScorecardService` — location scoring.
- `IsochroneService`, `DirectionsService` — geo/routing.
- `KeyScoreService` — proprietary property score.
- `TrustScoreService` — bidirectional user trust score (tenant + landlord, 7 signals each, 0–100).
- `FeatureFlagService` — runtime feature toggles.
- `AdminMetricsService` — dashboard analytics.
- `AcquisitionChannelClassifier`, `UtmAttributionService` — marketing attribution.
- `UserWelcomeService`, `WebPushService`, `NativeAppService` — notifications & mobile.
- `RetentionPushService` — behavioral retention push notifications (5 triggers: win-back after 3d inactivity, search-alert match, price-drop on favorites ≥5 000 FCFA, viewing reminder day-before, lease expiry at 30/7 days). All frequency-capped via Redis. Command: `app:send-retention-pushes` (scheduled twiceDaily 09:00/18:00). `--dry-run` flag available.
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

### TrustScore System
- **Bidirectional trust scoring** (0–100) for both tenants and landlords, modelled after `KeyScoreService`.
- `TrustScoreService` (`app/Services/TrustScoreService.php`) — `final readonly`, injected via constructor DI.
  - **Tenant signals (7, 100 pts):** `payment_reliability`(10), `viewing_attendance`(20), `profile_completeness`(15), `reviews`(20), `account_maturity`(10), `documents`(15), `verification`(10).
  - **Landlord signals (7, 100 pts):** `ad_quality`(15, via KeyScoreService avg), `response_rate`(15), `reviews`(25), `profile_completeness`(10), `lease_completion`(15), `account_maturity`(10), `verification`(10).
- `TrustScore` model (`app/Models/TrustScore.php`) — UUID, unique on `(user_id, role_context)`, stores `score`, `tier` (enum cast), `components` (jsonb breakdown), `computed_at`.
- `TrustScoreTier` enum (`app/Enums/TrustScoreTier.php`) — 5 tiers: `NonVerifie`(0–19), `Bronze`(20–39), `Argent`(40–59), `Or`(60–79), `Platine`(80–100). French labels, Filament color helpers, hex color for frontend.
- **Consent model:** `trust_score_consent` column on users (nullable boolean). `null` = not asked, `true` = opted in, `false` = opted out. Score only computed/shown when `true`.
- **Caching:** Redis, 1-hour TTL, key format `trust_score:{userId}:{roleContext}`. Invalidated by `PaymentObserver` and `TentativeReservationObserver`.
- **API routes:**
  - `GET /api/v1/users/{user}/trust-score` — public, rate limited 60/min.
  - `GET /api/v1/my/trust-score` — auth required, returns score or consent prompt.
  - `POST /api/v1/my/trust-score/consent` — auth required, rate limited 10/min.
- `TrustScoreResource` (`app/Http/Resources/TrustScoreResource.php`) — formats score, tier, breakdown, tips.
- `RecomputeTrustScores` command — nightly cron (02:30), processes all consented users in chunks of 500.
- **Filament integration:** Badge column on `UserResource` with tier coloring, sortable score subquery, `SelectFilter` by tier.
- **Frontend components:** `TrustScoreBadge` (Chip + Popover), `TrustScoreSection` (breakdown card), `TrustScoreConsentModal` (GDPR opt-in dialog). Integrated into bailleur profile page and ad detail owner section.
- **Cold-start handling:** New users get 3–8 baseline points. Email/phone verification + profile completion bootstraps to ~25–35 (Bronze).

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
`NaturalSearchController`, `AdImageSearchController`, `NeighborhoodScorecardController`, `NewsletterController`,
`NotificationController`, `NotificationPreferenceController`, `PasswordController`,
`PaymentController`, `PriceHeatmapController`, `PromoCodeController`, `PropertyAttributeController`,
`PublicSurveyController`, `PwaController`, `QuarterController`, `RecommendationController`,
`RefundController`, `RegistrationController`, `RentEstimatorController`, `ReviewController`,
`SearchAlertController`, `SignatureController`, `SocialAuthController`, `StatsController`,
`SubscriptionController`, `SurveyController`, `TeamController`, `TenantController`, `TourController`,
`TrustScoreController`, `UserController`, `UserPreferenceController`, `ViewingAvailabilityController`,
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
`SurveyAnonymousAudience`, `TransactionType`, `TrustScoreTier`, `UserRole`, `UserType`, `VerificationStatus`.

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
`RecalculateQuarterPricing`, `RecomputeTrustScores`, `ResetUserOnboardingCommand`, `SendEngagementEmails`, `SendMonthlyReport`,
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
  - `typecheck` job: `npx tsc --noEmit`. `test:unit` job: Vitest (no coverage thresholds).
  - **`cedrickdev` branch**: `build:check` (`npm run build`) runs as quality gate. Vercel deploys the preview automatically — NO `build:preprod` or `deploy:preprod` jobs in CI.
  - **`main` branch**: `build:prod` (vercel build --prod) → `deploy:production` (vercel deploy --prebuilt). Requires `VERCEL_TOKEN`, `VERCEL_ORG_ID`, `VERCEL_PROJECT_ID` GitLab CI variables.
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
  - `trust/` — TrustScore components (`TrustScoreBadge`, `TrustScoreSection`, `TrustScoreConsentModal`).
- `src/services/` — 23 service files calling `/api/v1/` endpoints.
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
- **Logout re-authentication bug (fixed)**: Three root causes combined to log users back in after logout — (1) `clerkExchangeDoneRef.current = false` was set at logout start, allowing the auth effect to re-run the Clerk JWT exchange while `signOut()` was still in-flight; (2) `signOut({ redirectUrl })` caused Clerk to navigate concurrently with our `window.location.replace`, creating a timing race; (3) the 1.2 s Clerk sign-out fallback was too short for mobile connections. Fix: removed the ref reset from logout, call `signOut()` without `redirectUrl`, increased fallback to 4 s, changed default redirect from `/home` (protected) to `/login` (public), and always use `window.location.replace` (hard nav) for both Clerk and non-Clerk paths so the React tree is fully torn down. Do NOT re-introduce `clerkExchangeDoneRef.current = false` inside `logout()` — the flag resets naturally in the `!isSignedIn` effect branch when Clerk propagates the sign-out.
- **Logout localStorage DEVICE_KEYS (do not remove)**: `logout()` does `localStorage.clear()` but MUST snapshot and restore `DEVICE_KEYS = ['keyhome_cookie_consent_v1', 'kh_tour_completed_at', 'kh:welcome-dismissed', 'kh:push-prompt-done', 'APPTOUR_SHOWN_KEY']` before/after. Removing this guard re-introduces two bugs: (a) cookie consent banner re-appears after every logout (GDPR UX violation), (b) onboarding tour/welcome modal re-shows to users who already completed them.
- **`StickyPropertyBar` price hidden by buttons (fixed)**: When both `whatsappUrl` + `phoneUrl` are provided, the bar now uses `flexDirection: 'column'` (price row then full-width buttons row). Previously both were in a single flex row, squeezing the price to ~35% width on narrow phones. Do NOT revert to a single-row layout when `hasDirectButtons = true`.
- **`AdDetailClient` favorite button label**: Both mobile and desktop action bar buttons now show `'Favoris'` permanently. The bookmark icon conveys saved/unsaved state; the text label is always "Favoris".
- **`NeighborhoodScorecard` mobile overflow (fixed)**: Inner info `Box` beside the score ring had no `flex: 1` / `minWidth: 0`, causing the status `Chip` to overflow the card width. Fix: `Box sx={{ flex: 1, minWidth: 0 }}`, `maxWidth: '100%'` + `textOverflow: 'ellipsis'` on both `degraded` and `unavailable` chips. Shortened unavailable chip label to "Données OSM indisponibles" to fit small screens.
- **`ReviewForm` reviews not appearing after submission (fixed)**: Root cause: `ReviewForm` called `queryClient.invalidateQueries({ queryKey: ['ad', adId] })` using the ad's UUID, but the actual ad query key in `AdDetailClient` is `['ad', adSlug, isAuthenticated]` (slug + auth state). UUID ≠ slug → invalidation was a no-op → reviews never re-fetched. Fix: Added `onSuccess?: () => void` prop to `ReviewForm`; `AdDetailClient` passes `onSuccess={() => queryClient.invalidateQueries({ queryKey: ['ad', adSlug, isAuthenticated] })}`. Do NOT change the `ReviewForm`'s internal invalidation back to `adId` — it will break again.
- **`src/components/ui/PageBreadcrumbs.tsx`**: `showBack` prop defaults to `false`. The mobile/PWA nav bar handles back navigation; callers that genuinely need a back button can pass `showBack={true}`.
- **`src/app/ads/[slug]/AdDetailClient.tsx`**: The floating back `IconButton` overlaid on the mobile image hero was removed (nav bar handles it). The `1/N` photo counter was moved from `bottom: 16` → `bottom: 56` so it clears the action button row below the image.
- **`src/app/immobilier/[ville]/page.tsx`**: The `fetch()` that loads live ads at build time MUST include `signal: AbortSignal.timeout(5000)`. Without it, when the API is unreachable on CI, each city page hangs for the full 60-second Next.js worker timeout (8 cities × 3 attempts = 24 timeouts → build failure). The `try/catch` already handles the abort gracefully — the page renders with `ads = []` and full SEO text.


- **`src/components/ui/PhoneField.tsx`**: `anchorEl` is stored as `useState<HTMLButtonElement | null>`, updated via callback ref (`ref={setAnchorEl}`) on the trigger button. Do NOT revert to `useRef` + reading `.current` during render.
- **`src/components/ui/progressive-contact-form.tsx`**: `inputRef` is typed as `useRef<HTMLInputElement | HTMLTextAreaElement>`. Cast to `React.Ref<HTMLInputElement>` for `<Input>` and `React.Ref<HTMLTextAreaElement>` for `<Textarea>`. Never cast to `any`.
- **`src/components/surveys/QuestionRenderer.tsx`**: `value` prop is `string | number | string[] | null | undefined`. `onChange` callback is `(value: string | number | string[]) => void` — **no `null`** in the callback (MUI `Rating` null is handled internally with `?? 0`).
- **`src/components/landing/PageTransition.tsx`**: Module-level `_setActive`/`_setTarget` are assigned inside `useEffect(() => { ... })` (no dependency array) — NOT directly in render body.
- **`src/tests/components/AdCard.test.tsx`**: `import React from 'react'` at top level. The `framer-motion` mock uses this top-level import — no `require()` inside `vi.mock` factories. The `React.createElement` call casts `children as React.ReactNode` because `children` is destructured from `Record<string, unknown>` and `createElement` requires `ReactNode`.
- **`.storybook/register-next-config.cjs`**: Has `/* eslint-disable @typescript-eslint/no-require-imports */` at top — intentional CJS file.
- **`src/app/credits/callback/page.tsx`** and **`src/app/payment-success/page.tsx`**: Recursive `useCallback` patterns that assign `retryTimerRef.current = setTimeout(() => self(attempt+1), ...)` use `/* eslint-disable react-hooks/immutability */ ... /* eslint-enable */` block pairs (single-line `eslint-disable-next-line` only covers the first line of a multi-line statement).

### Frontend Performance Optimizations — Round 2 (Full-App Audit)
Applied after a comprehensive scan of all major frontend features:

- **`AdCard`** — wrapped in `memo()`. **Most critical fix**: rendered 20–200+ times per page; any parent state change (pagination, category, snackbar) was triggering a full re-render of every card. Also wrapped `handleToggleFavorite` and `handleCardClick` in `useCallback`.
- **`ComparatorProvider`** — context value was a new object on every render (no `useMemo`). Every `AdCard` subscribes to this context for `isInComparator`; toggling `isOpen` / `drawerMode` / `maxReached` was causing all cards to re-render. Fixed with `useMemo` on the context value object.
- **`home/page.tsx`** — hero city search `Autocomplete` fired `citiesService.list` on every keystroke (no debounce). Added 300 ms `debouncedCityInput` debounce. Wrapped `handleCitySelect`, `handleIntentChoice`, `handleCategoryChange`, `handlePageChange` in `useCallback`.
- **`search/page.tsx` (1 988-line file)** — city search Autocomplete had no debounce (same pattern). `activeFilterCount` and `sortLabel` were recomputed on every render (all filter state changes). `clearFilters` created a new function every render. Fixed: 300 ms debounce on `debouncedCityInput`, `useMemo` for `activeFilterCount` and `sortLabel`, `useCallback` for `clearFilters`.
- **`DashboardLayout`** — large async `onPostponed` callback was inlined in JSX and recreated on every render, causing `SurveyPromptOrBanner` to re-render. Extracted to `handleSurveyPostponed` with `useCallback([activeSurvey?.id, user, refreshUser])`.

### Frontend Performance Optimizations (Ad Creation/Edit Pages)
Applied to `src/app/(owner)/owner/ads/new/page.tsx`, `src/app/(owner)/owner/ads/[id]/page.tsx`, `src/components/owner/AdForm.tsx`, `src/components/surveys/QuestionRenderer.tsx`, `src/hooks/useAutoSave.ts`:

- **`buildAdFormData`** — hoisted to module scope in `new/page.tsx` (was recreated inside component on every render).
- **`PROFILE_STEP_ICONS`** — hoisted to module scope in `new/page.tsx` (4 JSX elements were re-allocated per render).
- **`useProfileCompleteness` steps array** — wrapped in `useMemo([user fields])` (was rebuilt on every render).
- **All `AdForm` handlers** — wrapped in `useCallback` in both `new/page.tsx` and `[id]/page.tsx` (`handleSubmit`, `handleSaveDraft`, `handleEnhance`, `handleBeforeSubmit`).
- **Hotspot updates in `[id]/page.tsx`** — changed from serial `for...of await` to `Promise.allSettled(...)` (parallelized N independent API calls).
- **`initialData` in `[id]/page.tsx`** — hoisted into `useMemo([ad])` before early returns (was recomputed on every snackbar/dialog state change).
- **`AdForm`** — wrapped in `memo()` from react. **Critical**: without this, all `useCallback` optimizations in parent pages are ineffective.
- **`QuestionRenderer`** — wrapped in `memo()` (re-rendered on every survey parent state change).
- **`useAutoSave` `hasDraft`** — converted from `localStorage.getItem()` on every render to `useState` with lazy initializer, updated only on save/`clearDraft`.
- **Profile city search debounce** — added 300 ms debounce (`debouncedProfileCityInput` state + `useEffect`) in `new/page.tsx`; query key now uses debounced value so API is not hit on every keystroke.

### PWA Dual-App Isolation (Two Separate Installable Apps)

The frontend exposes **two distinct PWA identities** that install as separate apps on mobile:

| App | Manifest | Scope | Start URL | Theme |
|---|---|---|---|---|
| **Customer** | `/manifest.json` | `/` | `/home` | `#F6475F` pink |
| **Owner / Bailleur** | `/manifest-owner.json` | `/owner/` | `/owner/dashboard` | `#0D9488` teal |

**How it works:**
- `OwnerManifestSwitch` (`src/components/owner/OwnerManifestSwitch.tsx`) — client component mounted in the `(owner)` layout. On mount it swaps the `<link rel="manifest">` href, `theme-color` meta, `apple-mobile-web-app-title`, and `apple-touch-icon` to the teal owner values. Restores originals on unmount so navigating back to the customer side is seamless.
- `OwnerPWAInstallPrompt` — owner-specific install banner (separate dismiss key `kh_owner_pwa_dismissed` from the customer banner).
- Because the owner manifest `scope` is `/owner/`, navigating outside `/owner/*` from within the installed owner PWA opens a new browser tab — keeping the two apps naturally isolated at the OS level.

**Registration role-locking (prevent cross-role signup):**
- `src/lib/register-intent.ts` exports `registerUrlHasRoleLock`, `readStoredRegisterLock`, `writeStoredRegisterLock`, `clearStoredRegisterLock` — all backed by `sessionStorage` key `kh_register_role_locked`.
- **Owner flow**: `/owner/register` writes both `'agent'` role and the lock flag, then redirects to `/register`. The register page detects the lock and replaces the `ToggleButtonGroup` with a read-only "Propriétaire / Bailleur" badge — the user cannot switch to customer.
- **Customer flow**: `/login` links to `/register?lock=1`. The register page reads `?lock=1`, writes the lock flag, strips the URL, and shows a read-only "Particulier" badge.
- **Direct access** (`/register` with no params): no lock — both roles selectable as before (desktop/browser use case).
- Lock is cleared in `handleSubmit` alongside `clearStoredRegisterAccountRole()`.

**Customer side nav:** "Devenir hôte" menu item permanently removed from `NavDrawer.tsx`.

**Production-readiness fixes – round 2 (session persistence audit):**
- `config/sanctum.php`: `expiration` `1440` → `43200` (30 days). At 24 h, users who leave the PWA open in the background for a day triggered `kh:auth-expired` and were force-logged out. Tokens must outlive the typical PWA usage window.
- `config/session.php`: Hard-coded fallback default `120` → `10080` (7 days). If `SESSION_LIFETIME` is missing from `.env`, 2 h was too short for a PWA.
- `.env.example`: `SESSION_LIFETIME` `120` → `43200` (30 days) + explicit `SESSION_SAME_SITE=lax` with docs. Clerk OAuth users are unaffected (Clerk manages its own session). Email/password users are the beneficiary — their `laravel_session` cookie must survive across PWA restarts.
- `.env.preprod.example`: Same lifetime increase + `SESSION_SAME_SITE=none` + `SESSION_SECURE_COOKIE=true`. The preprod backend (`preprod-api.keyhome.neocraft.dev`) is on a **different registrable domain** from the frontend (`preprod.keyhome.app`). `SameSite=Lax` (the previous implicit default) silently blocks session cookies in cross-origin XHR requests, making email/password session auth completely non-functional after every PWA restart in preprod.

**PWA session persistence — how it works (email/password users):**
1. Login → Sanctum Bearer token returned, stored **in-memory only** (XSS-safe); Laravel session cookie also set (`withCredentials: true` in Axios).
2. PWA reopen → JS module reloads → in-memory token is `null` → `AuthProvider` calls `GET /auth/me` with no Bearer but WITH the session cookie.
3. Laravel Sanctum stateful middleware authenticates via the cookie → user is restored transparently.
4. Clerk OAuth users follow a different path: Clerk's own session persists, `isSignedIn=true`, and `AuthProvider` exchanges the Clerk JWT for a fresh Sanctum token — unaffected by any session lifetime setting.

**`SESSION_SAME_SITE` deployment rule:**
- Same registrable domain (e.g. `keyhome.app` + `api.keyhome.app`): `SESSION_SAME_SITE=lax` ✅
- Different domains (e.g. `keyhome.app` + `api.neocraft.dev`): `SESSION_SAME_SITE=none` + `SESSION_SECURE_COOKIE=true` + HTTPS required ✅

### Ad Create Stepper + API-based Draft Mode

**Architecture:**
- `AdFormWizard` — 6-step wizard (Type → Infos → Détails → Conditions → Médias → Résumé) using `AuthFlowStepper`.
- `useServerAutoSave` hook — debounced (5 s) server-side auto-save; creates draft on first dirty save, patches via `PATCH /ads/{id}/autosave` on subsequent saves.
- `AdStatusController::autosave` — lightweight text-field-only PATCH, no image processing, no status transitions.
- `AdStatusController::publish` — pessimistic-lock transition `DRAFT → PENDING` after all required fields verified.
- Draft detection on `/owner/ads/new` — queries `GET /my/ads?status=draft&per_page=5`, prompts "Reprendre" if any exist.
- Profile completeness gate — `handleBeforeSubmit` opens a right-side Drawer to complete profile; auto-save already persists the form data on the server so nothing is lost.

**Draft flow (new ad):**
1. User fills title → 5 s → auto-save creates draft (`POST /ads` + `is_draft=1`) → `onDraftCreated` → `router.push('/owner/ads/{id}')`.
2. On edit page: wizard has `draftId = ad.id`; auto-save patches via `PATCH /ads/{id}/autosave`.
3. Manual "Enregistrer le brouillon" button → `PUT /ads/{id}` with full FormData including images.
4. "Publier" on review step → `PUT /ads/{id}` update + `POST /ads/{id}/publish` → `DRAFT → PENDING`.

**Bugs fixed in ad stepper audit (6 bugs):**
- `AdFormWizard.tsx` `onCreateDraftCb`: Booleans (`has_parking=false`) were serialized as JS `String(false)='false'`, which PHP Eloquent's boolean cast treats as truthy → always stored `has_parking=true`. Fixed: `typeof v === 'boolean' ? (v ? '1' : '0') : String(v)`.
- `[id]/page.tsx` `saveDraftMutation`: Missing charges/lease/distance fields — `deposit_amount`, `minimum_lease_duration`, all `charges_*`, all `distance_*_m`, `is_boost_requested` were silently dropped on manual draft save. Fixed: added all fields, mirroring `buildAdFormData`.
- `[id]/page.tsx` `saveDraftMutation`: Double `_method=PUT` append — manual code appended `_method=PUT` then `adsService.update()` also appended it. Laravel used the first, second was noise. Fixed: removed manual append.
- `AdStatusController::autosave`: Wrong table names `exists:quarters,id` / `exists:ad_types,id` — actual tables are `quarter` / `ad_type` (singular, consistent with `Ad::$table = 'ad'`). Incorrect names caused validation to silently pass any UUID without FK checking. Fixed: corrected to `exists:quarter,id` / `exists:ad_type,id`.
- `new/page.tsx` `onDraftCreated`: Inline arrow function was recreated on every render → `useEffect` in `AdFormWizard` (dep: `onDraftCreated`) could fire spuriously and call `router.push` multiple times. Fixed: wrapped in `useCallback`.
- `AdFormWizard.tsx` `useServerAutoSave`: `enabled` only checked `!isSubmitting` — auto-save could fire while `draftMutation.isPending`, creating a second orphan draft in a race condition. Fixed: `enabled: !isSubmitting && !isSavingDraft`.

**Production-readiness fixes – round 4 (final deep audit):**
- `AuthProvider.tsx` `DEVICE_KEYS`: Added `kh_push_dismissed`, `kh_pwa_dismissed`, `kh_owner_pwa_dismissed` to the preserve list. These three keys were missing, so `localStorage.clear()` on logout wiped them — the push notification prompt and both PWA install banners would re-appear on every new login, even if the user had already made a device-level decision to dismiss them. Also removed `kh:push-prompt-done` which is a window `CustomEvent` name, not a localStorage key (it was harmlessly preserved as null each time but was misleading documentation).

**LocalStorage keys (all device-level, must survive logout):**
`keyhome_cookie_consent_v1`, `kh_tour_completed_at`, `kh:welcome-dismissed`, `APPTOUR_SHOWN_KEY`, `kh_push_dismissed`, `kh_pwa_dismissed`, `kh_owner_pwa_dismissed`.

**Production-readiness fixes – round 3 (deep audit):**
- `sw.js` fetch handler: Moved `isCacheableApi()` check **before** the `url.origin !== self.location.origin` guard. Previously the guard returned early for all cross-origin requests, making the owner offline API cache (`CACHEABLE_OWNER_PATHS`) completely dead code in any production deployment where the backend lives on a different domain. Now cross-origin API GET requests are network-first cached correctly.
- `sw.js` header comment: Updated stale "v2" comment to match `VERSION = "v3"`.
- `OwnerPWAInstallPrompt.tsx`: Added `sw-updated` event listener + `handleUpdate` callback + `Snackbar`/`Alert` update toast (identical to the one in `PWAInstallPrompt`). Without this, owner panel users were permanently stuck on stale SW versions after each deployment because the update notification never appeared in the `/owner/` scope.
- `sw.js` background sync: Removed dead `kh-sync-favorites` and `kh-sync-contacts` handlers — no frontend code ever registers these tags or writes to those IndexedDB stores. `FavoritesProvider` uses `localStorage` + fire-and-forget API calls (no offline queue). Only `kh-sync-viewing-response` is wired (`useViewingResponseSync` hook). DB schema kept intact (3 stores) for existing-install compatibility; unused stores are forward-reserved with a comment.

**Production-readiness fixes – round 1 (post-audit, commit `40845d5`):**
- `next.config.ts`: Added `/manifest-owner.json` header rule (`Content-Type: application/manifest+json` + `Cache-Control: public, max-age=3600`). Without it the owner manifest had no declared content-type and browsers could silently reject it.
- `manifest.json` (customer): Removed `/owner/dashboard` + `/owner/ads/new` shortcuts — owner actions must not appear in the customer app home-screen shortcut menu. Replaced with `/nearby` and `/search-alerts`.
- `offline/page.tsx`: Added `export const dynamic = 'force-static'`. The SW precaches `/offline` at install time; without this Next.js server-renders a dynamic page with a fresh CSP nonce, making the SW-cached copy stale/broken.
- `PWAInstallPrompt.tsx`: Changed dismiss key storage from `sessionStorage` → `localStorage`. The customer banner was re-appearing every new browser session while the owner prompt already used `localStorage`.
- `sw.js`: Added `/manifest-owner.json` to `PRECACHE_URLS`; bumped `VERSION` `v2` → `v3` so existing installs drop stale caches on next activation.

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
- **`build:preprod` + `deploy:preprod` removed**: Vercel handles `cedrickdev` preview deployments automatically. `build:check` (`npm run build`) runs on all non-`main` branches including `cedrickdev` as the quality gate. The `VERCEL_TOKEN`/`VERCEL_ORG_ID`/`VERCEL_PROJECT_ID` variables are only needed for `main` (production deploy).
