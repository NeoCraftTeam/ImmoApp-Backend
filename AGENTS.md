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

### Contracts (`app/Contracts/`)
- `PaymentGatewayInterface` — payment gateway abstraction (Flutterwave impl).
- `AiSearchServiceInterface` — NLP/image search parsing contract (`parse`, `parseFromImage`).
- `RecommendationEngineInterface` — ad recommendation contract (`recommend`).
- `TrustScoreServiceInterface` — trust score computation contract (`compute`, `getOrCompute`, `invalidate`).
- All bound in `AppServiceProvider::register()`.

### Services (`app/Services/`)
- `UserProfileService` — public profile assembly, response-time computation, trust-score resolution, unlocked-ads retrieval. Extracted from `UserController` (SRP).
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
- `NeighborhoodScorecardService` — location scoring. Overpass query uses `nwr` (node/way/relation) + `out center;` to capture building-mapped POIs (critical for sub-Saharan Africa where shops/schools are mapped as ways). Includes `public_transport` tags and expanded shop/amenity types. Coordinate parser handles both direct `lat/lon` (nodes) and `center.lat/center.lon` (ways/relations).
- `IsochroneService`, `DirectionsService` — geo/routing.
- `KeyScoreService` — proprietary property score.
- `TrustScoreService` — bidirectional user trust score (tenant + landlord, 7 signals each, 0–100).
- `FeatureFlagService` — runtime feature toggles.
- `AdminMetricsService` — dashboard analytics.
- `AcquisitionChannelClassifier`, `UtmAttributionService` — marketing attribution.
- `UserWelcomeService`, `WebPushService`, `NativeAppService` — notifications & mobile.
- `RetentionPushService` — behavioral retention push notifications (5 triggers: win-back after 3d inactivity, search-alert match, price-drop on favorites ≥5 000 FCFA, viewing reminder day-before, lease expiry at 30/7 days). All frequency-capped via Redis. Command: `app:send-retention-pushes` (scheduled twiceDaily 09:00/18:00). `--dry-run` flag available.
- `Chat/EncryptionService` — AES-256-CBC with HMAC-SHA256 MAC (authenticated encryption). Key from `CHAT_ENCRYPTION_KEY` env (32-byte hex). `encrypt()` returns `{ciphertext, iv}`; `decrypt()` verifies MAC before decrypting.
- `Chat/AttachmentService` — upload files to Cloudflare R2 (`chat-attachments/` prefix). MIME/size validated (images: JPEG/PNG/WEBP/GIF ≤5 MB; files: PDF/doc ≤20 MB). Returns descriptor with `signed_url` (1-hour TTL). `getSignedUrl()` refreshes URLs.
- `Chat/ConversationService` — find-or-create (gated on `UnlockedAd`), list (paginated), mark-as-read (broadcasts `MessageRead`), archive, unread count (Cache 30s TTL).
- `Chat/MessageService` — send (encrypt + update `last_message_id` + broadcast + FCM push + email 5min delay), soft-delete (sender only, 24h window), cursor-paginated history.
- `HealthCheckService` — enterprise health check service. 6 checks: Database, Redis, Queue, Storage, Meilisearch, Flutterwave. 3-tier status: `healthy` / `degraded` / `unhealthy`. Results cached 30 s (Redis). Critical checks: Database + Storage → failure = `unhealthy`. All others → `degraded`. Used by `GET /api/health` and `php artisan app:health-check --force`.
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
- Filament panels: session-based with MFA (TOTP + email) + **Passkeys (WebAuthn)**.
- **WebAuthn/Passkeys** (`laragear/webauthn ^5`):
  - User provider driver: `eloquent-webauthn` with `password_fallback: true` in `config/auth.php`.
  - `User` model implements `WebAuthnAuthenticatable` + uses `WebAuthnAuthentication` trait.
  - `User::webAuthnData()` overridden to return `email` + `firstname lastname` (no `name` attribute).
  - **Filament (admin panel) routes**: `POST /webauthn/register/options`, `POST /webauthn/register`, `POST /webauthn/login/options`, `POST /webauthn/login`, `DELETE /webauthn/credentials/{credential}`.
  - **API (frontend/PWA) routes** (`WebAuthnApiController`): All under `/api/v1/auth/webauthn/`:
    - Public: `POST login/options` (returns `X-WebAuthn-Token` header), `POST login` (requires `X-WebAuthn-Token` header, returns Sanctum token + UserResource).
    - Auth:sanctum: `POST register/options`, `POST register` (optional `alias`), `GET credentials` (list), `PATCH credentials/{id}` (rename), `DELETE credentials/{id}`.
  - `webauthn/*` routes excluded from CSRF verification in `bootstrap/app.php`.
  - Custom login page: `app/Filament/Admin/Pages/Auth/AdminLogin.php` (extends Filament Login).
  - Passkey button injected via `panels::auth.login.form.after` render hook → `filament.admin.components.passkey-login-button` Blade view.
  - Passkey management: Profile → "Passkeys" section → `filament.admin.components.passkey-manager` Blade view (list, register, name, delete).
  - **JS: Native WebAuthn API** — `navigator.credentials.create()` / `.get()` with manual base64url ↔ ArrayBuffer conversion. No external JS library (Webpass removed — caused 422 format mismatch).
  - **Challenge storage: Redis (cache)** — `CacheChallengeRepository` (`app/Services/WebAuthn/`) replaces default `SessionChallengeRepository`. Required because `SESSION_DRIVER=cookie` exceeds 4 KB browser cookie limit when challenge data is added. Bound in `AppServiceProvider::register()`. Identifier priority: (1) authenticated user ID, (2) `X-WebAuthn-Token` header (API stateless flow), (3) session ID (Filament panel session-based flow).
    - **⚠️ Critical bug fixed (root cause)**: Passkeys failed 100% of the time on the Filament admin panel.
      - **Root cause**: The session ID changes between the `/options` and `/login` requests. The Filament panel / Livewire / Laravel auth middleware calls `session()->migrate()` which rotates the session ID. Any mechanism that uses the session ID as the Redis cache key will fail because it stores under session ID A and looks up under session ID B.
      - **Attempted intermediate fix (also broken)**: Storing a `_wt_assertion` token in session data (survives `migrate()`). Screenshots showed even this failed because the browser somehow sends a different session on the follow-up request (new `Set-Cookie` in 422 response confirmed a new session was created).
      - **Final fix**: Do NOT depend on the session at all. Always generate a random token for unauthenticated `AssertionCreation`. Surface it in the OPTIONS response body as `_wt` AND in the `X-WebAuthn-Token` header. Both `WebAuthnLoginController::options()` (Filament) and `WebAuthnApiController::loginOptions()` (API) inject `_wt`. The frontend reads `_wt`, strips it from the options object before calling the browser WebAuthn API, and sends it back on the verify request (body field `_wt` and/or `X-WebAuthn-Token` header). `resolveIdentifier()` checks header then body. Zero session dependency.
      - Applies to both flows: **Filament admin panel** (Alpine.js in `passkey-login-button.blade.php`) and **Next.js PWA** (`webauthn.service.ts`).
    - **`_wt` body failsafe**: `WebAuthnApiController::loginOptions()` also embeds the challenge token in the response body under `_wt`. Frontend reads header first, then `_wt`. Prevents failure when CDN/proxies strip custom headers.
    - **Required env vars (production)**: `WEBAUTHN_ID=keyhome.app`, `WEBAUTHN_ORIGINS=https://keyhome.app,https://www.keyhome.app`. RP ID must be a suffix of every origin that registers/uses passkeys.
    - **Required env vars (local dev)**:
      ```
      WEBAUTHN_ID=keyhome.test
      WEBAUTHN_ORIGINS=http://keyhome.test:3000,https://keyhome.test:3000,http://keyhome.test,https://keyhome.test,http://admin.keyhome.test,https://admin.keyhome.test,http://localhost:3000,http://localhost
      ```
      `DynamicWebAuthnRelyingParty` middleware auto-overrides RP ID to `localhost` when origin host is `localhost`. For `keyhome.test:*` origins, the static `WEBAUTHN_ID=keyhome.test` applies (keyhome.test is a valid suffix of all *.keyhome.test origins).
    - **`WebAuthnApiController::login()`** now wrapped in try-catch — unexpected laragear exceptions are logged to `Log::warning('Passkey API login failed', ...)` instead of bubbling as 500.
    - **PHPStan note — `WebAuthnLoginController::login()`**: `AssertedRequest::login()` returns `WebAuthnAuthenticatable|null`. The `WebAuthnAuthenticatable` Laragear contract does **not** declare `getAuthIdentifier()` (it comes from `Illuminate\Contracts\Auth\Authenticatable`). Do **not** call `getAuthIdentifier()` on the return value — use `$user ? $user::class : null` or another PHPStan-safe accessor instead (commit `3001e0aa`).
  - **Alias/naming**: Registration POST accepts optional `alias` field → saved on `webauthn_credentials.alias` via `$request->save(['alias' => ...])` in `WebAuthnRegisterController`. UI shows name input before biometric prompt.
  - Config: `config/webauthn.php` (relying party from `APP_NAME`, origins auto-detected). Env: `WEBAUTHN_NAME`, `WEBAUTHN_ID`, `WEBAUTHN_ORIGINS`.
  - DB table: `webauthn_credentials` (migration `2026_04_12_161011`, `->morph('uuid')` for UUID users).
  - Google Socialite plugin removed from admin panel — admin auth is email/password + passkeys only.
  - **Frontend (Next.js) passkey integration**:
    - Service: `src/services/webauthn.service.ts` — base64url encoding, `navigator.credentials` calls, API wrappers.
    - Hooks: `src/hooks/usePasskey.ts` — `usePasskeyManager()` (list/register/rename/delete via TanStack Query), `usePasskeyLogin()` (login flow).
    - Components: `src/components/auth/PasskeyLoginButton.tsx` (login pages), `src/components/security/PasskeyManager.tsx` (settings pages). Both are **theme-aware**: `variant='client'` (default, crimson #F6475F) or `variant='owner'` (teal #0D9488).
    - Integrated in: client login (`/login`), owner login (`/owner/login`), **client profile `/profile` → "Sécurité" tab (index 4)**, **owner profile `/owner/profile` → "Sécurité" tab (index 2)**. Removed from `/parametres` and `/owner/security` (single source of truth in profile).
    - **`WebAuthnApiController` tokens**: `final readonly`, `LoginService` injected via constructor. Token issuance delegates to `LoginService::issueApiTokenForLoginContext(User, string): NewAccessToken` — same `TokenService::rotateForUser()` naming as password login (`owner_token_*` / `client_token_*`), same Sanctum abilities (`role:<enum>`, `api:access`), automatic rotation (old tokens revoked). Login response now includes `expires_at`. Invalid `login_context` values return 422. `RoleContextMismatchException` (e.g. CUSTOMER trying `owner` context) returns 403 `ROLE_CONTEXT_MISMATCH`. Covered by `tests/Unit/LoginServiceIssueApiTokenTest.php` (7 tests: scoping, rejection, rotation).
    - **Critical bug fix — `role` missing from passkey login response**: `UserResource` gates `role` and `type` behind `$request->user()?->id === $this->id`. The passkey login endpoint has no `auth:sanctum` middleware → `$request->user()` = null → `role` absent from JSON → frontend received `user.role = undefined` → role guard in `PasskeyLoginButton` failed → redirected back to `/login`. **Fix**: call `auth()->guard('web')->setUser($user)` in `WebAuthnApiController::login()` after credential verification, before building `UserResource`. This populates `$request->user()` without opening a session.
    - **Auth redirect fix (critical)**: Two confirmed root causes of owner passkey login redirecting to `/home`:
      1. `migrateLegacyTokens()` catch block unconditionally nullified `ownerInMemoryToken`/`clientInMemoryToken`. Fixed by saving tokens before migration and restoring them on failure in `src/lib/auth-session.ts`.
      2. Active Clerk session (CUSTOMER) fired Clerk exchange after passkey login, returning wrong user → role guard redirect. Fixed in `AuthProvider.tsx` by skipping Clerk exchange when `hasAnySanctumInMemory()` is true.
    - **`PasskeyLoginButton` role-gate**: added role validation after login — if `loginContext === 'owner'` but user isn't AGENT/ADMIN (or vice versa), redirects cleanly rather than persisting a wrong-context token.
    - **`OwnerLayoutClient` role guard hardened**: changed `if (user.role && !OWNER_ALLOWED_ROLES.includes(user.role))` → `if (!user.role || !OWNER_ALLOWED_ROLES.includes(user.role))` to prevent null-role bypass.
  - **Agency panel passkey login**: `AgencyPanelProvider` now renders `filament.admin.components.passkey-login-button` after the login form (same hook as admin panel) — passkey login available on both admin and agency Filament panels.
  - **Passkey email notifications**: `PasskeyNotificationMail` (`app/Mail/`) — sent on passkey add/remove from `WebAuthnApiController::register()` and `destroy()`. Uses `emails.layout` (crimson) for clients, `emails.owner-layout` (teal) for agents/admins. Template: `resources/views/emails/passkey-notification.blade.php`. Modern design with device details, IP, timestamp, security warning. Queued via `ShouldQueue`.
    - Query key: `passkeyKeys.all` in `src/lib/query-keys.ts`.
- Frontend uses Clerk for OAuth, exchanged for Sanctum tokens via `/auth/clerk/exchange`.
- Magic-link sign-in/sign-up supported.
- Social auth via Laravel Socialite (Apple provider included).
- **Google One Tap** (`src/components/auth/GoogleOneTap.tsx`):
  - Mounted **only on `/login`** (CUSTOMER page) — never on `/owner/login` (new One Tap users always get CUSTOMER role).
  - Activates only when `NEXT_PUBLIC_GOOGLE_CLIENT_ID` is set; returns `null` otherwise (zero-cost no-op).
  - Flow: GSI library loaded via `<Script strategy="afterInteractive">` → `onLoad` initializes once via `initializedRef` guard → `clerk.authenticateWithGoogleOneTap({ token: credential })` → `clerk.handleGoogleOneTapCallback(res, { signInFallbackRedirectUrl: '/home', signUpFallbackRedirectUrl: '/home' }, customNavigate)` → Next.js client-side navigation (no full reload).
  - `customNavigate` passes `router.push` so navigation stays client-side.
  - Auto-cancels and resets when `isAuthenticated` becomes `true` (user already logged in).
  - Types: `@types/google-one-tap` (devDependency). Import: `import type { CredentialResponse, PromptMomentNotification } from 'google-one-tap'`.
  - **Required env var**: `NEXT_PUBLIC_GOOGLE_CLIENT_ID` — same Google Client ID configured in Clerk dashboard for Google OAuth provider.
  - **E2E tests**: `e2e/google-one-tap.spec.ts` — 9 tests (3 skipped without `NEXT_PUBLIC_GOOGLE_CLIENT_ID`). Covers: GSI script presence/absence on `/login` and `/owner/login`, mocked credential callback, mobile layout regression, social buttons coexistence.
  - **Known gotcha**: `/se connecter/i` regex in Playwright tests matches both "Se connecter" (submit) and "Se connecter avec une Passkey" — always use `{ name: 'Se connecter', exact: true }` in E2E button locators.
  - **CSP requirements** (all in `src/proxy.ts` `buildCsp()`): `script-src`, `style-src`, `connect-src`, `frame-src`, and `img-src` must all include `https://accounts.google.com`. `img-src` also needs `https://lh3.googleusercontent.com` for user avatars. Missing any of these produces a CSP violation for the respective GSI resource.
  - **DuckDuckGo / privacy browsers**: content blockers block `play.google.com/log` and the One Tap iframe. This is browser-level and cannot be fixed in code. Always test One Tap in Chrome or Firefox with an active Google session.
  - **`unregistered_origin`**: if One Tap shows `[GoogleOneTap] Not displayed: unregistered_origin`, add `http://localhost` and `http://localhost:3000` to **Authorized JavaScript origins** in [Google Cloud Console](https://console.cloud.google.com/apis/credentials) for the OAuth Client ID.
  - **FedCM migration**: GSI emits a warning that `isNotDisplayed()` / `isSkippedMoment()` prompt notification methods will stop working when FedCM becomes mandatory. Non-blocking for now; revisit when Google announces enforcement date.
- **API rate limits**: CUSTOMER 300 req/min, AGENT with subscription 500 req/min, AGENT without subscription 300 req/min, ADMIN unlimited, guest 60 req/min.
- **Token refresh**: `POST /api/v1/auth/refresh` — rotates the current Sanctum token (delete old → create new), preserves login-context prefix (owner/client). `AuthController::refresh()`.
- **Session idle timeout** (frontend): `SessionTimeoutGuard` component (`src/components/session/`) monitors user activity via `useIdleTimeout` hook. After **15 min idle** → warning modal with **60 s countdown**. Two buttons: "Prolonger la session" (calls `/auth/refresh` to rotate token) or "Se déconnecter". Auto-logout if countdown reaches 0. Integrated in `providers.tsx` inside `AuthProvider`. Only active when `isAuthenticated`.

### Architecture Decisions
- **No repository pattern** — Eloquent is used directly in Services and Actions. At this scale (~70 models), the repository pattern adds indirection without meaningful testability gain (Eloquent itself is well-tested). Services are the business-logic boundary; if we outgrow this, we extract repositories per-domain rather than globally. Documented decision — not a TODO.
- **Interface contracts for key services** — `AiSearchServiceInterface`, `RecommendationEngineInterface`, `TrustScoreServiceInterface` enable swapping implementations and simplify mocking in tests. Bound in `AppServiceProvider::register()`.
- **UserProfileService extraction** — Public profile assembly, response-time computation, trust-score resolution, and unlocked-ads retrieval extracted from `UserController` to `app/Services/UserProfileService.php` (SRP compliance).

### Frontend Hooks (`keyhome-frontend-next/src/hooks/`)
- `useSearchFilters` — all search filter state, URL sync, remote data (cities, adTypes, facets, propertyAttributes), derived state (activeFilterCount, sortLabel, clearFilters, buildParams). Extracted from `search/page.tsx`.
- `useSearchResults` — TanStack Query calls for search results and map-all data, derived `ads`, `mapAds`, `totalPages`, `total`. Extracted from `search/page.tsx`.
- `useAuthActions` — login, loginOwner, loginWithOAuth, logout, refreshUser, setUser, finalizeAuth, getClerkToken. Extracted from `AuthProvider.tsx`.
- `useIdleTimeout` — tracks user inactivity and triggers session timeout warning.
- `useSearchHistory` — manages recent search history in localStorage.

### Frontend Shared Components (`keyhome-frontend-next/src/components/ui/`)
- `CityAutocomplete` — reusable city autocomplete with debounced search, shared visual config. Props: `value`, `onChange`, `label`, `placeholder`, `size`, `sx`, `required`, `error`, `helperText`, `disabled`. Available for progressive adoption across 13+ consumers.

### Animation System (SKILL.md compliant)
- **Easing:** All CSS/Framer Motion use out-quint `cubic-bezier(0.22, 1, 0.36, 1)`. Bounce easing `(0.34, 1.56, 0.64, 1)` is banned (SKILL.md: "feels dated and tacky"). Fixed globally in `globals.css` (`--spring`), `theme.ts` (MuiButton, MuiCard, MuiIconButton, MuiFab, MuiSwitch, MuiTabs, MuiAccordion), and per-component in `login/page.tsx`, `HeroSearch.tsx`, `AdCard.tsx`, `PackageCard.tsx`, `SplashTransition.tsx`. Zero bounce easings remain in the codebase.
- **Page transitions:** `template.tsx` in both `(dashboard)` and `(owner)` route groups. Framer Motion fade+slide (opacity 0→1, y 12→0, 300ms). Respects `useReducedMotion()`.
- **Section entrance:** `FadeIn` (CSS animation) wraps page headers (breadcrumbs + title + description) on all owner pages (financials, tenants, lease-contracts, reviews, security, subscriptions, parametres, equipe, availability, pro-services, viewings) and dashboard pages (parametres, payments, aide, prix-marche, publish). Staggered with `delay` prop.
- **Reusable utilities:** `ScrollReveal` (Framer Motion viewport-triggered fade+slide), `StaggerList` (staggered children), `FadeIn` (CSS animation). All respect `prefers-reduced-motion`.
- **Global accessibility:** `globals.css` has `@media (prefers-reduced-motion: reduce)` that zeroes all `animation-duration`, `transition-duration`, `scroll-behavior`.
- **Theme-level micro-interactions:** MuiButton active scale(0.96), MuiCard hover translateY(-3px), MuiIconButton hover scale(1.1)/active scale(0.92), MuiFab hover scale(1.08), MuiTextField focus glow ring, MuiTabs indicator smooth slide, MuiAccordion expand transition. All with focus-visible outlines.
- **Touch targets:** All interactive elements ≥44×44px per SKILL.md adapt guidelines (PageBreadcrumbs back button, NavDrawer items, AdCard buttons, Navbar elements).

### Filament Panels
- **Admin** (`app/Filament/Admin/`) — full platform management. Path: `/admin`.
  Provider: `app/Providers/Filament/AdminPanelProvider.php`.
  Resources: `AcquisitionUsers`, `ActivityLogs`, `AdReports`, `AdTypes`, `Ads`, `Agencies`, `Cities`,
  `NewsletterCampaigns`, `NewsletterSubscribers`, `Payments`, `PendingAds`, `PointPackages`,
  `PointTransactions`, `PromoCodes`, `PropertyAttributeCategories`, `PropertyAttributes`, `Quarters`,
  `Refunds`, `Reviews`, `SiteVisits`, `SubscriptionPlans`, `SubscriptionResource`, `Surveys`,
  `UnlockedAds`, `Users`.
  Dashboard: Tabbed sections via `HasFiltersForm` (Vue d'ensemble | Acquisition | Revenus | Engagement | Rétention | Avancé).
  Only 2-6 widgets loaded per tab instead of 22 at once.
  Geographic widget (`GeographicHeatmapWidget`): Mapbox GL JS map (clusters by city with ad count, zoom for individual ads) + table (offre vs demande). Requires `MAPBOX_TOKEN` env var. Data cached 5 min via `admin_geo_map_data` Redis key. Uses PostGIS `ST_Centroid(ST_Collect(...))` for city-level aggregation.
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
- `laravel/sanctum ^4`, `laravel/socialite ^5`, `dutchcodingcompany/filament-socialite`, `laragear/webauthn ^5`.
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

- **`VoiceSearchButton` click not working (fixed)**: Root cause: (1) `onClick` was `disabled ? undefined : toggle` — inside MUI `InputAdornment`, clicks bubbled up and focused the input instead. Fix: always attach `onClick` with `e.stopPropagation()`. (2) `onTranscript` was in `toggle`'s dependency array causing stale closures; switched to ref pattern (`onTranscriptRef`). (3) `rec.start()` had no try-catch — mic permission errors were uncaught.
- **Image search removed from frontend**: `ImageSearchButton` component still exists but is no longer rendered in `HeroSearch` or `NaturalSearchBar`. Both only import the `ParsedSearchParams` type from it.
- **`useUserLocation` geolocation ask-once (fixed)**: Was using `watchPosition` (continuous tracking) with 10-minute cache — re-prompted on every visit. Fixed: `getCurrentPosition` (one-shot), 24-hour localStorage cache (`user-location`), denial persisted in `user-location-denied` key so user is never re-prompted after refusing. `refresh()` method bypasses both caches for explicit re-requests.
- **Breadcrumb "Accueil" must link to `/home`**: Dashboard pages (bailleurs profile, agences) were linking Accueil breadcrumb to `/` (landing page) instead of `/home`. Public/marketing pages (blog, conditions, etc.) correctly use `/`.

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
- `[id]/page.tsx` `updateMutation`: same double `_method=PUT` append bug as `saveDraftMutation`. Fixed: removed manual append.
- `[id]/page.tsx` loading/error skeletons: `Container maxWidth="md"` caused layout jump to `"xl"` when main content loaded. Fixed all 3 containers to `"xl"`.
- `new/page.tsx` `createMutation`: no `onError` handler — silent failure on ad publication (any 422/500/network error showed no feedback, submit button just re-enabled). Fixed: `onError` added, surfaces server `message` via existing `draftSnackbar` state.
- `AdFormWizard` `handleSubmit`: no try/catch around `await onSubmit()` — unhandled promise rejection from form event handler on publish failure. Fixed: wrapped in try/catch; `clearDraft()` only called on success path.
- `AdForm.tsx`: confirmed dead code — never imported by any route. Both ad pages use `AdFormWizard` exclusively.

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

## Known Bugs Fixed

### `GET /api/v1/users/{identifier}/public-profile` — 500 for all seeded owners
**Symptom:** Every request to a seeded bailleur's public profile page returned HTTP 500 ("Profil introuvable" on the frontend).
**Root causes (two independent bugs):**
1. `Ad::getPublisherName()` accesses `$this->agency` directly (lazy load) but `publicProfile()` only eager-loaded `user.agency`, not `agency` on the `Ad` model itself. Added `'agency'` to the `with()` call. Lazy loading is disabled globally, so any un-loaded relation access = 500.
2. `computeResponseTimeLabel()` queries `viewing_reservations` (table doesn't exist yet). PHP's `catch(Throwable)` recovers at the application layer, but **PostgreSQL still marks the connection-transaction as ABORTED**. All subsequent queries in the same HTTP request (including `SELECT * FROM settings` inside `AdResource`) then fail with `SQLSTATE[25P02]: In failed sql transaction`. Fixed by wrapping the risky query in `DB::transaction()` → PostgreSQL uses a **SAVEPOINT** and rolls back only to it on failure, leaving the outer transaction intact.
**Pattern:** Any `try { DB::rawQuery(...) } catch (Throwable)` on a table that may not exist **must** use `DB::transaction(fn() => ...)` so PostgreSQL can recover via savepoint instead of aborting the entire outer transaction.
**Tests:** `tests/Feature/PublicProfileTest.php` (7 tests).

## Comprehensive Assessment Report Fixes (April 2026)

All 14 items from `COMPREHENSIVE_ASSESSMENT_REPORT.md` have been addressed:

### Critical (all 3 fixed)
1. **Color contrast WCAG** — `tokens.ts:82` `textSecondary` changed `#717171→#5A5A5A` (5.1:1 ratio).
2. **Touch target sizes** — `AdCard.tsx` carousel dots 5px→8px + `::before` hitbox; compare button `minHeight/minWidth: 44`.
3. **Mobile hero height** — `home/page.tsx:203` `minHeight: { xs: 280, sm: 400, md: 480 }` (was 340px).

### High Priority (all 5 fixed)
4. **Owner dashboard tabs** — `owner/dashboard/page.tsx` refactored with `<Tabs>`: Vue d'ensemble | Analytique | Activité. Hero stats remain always visible.
5. **Search UX** — `HeroSearch.tsx` now has geolocation button (`onGeolocate` prop, `MyLocationIcon`). Intent dialog result saved to `localStorage('kh:last-intent')` — returning users skip the dialog.
6. **Dark mode card contrast** — `theme.ts:354` `paper: dark.surface` (was `dark.paper` — #1D1D24→#24242D, ~12% brightness delta).
7. **`aria-current` on nav** — `Navbar.tsx:158` and `BottomNav.tsx:107` add `aria-current="page"` to active links.
8. **Email role-based layouts** — 6 templates switched from `emails.layout` to `emails.owner-layout`: `agency-welcome`, `reservation/created-landlord`, `subscription/success`, `subscription/renewal-reminder`, `subscription/expiring`, `subscription/invoice`.

### Medium Priority
9–10. **Infinite scroll / loading states** — deferred (existing pagination + skeleton loading already adequate).
11. **Customer empty states** — `home/page.tsx` and `search-alerts/page.tsx` now use shared `EmptyState` component with `variant="customer"`.
12. **Image aspect ratios** — deferred (3:2 already used in `AdCard`).
13. **Page transition indicator** — already existed (`RouteProgressBar` in root layout).
14. **OTP template consolidation** — no actual duplicates found (`verification-code`, `reset-password`, `pricing-verification` serve different purposes).

### Email System Polish
- **Dev preview** — `GET /dev/email-preview` (local env only). `EmailPreviewController` renders 11 Mailable templates in-browser with fake data. Routes guarded by `app()->environment('local')`.
- **Logo optimization** — `keyhomelogo_email.png` resized 500×500→96×96 (104KB→7.4KB). Retina-ready for 48px display.
- **Outlook 2016 compat** — Both `layout.blade.php` and `owner-layout.blade.php` now include MSO conditional `<table>` wrapper (600px width), `mso-padding-alt` for CTA buttons, and `border-radius: 0` fallback. Gmail dark mode uses `[data-ogsc]` selectors (already present).

### Testing Infrastructure
- **Accessibility audit** — `e2e/accessibility.spec.ts` uses `@axe-core/playwright` to scan customer + owner pages for WCAG 2.1 AA violations. Run: `npx playwright test e2e/accessibility.spec.ts`.
- **Load test** — `tests/load/load-test.yml` (Artillery). 5 weighted scenarios (browse, search, detail, autocomplete, health). Target: 200 req/s sustained, p95<500ms. Run: `artillery run tests/load/load-test.yml`.
- **Email rendering** — `tests/Feature/EmailRenderingTest.php` (13 Pest tests). Renders all preview Mailables, asserts valid HTML + MSO conditional comments + correct accent color per layout.
- **Mobile viewports** — Playwright config now has 3 projects: `chromium` (desktop), `mobile-android` (Pixel 5), `mobile-ios` (iPhone 13).
- **A/B feature flag** — `ab_search_geolocation` in `config/features.php` (default off). Toggleable from Filament admin or `FEATURE_AB_SEARCH_GEOLOCATION=true` in env.

### Design System Normalization (P0–P1)
- **Brand tokens** — `tokens.ts` expanded with `primaryAlpha{5,10,12,15,25,30,40,88}`. All hardcoded `rgba(246,71,95,...)` in `AdDetailClient.tsx`, `payment-success/page.tsx` replaced with token references.
- **Hardcoded grays** — `#999`→`var(--mui-palette-text-secondary)`, `#888`→`textSub` token.
- **IconButton a11y** — `aria-label` added to 30+ `IconButton` instances across shared components and owner pages.
- **LocalStorage key** `kh:last-intent` — stores last search intent (`louer`|`acheter`) for returning users.

### Journal de Sécurité (ActivityLogResource)
`app/Filament/Admin/Resources/ActivityLogs/ActivityLogResource.php` — full overhaul:

**Table:**
- Added `log_name` badge column (Sécurité 🔴 / Admin 🔵) — **first column**, with icon.
- `event` badge now context-aware: security events map `properties.action` → french label (Connexion / Déconnexion / Échec connexion / Réinit. MDP / Verrouillage); CRUD events map event → Création/Modification/Suppression.
- `event` badge color is also context-aware (info/gray/danger/warning for security; success/warning/danger for CRUD).
- Added `ip_address` column extracting `properties->ip`.
- Added `log_name` SelectFilter (Sécurité / Actions Admin).

**ViewAction (slide-over modal):**
- `modalIcon`, `modalIconColor`, `modalHeading` now context-aware for both security and CRUD events.
- Modal headings are fully French (e.g. "Connexion administrateur", "Modification d'un enregistrement").

**Infolist — Summary card:**
- Colored left border: `#F6475F` for security, green/amber/red for CRUD events.
- Top row: log-type badge (Sécurité/Action Admin) + date.
- Large description text.
- Meta row: ACTION pill (colored by event type), ENTITÉ pill (blue), ADMIN name+email.
- **Network section** (security events only, orange card): Adresse IP, Guard, Navigateur (user-agent truncated to 72 chars).

**Infolist — Diff table:**
- Section heading "Modifications détaillées" with colored accent bar + field count.
- Header border uses event-specific accent color (green/amber/red/brand).
- New/removed-only cells use neutral background (not red/green) to avoid false alarm.
- Empty state has a proper styled message.

**humanizeFieldName:** Expanded from 18 → 40+ entries covering all common model fields (amounts, timestamps, geo, subscription, payment fields).

### Other Filament Resource Infolist Overhauls

**RefundResource** (`app/Filament/Admin/Resources/Refunds/RefundResource.php`):
- **Bug fixed:** `form()` was incorrectly using `TextEntry` (infolist components) instead of form fields. `ViewAction` had no config.
- Added proper `infolist()` with 4 sections: Remboursement (amount, status badge, type, ref), Parties (user + admin who processed), Motif & Notes (reason + admin_note prose), Horodatage.
- `ViewAction` now: `slideOver()`, contextual `modalIcon`/`modalIconColor` (success/danger/info by status), `modalHeading` with formatted XAF amount.

**NewsletterSubscriberResource** (`app/Filament/Admin/Resources/NewsletterSubscribers/NewsletterSubscriberResource.php`):
- **Bug fixed:** `ViewAction::make()` with no `infolist()` produced an empty modal.
- Added `infolist()` with 2 sections: Coordonnées (email copyable, name, locale badge, source badge), Statut (boolean active icon, confirmed_at, unsubscribed_at, created_at).
- `ViewAction` now: `slideOver()`, `modalIcon` = check-badge if subscribed else envelope, `modalHeading` = email.

**UserResource** (`app/Filament/Admin/Resources/Users/UserResource.php`):
- Added **Score de confiance** section (visible only if trustScores not empty): tenant badge + landlord badge + computed_at.
- Added **Agence rattachée** section (visible only if agency not null): name + slug copyable badge.

**PaymentResource** (`app/Filament/Admin/Resources/Payments/PaymentResource.php`):
- Added **Passerelle & crédits** infolist section: gateway badge (ucfirst), points_awarded badge, pointPackage.name badge.
- Eager loading updated: `['ad.ad_type', 'user', 'pointPackage']`.

**AgencyInfolist** (`app/Filament/Admin/Resources/Agencies/Schemas/AgencyInfolist.php`):
- Added **Statistiques** section (3 cols): ads_count via `users()->withCount('ads')`, members_count via `users()->count()`, subscription via `getCurrentSubscription()` + `data_get()` (avoids nullsafe PHPStan issues).

### PDF Exports

#### Payment History PDF (User-facing)
- **Backend endpoint**: `GET /api/v1/payments/export?period=30|90|365` (auth required, throttle 10/min).
  - Implemented in `PaymentController::export()`.
  - Accepts optional `period` (days). Omit for full history.
  - Returns `application/pdf` download via DomPDF.
  - Template: `resources/views/pdf/payment-history.blade.php` — brand #F6475F, Stripe-style layout with user info, 3-stat summary cards, full transaction table with type/status/credits/method columns, total row, footer.
- **Frontend service**: `paymentsService.exportPdf(period?)` in `src/services/payments.service.ts` — fetches blob, triggers browser download.
- **Owner panel**: `PaymentHistoryTable.tsx` — period chips (Tout/30j/90j/1an) + red PDF button above table.
- **Client PWA**: `PaymentHistoryTableModern.tsx` — same period chips already present, "Télécharger CSV" button replaced with "Télécharger PDF" (real backend call, not client-side CSV).

#### Admin Report PDF
- Template `resources/views/pdf/admin-monthly-report.blade.php` rebranded: teal (#0d9488) → brand #F6475F throughout (header gradient, section titles, highlights).
- Entry point: `ExportActionsWidget::exportPdf()` (Livewire, Filament admin dashboard).

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
