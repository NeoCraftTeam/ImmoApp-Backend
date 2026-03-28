# AGENTS.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

## Project

KeyHome Backend — multi-tenant real estate platform for the West African market (XOF/XAF currency). Laravel 12 API + 3 Filament 4 admin panels (Admin, Bailleur, Agency). The customer-facing frontend is a separate Next.js 16 app in `keyhome-frontend-next/`.

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

Tests require a PostgreSQL `testing` database (see `phpunit.xml`). Meilisearch driver is set to `null` in tests. Payment gateway uses fake Flutterwave keys.

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
- **Actions** (`app/Actions/`) — single-purpose action classes (e.g., `HandlePostPaymentActions`).

### Payment System
- Strategy pattern: `PaymentGatewayInterface` with `FlutterwavePaymentService` and `FedaPayPaymentService`.
- `PaymentService` is the central orchestrator — injected via DI in `AppServiceProvider` with automatic fallback.
- Amounts are resolved server-side from `PointPackage`/`SubscriptionPlan` — never trust client amounts.
- DB locks (`lockForUpdate`) prevent double-spending on verification.

### Auth
- API: Laravel Sanctum (Bearer tokens + SPA session cookies).
- Filament panels: session-based with MFA (TOTP + email).
- Frontend uses Clerk for OAuth, exchanged for Sanctum tokens via `/auth/clerk/exchange`.

### Filament Panels
- **Admin** (`app/Filament/Admin/`) — full platform management. Path: `/admin`.
- **Bailleur** (`app/Filament/Bailleur/`) — property owner panel. Path: `/owner`.
- **Agency** (`app/Filament/Agency/`) — multi-tenant (Agency model). Path: `/agency`.

### API Routes
Routes are split into domain files under `routes/api/`: `auth.php`, `ads.php`, `payments.php`, `viewings.php`, `surveys.php`. All prefixed with `/api/v1/`.

### Key Conventions
- All PHP files use `declare(strict_types=1)`.
- Models use UUID primary keys (`HasUuids` trait).
- `Model::preventLazyLoading()` is enabled in non-production environments.
- Ad `status` is excluded from `$fillable` — use `transitionTo()` or `forceFill()`.
- Image uploads use Spatie Media Library with WebP conversions (thumb, medium, large).
- PostGIS for geolocation (`location` column on Ad/User models).
- Meilisearch for full-text search on Ads.

## Frontend (keyhome-frontend-next/)

```bash
cd keyhome-frontend-next
npm run dev          # development server
npm run build        # production build
npm run lint         # ESLint
npm run test         # Vitest unit tests
npm run test:e2e     # Playwright e2e tests
```

Stack: Next.js 16, React 19, MUI 7, Tailwind v4, Clerk auth, TanStack Query, Framer Motion.
Design tokens in `src/theme/tokens.ts`. MUI theme in `src/theme/theme.ts`.
