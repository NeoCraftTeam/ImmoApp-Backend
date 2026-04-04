# AGENTS.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.
It is kept up-to-date automatically: every edit to the codebase should be reflected here.

## Project

KeyHome Backend — multi-tenant real estate platform for the West African market (XOF/XAF currency).
Laravel 12 API + 2 active Filament 4 admin panels (Admin, Agency). The customer-facing frontend is a
separate Next.js 16 app in `keyhome-frontend-next/`.

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
- Strategy pattern: `PaymentGatewayInterface` (`app/Contracts/`) implemented by `FlutterwavePaymentService`.
- `PaymentService` is the central orchestrator — injected via DI in `AppServiceProvider`.
- Amounts resolved server-side from `PointPackage`/`SubscriptionPlan` — never trust client amounts.
- DB locks (`lockForUpdate`) prevent double-spending on verification.
- Events: `PaymentInitiated`, `PaymentSucceeded`, `PaymentFailed`.

### Auth
- API: Laravel Sanctum (Bearer tokens + SPA session cookies).
- Filament panels: session-based with MFA (TOTP + email).
- Frontend uses Clerk for OAuth, exchanged for Sanctum tokens via `/auth/clerk/exchange`.
- Magic-link sign-in/sign-up supported.
- Social auth via Laravel Socialite (Apple provider included).

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
- **CI/CD**: GitHub Actions (`.github/workflows/`) for `main`/`develop`/`preprod` + GitLab CI/CD (`.gitlab-ci.yml`) with self-hosted runner and Container Registry.
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
- **Dockerfile is multi-stage**: Stage 1 (`node-builder`) compiles Vite/Filament assets; Stage 2 (PHP-FPM) produces the production image without Node.js (~80 MB saved). `COPY composer.json composer.lock` precedes `RUN composer install` so the vendor layer is only rebuilt when `composer.lock` changes. Static assets (`vendor:publish --tag=livewire:assets`, `filament:assets`) are baked into the image and must NOT be re-published at deploy time.
- **Deploy process** (both prod and preprod): pull image → start containers → `migrate --force` (with automatic rollback to previous image on failure) → `optimize:clear` → `bash resetFilamentLivewire.sh` (cache only) → `optimize` → `artisan up` → `l5-swagger:generate` (non-blocking, `|| true`). `composer install` is NOT run at deploy time — it must be in the Docker image.
- **`scout:import` is NOT run on every deploy.** Only `scout:sync-index-settings` runs on deploy. Full reindexing should be triggered manually or via a scheduled command when the mapping changes.
- **PHP JIT** is enabled in `.docker/php/opcache.ini` (`opcache.jit=tracing`, `opcache.jit_buffer_size=128M`). Do not disable unless debugging a JIT-specific crash.
- **`resetFilamentLivewire.sh`** only rebuilds runtime caches (config, routes, views, events, Filament components). Asset publishing has been removed from this script — it is now done at Docker build time.

## Frontend (keyhome-frontend-next/)

```bash
cd keyhome-frontend-next
npm run dev          # development server
npm run build        # production build
npm run lint         # ESLint
npm run test         # Vitest unit tests
npm run test:e2e     # Playwright e2e tests
```

Stack: Next.js 16, React 19, MUI 7, Tailwind v4, Clerk auth, TanStack Query, Framer Motion, Mapbox GL, Photo Sphere Viewer (360° tours), Recharts, Three.js, next-intl, Storybook, Vitest, Playwright.
Three themes: `src/theme/tokens.ts` (design tokens), `src/theme/theme.ts` (MUI), `src/theme/ownerTheme.ts` (bailleur).
22 service files in `src/services/`. 16 custom hooks in `src/hooks/`.
Env vars: `NEXT_PUBLIC_API_URL`, `NEXT_PUBLIC_MAPBOX_TOKEN`, `NEXT_PUBLIC_CLERK_PUBLISHABLE_KEY`, `CLERK_SECRET_KEY`, `NEXT_PUBLIC_VAPID_PUBLIC_KEY`, `NEXT_PUBLIC_OWNER_PANEL` (`next`=integrated bailleur UI, `laravel`=Filament panel).
