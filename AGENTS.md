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

**Brand tagline (devise):** « Votre patrimoine immobilier en poche » — single source of truth in `keyhome-frontend-next/src/lib/brand.ts` (`BRAND_TAGLINE`, `BRAND_TITLE_WITH_TAGLINE` for default titles and OG alt text).

## Remotes

```
Backend  → origin  = GitHub (NeoCraftTeam/ImmoApp-Backend)
         → gitlab  = GitLab (neocraft/immoapp-backend)
Frontend → origin  = GitLab (neocraft/keyhome-next)
         → github  = GitHub (NeoCraftTeam/keyhome-frontend-next)
```

## Build & Run

**Convention équipe : ne pas utiliser Laravel Sail** pour les commandes locales — exécuter `php`, `composer`, `npm` et les binaires `vendor/bin/*` directement (voir `.cursor/rules/no-sail.mdc`).

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

### When the user asks to « test then commit » (backend)

Agents must run **Pint → PHPStan → Rector (apply + dry-run clean) → Pint/PHPStan again if needed → tests → commit**. See `.cursor/rules/test-then-commit.mdc` for the exact checklist.

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
- `PaymentGatewayInterface` — payment gateway abstraction. Two implementations: `FlutterwavePaymentService` (mobile money + orange money) and `StripePaymentService` (carte, EUR billing). Routed per-method via `PaymentMethod::gateway()` and the registry built in `AppServiceProvider`.
- `AiSearchServiceInterface` — NLP/image search parsing contract (`parse`, `parseFromImage`).
- `RecommendationEngineInterface` — ad recommendation contract (`recommend`).
- `TrustScoreServiceInterface` — trust score computation contract (`compute`, `getOrCompute`, `invalidate`).
- All bound in `AppServiceProvider::register()`.

### Services (`app/Services/`)
- `UserProfileService` — public profile assembly, response-time computation, trust-score resolution, unlocked-ads retrieval. Extracted from `UserController` (SRP).
- `LoginService`, `RegistrationService`, `TokenService`, `ClerkJwtService` — auth flows.
- `Payment/PaymentService` — multi-gateway orchestrator. Accepts a primary gateway, optional cross-gateway fallback (mobile money only) and a `array<gateway-name, PaymentGatewayInterface>` registry built by `AppServiceProvider`. Routes each `createPayment()` call via `PaymentMethod::gateway()` lookup; webhook + verify dispatched to `$payment->gateway` for the right impl.
- `Payment/FlutterwavePaymentService` — Flutterwave implementation (mobile money / orange money). Hosted-checkout redirect flow.
- `Payment/StripePaymentService` — Stripe via Cashier `\Laravel\Cashier\Cashier::stripe()`. PaymentIntent (no redirect, returns `clientSecret`), signed webhook (`Stripe-Signature` against raw body, `__raw` injected by controller), refund. Conversion XAF→EUR cents at the BEAC peg `1 EUR = 655.957 XAF` (config `services.stripe.xaf_to_eur_rate`). Idempotency keys: `kh_initiate:{tx_ref}` / `kh_refund:{intent}:{amount}`.
- `Payment/PaymentMethodGateService` — admin-controlled per-method gating. Persisted in `Setting` (`payment_method:{value}:enabled`), cached 5 min, same pattern as `FeatureFlagService`. Methods: `isEnabled / enable / disable / reset / available / describeAvailable / describeAll`. Defaults via `match` (compile-time exhaustive). Consumed by `GET /api/v1/payments/methods` (public catalogue) and `FlutterwaveInitiateRequest::withValidator()` (rejects disabled methods at validation time with French error label).
- `Payment/RefundService` — refund processing (gateway-agnostic via the registry).
- `SubscriptionService` — plan management & renewals.
- `PointService` — credit wallet operations.
- `AdBoostService` — ad promotion logic.
- `AdReportService` — abuse reporting.
- `AgencyService` — agency CRUD.
- `ViewingScheduleService` / `ReservationService` — viewing calendar & booking.
- `LeaseContractService` — lease management.
- `TourService` / `PanoramaProcessor` — 360° virtual tours.
- `AiDescriptionEnhancer`, `AiDigestService`, `AiSearchService` — AI-powered features.
  - `AiSearchService::parse()` — multi-provider NLP text search (Groq/Llama-3.3-70B → OpenAI GPT-4o-mini → Gemini 2.0 Flash → Together → Mistral) with circuit breakers + 24h cache. **Type disambiguation** : `AdType::resolveFromNaturalSearchHint()` choisit la bonne ligne catalogue (ex. *appartement meublé* vs *appartement simple*) à partir du texte « meublé/meuble » et du hint LLM ; fusion de `furnished` si le mot apparaît dans la requête même si le modèle l’omet.
  - Ads index (`toSearchableArray`) expose **`is_furnished`** (attribut `furnished` et/ou nom de type contenant *meubl*). Le filtre recherche « amenity » **meublé** côté API applique `(attributes = furnished OR is_furnished = true)` (Meilisearch + fallback Eloquent) pour ne pas exclure les annonces classées uniquement par type.
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
- `RetentionPushService` — behavioral retention Web Push (4 triggers: win-back after 3d inactivity, price-drop on favorites ≥5 000 FCFA, viewing reminder day-before, lease expiry at 30/7 days). Search-alert matches are handled separately by `SendSearchAlertInstantNotificationJob` + `SendSearchAlertFcmJob` (instant, per `SearchAlert.notify_email` / `notify_push`). Retention command: `app:send-retention-pushes` (scheduled twiceDaily 09:00/18:00). `--dry-run` flag available.
- **E-mails jalons** — `SendWelcomeNotification` / `UserWelcomeService` après vérification (`Verified`) et flux Clerk. **Client** : premier déblocage contact par crédits → `FirstAdUnlockCongratulationsMail` depuis `UnlockAd` (une seule fois par compte, e-mails `@clerk.local` ignorés). **Bailleur** : passage modération `PENDING` → `AVAILABLE` → listener `SendAdApprovedEmail` → `AdApprovedMail` (layout teal `emails.owner-layout`), via `AdStatusTransitioned` / `AdObserver` (y compris approbation unitaire ou bulk Filament).
- `Chat/EncryptionService` — AES-256-CBC with HMAC-SHA256 MAC (authenticated encryption). Key from `CHAT_ENCRYPTION_KEY` env (32-byte hex). `encrypt()` returns `{ciphertext, iv}`; `decrypt()` verifies MAC before decrypting.
- `Chat/AttachmentService` — upload files to Cloudflare R2 (`chat-attachments/` prefix). MIME/size validated (images: JPEG/PNG/WEBP/GIF ≤5 MB; files: PDF/doc ≤20 MB). Returns descriptor with `signed_url` (1-hour TTL). `getSignedUrl()` refreshes URLs.
- `Chat/ConversationService` — find-or-create (gated on `UnlockedAd`), list (paginated), mark-as-read (broadcasts `MessageRead`), archive, unread count (Cache 30s TTL).
- `Chat/MessageService` — send (encrypt + update `last_message_id` + broadcast + FCM push + email 5min delay), soft-delete (sender only, 24h window), cursor-paginated history.
- `HealthCheckService` — enterprise health check service. 6 checks: Database, Redis, Queue, Storage, Meilisearch, Flutterwave. 3-tier status: `healthy` / `degraded` / `unhealthy`. Results cached 30 s (Redis). Critical checks: Database + Storage → failure = `unhealthy`. All others → `degraded`. Used by `GET /api/health` and `php artisan app:health-check --force`.
- `PropertyAttributeImportService` — bulk attribute import.
- `UserAgentParser` — browser/device detection.
- `Media/MediaPathGenerator` — Spatie media paths.
- `QrCodeService` (`final readonly`) — Apple-style branded scannable QR generator + UTM URL helpers (`appendUtm`, `absoluteFrontendUrl`, `adListingUrl`, `landlordProfileUrl`). Public render API: `pngDataUriForUrl(url, size=800)` returns `data:image/png;base64,...`, `renderRichPng(url, size)` returns binary PNG, `renderRichSvg(url)` returns the branded SVG. Implementation: chillerlan/php-qrcode v5, ECC level **H**, **#000000** modules, `quietzoneSize: 4`, circular modules with `circleRadius: 0.5`, finder + alignment patterns kept square. Branded SVG layout: matrix at **62 % of canvas** (room for rings), 5 decorative dashed rings (2 inner halos + 3 outer signature rings, brand crimson on the outer one) anchored to the matrix half-DIAGONAL so they NEVER overlap data → 100 % scannable. Centre punch-out at 10 % of `qrSize` with the embedded official KeyHome PNG logo (`keyhome-frontend-next/public/icons/icon-512x512.png`). Rasterisation chain: **Imagick → rsvg-convert → plain chillerlan PNG** fallback (DomPDF can't render the branded SVG reliably).

### QR Code & Printable Assets (`app/Http/Controllers/Api/V1/QrCodeController.php`)
- All routes in `routes/api.php` are gated by `auth:sanctum` + `owner.role` + `panel.role:owner` + `token.role:agent` (so customers get **403** on profile + ad endpoints).
- **Ad endpoints** (prefix `my/ads/{ad}`, `throttle:60,1` for QR / `20,1` for PDF):
  - `GET /api/v1/my/ads/{ad}/qr-code` → JSON `{data: {ad_url, profile_url, qr_data_uri}}`. Authorised via `AdPolicy::update`.
  - `GET /api/v1/my/ads/{ad}/qr-code/image` → `image/png` (rich branded QR).
  - `GET /api/v1/my/ads/{ad}/placarde` → A5 portrait pancarte PDF (`pdf.ad-placarde` blade — hero photo, status pill, title, price, 3 features, branded QR + CTA).
- **Profile endpoints** (prefix `my/profile`):
  - `GET /api/v1/my/profile/qr-code` → JSON `{data: {profile_url, qr_data_uri}}`.
  - `GET /api/v1/my/profile/qr-code/image` → `image/png`.
  - `GET /api/v1/my/profile/business-card` → 90 × 55 mm landscape PDF (`pdf.business-card` blade, paper `[0,0,255.118,155.906]` landscape, rounded corners via `border-radius: 3mm` + thin `#CBD5E1` border so DomPDF actually paints them, avatar / role / KeyHome stat line / contact list / KeyHome key+keyring badge / 22 mm branded QR + `Scannez · mes annonces`).
  - `GET /api/v1/my/profile/business-card/preview` → returns **HTML** (not PDF, `Content-Type: text/html`) rendered through `pdf.business-card-preview` blade — same DOM as the PDF blade, transparent background, JS auto-fits the card to the iframe via `min(scaleX, scaleY)`. Used as `srcDoc` of the iframe inside `QrCodeDialog`.
- UTM scheme: `utm_source=keyhome`, `utm_medium=qr|placard|visitcard|visitcard_preview`, `utm_campaign=owner_share`, `utm_content=ad_<id>` or `profile_<id>`.
- Controller is `final` with DI on `QrCodeService` (readonly). All view data centralised in `buildBusinessCardPayload(User, utmMedium)` so the PDF and HTML preview stay perfectly in sync. Helpers `loadAdCoverAsBase64`, `loadUserAvatarAsBase64` resolve Spatie media → storage path → absolute URL → null, returning `data:<mime>;base64,...` URIs (DomPDF runs with `isRemoteEnabled: false`).
- **Frontend integration**:
  - `QrCodeDialog.tsx` (`variant: 'ad' | 'profile'`, optional `ad: {id, title}`) — fetches the meta JSON via React Query. Profile variant additionally fetches the HTML preview and renders it inside an `<iframe srcDoc>` above the QR PNG so users see the printable card before downloading. Buttons: copy link, download PNG, ad → "Pancarte A5", profile → "Carte de visite" (teal `#0D9488` / hover `#0F766E`).
  - `ownerService` methods: `getAdQrCodeMeta`, `downloadAdQrPng`, `downloadAdPlacarde`, `getProfileQrMeta`, `downloadProfileQrPng`, `downloadBusinessCard`, `fetchBusinessCardPreviewHtml`.
  - Owner pages: **Mes annonces** action menu → "QR code & pancarte" opens the dialog with `variant="ad"`. **Profil bailleur** → "QR code & carte de visite" Paper card with an "Ouvrir" button opens `variant="profile"`.
- **Tests**: `tests/Feature/QrCodeTest.php` (6 assertions × meta + image + PDF + ownership/role guards). Smoke script `tests/qr-pdf-smoke.php` regenerates `/tmp/qr-rich.png`, `/tmp/placarde-sample.pdf`, `/tmp/business-card-sample.pdf` for visual review.

### Actions (`app/Actions/`)
- `CreateAd`, `UpdateAd`, `UnlockAd`, `HandlePostPaymentActions`, `SubmitAnonymousSurveyAction`.
- `Agency/`: `CreateAgencyAction`, `UpdateAgencyAction`, `DeleteAgencyAction`, `ListAgenciesAction`.
- `City/`: `CreateCityAction`, `UpdateCityAction`, `DeleteCityAction`, `ShowCityAction`, `ListCitiesAction`.
- `Reservation/`: `ConfirmReservationAction`.

### Payment System
- **Two gateways live: Flutterwave + Stripe.** `PaymentGateway` enum: `Flutterwave = 'flutterwave'`, `Stripe = 'stripe'` (each with `label()` for invoices/admin). Stripe was added in May 2026 alongside Laravel Cashier `^16.0`.
- **Strategy pattern + registry.** `PaymentGatewayInterface` (`app/Contracts/`) implemented by `FlutterwavePaymentService` and `StripePaymentService`. `PaymentService` ctor accepts `(primary, ?fallback, registry)` — `AppServiceProvider` builds the registry indexed by `getName()` so `createPayment()` can route per-method without if/else.
- **Routing.** `PaymentMethod::gateway()` is the single source of truth: `MOBILE_MONEY` + `ORANGE_MONEY` + `FLUTTERWAVE` (legacy umbrella) → Flutterwave ; `CARD` → Stripe. Adding a new method only requires a case + match arm in the enum.
- **Admin gating.** `PaymentMethodGateService` lets admins flip any of the four methods on/off at runtime (persisted in `Setting`, cached 5 min). Public consumer: `GET /api/v1/payments/methods`. Backend guard: `FlutterwaveInitiateRequest::withValidator()` rejects disabled methods with a localised French message before any gateway call.
- **`Payment::gateway` column** stored as plain string (not enum cast) — Stripe rows coexist with legacy Flutterwave rows in the same table.
- **Webhooks.** Flutterwave: `POST /api/v1/webhooks/{gateway}` (constraint `flutterwave`). Stripe: dedicated `POST /api/v1/webhooks/stripe` — Stripe requires the **raw body** for signature verification, controller passes `getContent()` and injects it as `__raw` so `StripePaymentService::handleWebhook()` can call `\Stripe\Webhook::constructEvent()`. `Cashier::ignoreRoutes()` is set in `AppServiceProvider::boot()` so Cashier's default `/stripe/webhook` does not collide.
- **Stripe currency: EUR pegged.** Stripe does not support XAF/XOF. `payments.amount` stays in XAF (canonical), Stripe is invoiced in EUR using `1 EUR = 655.957 XAF` (BEAC peg, config `services.stripe.xaf_to_eur_rate`). Both directions (XAF→cents and cents→XAF for refunds) use the same peg so receipts always reconcile. PaymentIntent metadata carries `xaf_amount` + `xaf_to_eur_rate` for audit. Visitor multi-currency display (`<Price>` / `CurrencySelector`) is unrelated to Stripe billing currency.
- **Cashier scope.** `Laravel\Cashier\Billable` trait on `User`. Tables renamed to **`cashier_subscriptions`** + **`cashier_subscription_items`** to avoid collision with the existing business `subscriptions` table (`App\Models\Subscription`, agency plans). Custom models `App\Models\CashierSubscription` and `CashierSubscriptionItem` pin the table names; wired via `Cashier::useSubscriptionModel(...)` and `useSubscriptionItemModel(...)` in `AppServiceProvider::boot()`. UUID-safe migrations (`foreignUuid` because `users.id` is UUID via `HasUuids`).
- **Single Stripe webhook secret** for both test and live (`STRIPE_WEBHOOK_SECRET`). Rotate the value in `.env` when switching environments — no `_TEST`/`_LIVE` split.
- **Stripe env vars** : `STRIPE_KEY` (`pk_test_*` / `pk_live_*`), `STRIPE_SECRET` (`sk_*`), `STRIPE_WEBHOOK_SECRET` (`whsec_*`), optional `STRIPE_CURRENCY=eur`, `STRIPE_WEBHOOK_TOLERANCE=300`. All in `.env.example`.
- Amounts resolved server-side from `PointPackage`/`SubscriptionPlan` — never trust client amounts.
- DB locks (`lockForUpdate`) prevent double-spending on verification.
- Events: `PaymentInitiated`, `PaymentSucceeded`, `PaymentFailed`.
- **Crédits (`POST /credits/purchase/{package}`)** — accepte `callback_url` optionnelle ; validée par `App\Support\FrontendRedirectGuard` (même politique d’hôte que OAuth / `FRONTEND_URL` + `OAUTH_ALLOWED_REDIRECT_HOSTS`). Passée à `PaymentService::createPayment` comme `redirect_url` vers Flutterwave.
- **Frontend integration** (Phase 3, à venir) : `pnpm add @stripe/stripe-js @stripe/react-stripe-js` ; `PaymentModal.tsx` détecte `gateway === 'stripe'` dans la réponse de `/payments/initiate_payment` et utilise le `payment_link` retourné comme `clientSecret` pour `<Elements>` + `<PaymentElement>` ; pour Flutterwave (mobile money), comportement actuel inchangé (redirect hosted checkout).

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
    - Components: `src/components/auth/PasskeyLoginButton.tsx` (login pages), `src/components/security/PasskeyManager.tsx` (settings pages). **Couleurs** via `useTheme().palette.primary` (rose client / teal bailleur selon le `ThemeProvider`). **WebAuthn indisponible** : message FR via `explainPasskeyUnsupported()` (`src/lib/passkey-support.ts`); erreurs biométrie via `formatWebAuthnClientError`. Gestion erreur liste passkeys + bouton « Réessayer » dans `PasskeyManager`. Tests Vitest : `src/tests/lib/passkey-support.test.ts`.
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
  - **CSP requirements** — allowlists are centralized in `keyhome-frontend-next/src/lib/csp-allowlist.ts` and applied in `src/proxy.ts` `buildCsp()` (per-request nonce). `media-src` mirrors `img-src` hosts (R2 signed URLs for voice/video) so `<audio>`/`<video>` are not blocked by `default-src 'self'`. `connect-src` must include `https://*.googleapis.com` / `wss://*.googleapis.com` (Firebase Installations, FCM, etc.), `https://www.gstatic.com`, Vercel Speed Insights (`vitals.vercel-insights.com`), Sentry (`*.sentry.io`), R2 `https://*.r2.cloudflarestorage.com`, dev Reverb `ws://localhost:8080`, and app hosts (`*.keyhome.app`, `*.keyhome.cm`, `*.neocraft.dev`). Keep `https://accounts.google.com` + `lh3.googleusercontent.com` for GSI/avatars. `next.config.ts` `images.remotePatterns` must include `img.clerk.com` and `*.googleusercontent.com` so chat avatars load via `next/image`. When adding a third-party SDK, extend `csp-allowlist.ts`—avoid duplicating origins only in `proxy.ts`.
  - **DuckDuckGo / privacy browsers**: content blockers block `play.google.com/log` and the One Tap iframe. This is browser-level and cannot be fixed in code. Always test One Tap in Chrome or Firefox with an active Google session.
  - **`unregistered_origin`**: if One Tap shows `[GoogleOneTap] Not displayed: unregistered_origin`, add `http://localhost` and `http://localhost:3000` to **Authorized JavaScript origins** in [Google Cloud Console](https://console.cloud.google.com/apis/credentials) for the OAuth Client ID.
  - **FedCM migration**: GSI emits a warning that `isNotDisplayed()` / `isSkippedMoment()` prompt notification methods will stop working when FedCM becomes mandatory. Non-blocking for now; revisit when Google announces enforcement date.
- **API rate limits**: CUSTOMER 300 req/min, AGENT with subscription 500 req/min, AGENT without subscription 300 req/min, ADMIN unlimited, guest 60 req/min.
- **Token refresh**: `POST /api/v1/auth/refresh` — rotates the current Sanctum token (delete old → create new), preserves login-context prefix (owner/client). `AuthController::refresh()`.
- **Session idle timeout** (frontend): `SessionTimeoutGuard` (`src/components/session/`) uses `useIdleTimeout`. After **15 min idle** → modal (**60 s** countdown); « Prolonger la session » appelle `/auth/refresh`; « Se déconnecter » appelle `logout()`. Monté **par panneau** sous le bon thème MUI — `(dashboard)/layout.tsx` (rose) et **`OwnerLayoutClient`** sous `OwnerThemeProvider` (teal) ; **pas** dans `providers.tsx`, pour que la modale reprenne `primary.main` du panneau.

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
- `useNetworkStatus` — online/offline via `navigator.onLine` + events; défaut SSR `true`; consommé par `SectionState` pour copy hors-ligne.

### Frontend Shared Components (`keyhome-frontend-next/src/components/ui/`)
- `CityAutocomplete` — reusable city autocomplete with debounced search, shared visual config. Props: `value`, `onChange`, `label`, `placeholder`, `size`, `sx`, `required`, `error`, `helperText`, `disabled`. Available for progressive adoption across 13+ consumers.
- `SectionBoundary` / `SectionErrorFallback` — périmètre d’erreur React par section (réessai, FR). `SectionState` — loading / erreur / vide avec prise en compte hors-ligne.

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
`SendNewsletterCampaignJob`, `SendNewsletterEmailJob`, `SendSearchAlertDigestJob`,
`SendSearchAlertInstantNotificationJob`, `SendSearchAlertFcmJob`.

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
  - `confidentialite/`, `conditions/` — politique de confidentialité et CGU ; styles partagés via `confidentialite/legal.module.css` ; libellé « dernière mise à jour » dans `src/lib/legal-documents.ts`.
- `src/components/` — shared UI components.
  - `ui/` — base primitives (shadcn-style + MUI hybrids).
  - `owner/` — landlord-specific components (`AdForm`, `AdFormPhotos`, `AdFormTour`, etc.).
  - `landing/` — marketing landing page components.
  - `surveys/` — survey form components (`SurveyForm`, `QuestionRenderer`).
  - `payment/` — payment history, checkout components.
  - `trust/` — TrustScore components (`TrustScoreBadge`, `TrustScoreSection`, `TrustScoreConsentModal`).
- `src/services/` — 23 service files calling `/api/v1/` endpoints.
- `src/hooks/` — hooks partagés (`useSearchFilters`, `useAuthActions`, `useNetworkStatus`, etc.).
- `src/theme/` — `tokens.ts` (design tokens), `theme.ts` (MUI theme), `ownerTheme.ts` (bailleur).
- `src/types/` — TypeScript interfaces mirroring backend models.
- `src/tests/` — Vitest unit tests.
- `e2e/` — Playwright end-to-end tests.
- `.storybook/` — Storybook config. Uses `register-next-config.cjs` (CJS, `require()` intentional).

### Thème & retours paiement (May 2026)
- **`ThemeProvider`** — préférence persistée `localStorage` `kh_theme_choice` : `system` \| `light` \| `dark` ; `useThemeMode()` expose `choice` et `setChoice`. Pages **Paramètres** client et bailleur : `ToggleButtonGroup` Clair / Sombre / Système.
- **Retour après paiement** — `src/lib/payment-return.ts` : `rememberPaymentOriginPath()` avant redirection Flutterwave (`creditsService.purchase`, `paymentsService.flutterwaveInitiate`) ; `consumePaymentReturnPath(fallback)` sur les pages callback pour retrouver la page d’origine (`sessionStorage` `kh_payment_return_path`).

### Landing polish & résilience UI (May 2026)
- **Landing publique** (`src/components/landing/*`, `/`) — repasse UX/a11y : hiérarchie, espacements, tokens (`tokens.ts`), CTAs qui pointent vers des routes joignables (ex. bailleur → `/owner/login`), FAQ (`aria-expanded` / `aria-controls`), nav mobile (`role="dialog"`), pied de page et newsletter (validation email, états d’erreur), `.hero-section` min-heights responsives dans `globals.css`. Tests Vitest : `src/tests/components/NewsletterSection.test.tsx`.
- **Sections isolées** — `SectionBoundary` + `SectionState` + `useNetworkStatus` pour états chargement / erreur / hors-ligne par bloc (pattern appliqué sur `AdDetailClient` pour cartographie, quartier, avis, etc.).
- **`/nearby`** — même stratégie que `/search` : import **dynamique** de `mapbox-gl` au montage de la carte (bundle initial plus léger sans mode liste).
- **`useServerAutoSave`** — après échec API, remplit `lastError` avec `getLaravelApiErrorMessage()` (`src/lib/api-errors.ts` : `message`, champs `errors` Laravel, `debug.message` si présent) ; `AdFormWizard` affiche le détail sous le bouton brouillon + tooltip ; effacement au prochain succès ou via `clearSavedAt()`.
- **Erreurs API owner (annonces)** — mutations sur `(owner)/owner/ads/*` et liste `owner/ads/page` utilisent `getLaravelApiErrorMessage` pour snackbars (plus de messages génériques seuls).
- **`bootstrap/app.php`** — handler JSON 500 API : si `APP_DEBUG=true`, ajoute `debug` (`exception`, `message`, `file`, `line`) en plus du message public ; le front peut afficher `debug.message` via l’helper ci-dessus.
- **`public/sw.js`** — entrée **cache-first réseau** pour les GET `CACHEABLE_OWNER_PATHS` traitée **avant** le filtre same-origin → cache offline utile lorsque l’API est **cross-origin** (nécessite CORS permissif pour GET depuis l’origine du PWA). Version incrémentée (`VERSION`) après changement comportement SW.
- **Favoris invité** — `FavoritesProvider` : à la transition `authenticated → guest`, réhydrate depuis `localStorage` (`readLocal()`) au lieu de laisser `[]` désynchronisé.
- **Navigation bailleur** — libellé **« Tableau de bord »** dans `OWNER_NAV_ITEMS` / `OWNER_SIDEBAR_NAV_ITEMS` / `OwnerSidebar` ; **`aria-current="page"`** sur l’entrée active ; **`OwnerNavbar`** : bouton « Nouvelle annonce » visible lorsque la barre mobile est affichée (branche `{!isMobile && …}` supprimée).
- **Shell owner (Mai 2026, correctifs audit)** — FAB « nouvelle annonce » : `shouldShowOwnerQuickCreateFab` vrai **uniquement** sur `/owner/ads` (regression corrigée). **`OWNER_NAV_ITEMS`** aligné sur la sidebar (Messages, Mon équipe, Sécurité, ordre métier) ; **`OWNER_SIDEBAR_NAV_ITEMS`** = alias de la même liste. **`OwnerManifestSwitch`** respecte les `meta theme-color` clair/sombre (teal / teal foncé). **Profil bailleur** : clés TanStack `active-survey-owner` / `survey-has-answered-owner` alignées sur `OwnerLayoutClient`. **`ShareAdButtons`** : URL absolue passée telle quelle (pas de double origine).
- **Owner shell** — `handleSurveyPostponed` mémoïsé (`useCallback`) pour limiter les re-renders de `SurveyPromptOrBanner`.

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
| `NEXT_PUBLIC_SITE_URL` | **SEO / Open Graph**: public site origin (canonical, `metadataBase`, sitemap, `robots.ts`). Priority over `NEXT_PUBLIC_APP_URL`; on Vercel, `VERCEL_URL` is used if unset. Fallback `https://keyhome.app`. Helpers: `src/lib/site-url.ts` (`getSiteOrigin`, `absoluteUrl`, `absoluteAssetUrl`). Tests: `src/tests/lib/site-url.test.ts`. |
| `NEXT_PUBLIC_GOOGLE_SITE_VERIFICATION` | Optional. Google Search Console → HTML tag: paste **only** the `content` value. Injected in root `layout.tsx` via `src/lib/seo-verification.ts`. |
| `NEXT_PUBLIC_BING_SITE_VERIFICATION` | Optional. Bing Webmaster Tools → Meta tag (`msvalidate.01`): paste **only** the `content` value. |

### SEO, canonicals & GEO (frontend, May 2026)
- **Centralisation** — `metadataBase`, canonicals, JSON-LD (`JsonLd`, annonces, villes, recherche, blog, comparaison), OG/Twitter images via `absoluteUrl` / `absoluteAssetUrl` pour éviter les URL relatives incorrectes sur crawl / partage. **Devise** : `BRAND_TAGLINE` / `BRAND_TITLE_WITH_TAGLINE` dans `src/lib/brand.ts` (métadonnées globales, JSON-LD Organization/WebSite, image OG dynamique, manifests PWA).
- **Search Console / Bing** — Après déploiement sur l’URL canonique (`NEXT_PUBLIC_SITE_URL`), définir les env de vérification, redéployer, puis GSC → **Sitemaps** : `https://<domaine>/sitemap.xml` ; suivre couverture, requêtes et CWV. Opérationnel, pas une garantie de classement.
- **Maillage interne** — Footer landing : colonne « Guides & villes » (`/immobilier/*`, `/type-bien/*`, `/comparaison`, `/nearby`) en complément des liens recherche.
- **Sitemap** — `src/app/sitemap.ts` inclut notamment `/search`, `/login`, `/register` (entrées marketing indexables).
- **GEO** — Pages annonce (`ads/[slug]`), hub ville (`immobilier/[ville]`) et recherche : coordonnées WGS84 quand disponibles ; métadonnées `geo.position` / `ICBM` et schémas `GeoCoordinates` / local business où pertinent.
- **`robots.ts`** — Sitemap absolu ; disallow des zones privées owner/messages/my/credits ; `/home` et `/nearby` indexables pour invités.

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
- **Chat iOS keyboard + audio URL (May 2026)**: `app/(owner)/layout.tsx` mirrors root viewport with `interactiveWidget: 'resizes-content'` and `viewportFit: 'cover'` so Safari/PWA resize the layout when the keyboard opens. The mobile chat `html/body` scroll lock (`DashboardLayout` + `OwnerLayoutClient`) is **skipped on iOS** via `isLikelyIosWebKit()` — locking `position: fixed` on `html/body` fought that viewport mode and made the whole page jump. Voice playback: `resolveChatAudioUrl()` (`src/lib/chat-attachment-audio.ts`) only returns `http(s)` URLs; bare R2 paths (`chats/...`) are rejected so `VoicePlayer` surfaces an error instead of a silent no-op.
- **Owner panel MUI teal (May 2026)**: `OwnerLayoutShell` wraps `OwnerLayoutClient` and `OwnerPWAInstallPrompt` with `OwnerThemeProvider`. The provider existed but was **never mounted**, so the root theme (customer pink) leaked into `/owner/login`, dashboard `primary.main`, contained buttons, and links. All routes under `(owner)` now resolve `palette.primary` to teal via `ownerTheme`.
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
- **Safe-area first paint (standalone)** — Inline script in `app/layout.tsx` (`src/lib/safe-area-init-inline.ts`) runs in `<head>` before body paint and sets `--kh-safe-area-top` / `--kh-safe-area-bottom` on `<html>` when WebKit/Android delay `env(safe-area-inset-*)` on cold load. `globals.css` and `Navbar` / `OwnerNavbar` / `bottomNavigationPwaShellSx` use `max(env(...), var(--kh-safe-area-*))` from `src/lib/safe-area-insets.ts`. `SafeAreaInsetBridge` in `providers.tsx` re-syncs after hydration (resize, rotation, visual viewport).

**Registration role-locking (prevent cross-role signup):**
- `src/lib/register-intent.ts` exports `registerUrlHasRoleLock`, `readStoredRegisterLock`, `writeStoredRegisterLock`, `clearStoredRegisterLock` — all backed by `sessionStorage` key `kh_register_role_locked`.
- **Owner flow**: `/owner/register` writes both `'agent'` role and the lock flag, then redirects to `/register`. The register page detects the lock and replaces the `ToggleButtonGroup` with a read-only "Propriétaire / Bailleur" badge — the user cannot switch to customer.
- **Customer flow**: `/login` links to `/register?lock=1`. The register page reads `?lock=1`, writes the lock flag, strips the URL, and shows a read-only "Particulier" badge.
- **Direct access** (`/register` with no params): no lock — both roles selectable as before (desktop/browser use case).
- Lock is cleared in `handleSubmit` alongside `clearStoredRegisterAccountRole()`.

**Customer side nav:** "Devenir hôte" menu item permanently removed from `NavDrawer.tsx`.

**PWA / drawer alignment (May 2026):** `keyhome-frontend-next/src/lib/navVisualMetrics.ts` unifies **24px** icon glyphs and list icon columns for client `NavDrawer` (invité + compte + quick nav), `bottomNavigationPwaShellSx` (SVG + `<img>`), standalone `BottomNav` / `OwnerBottomNav` (brand marks resized to 24px; removed owner tab override that forced **1.75rem** on Annonces), and desktop `OwnerSidebar` (no more `fontSize="small"` mix).

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
- **`AdFormLivePreview`** — aperçu **temps réel** : colonne sticky desktop **large** (`flex: 1 1 0%`, `minWidth` 420–520px) à côté du formulaire (colonne formulaire ~480–540px) ; **FAB + `Drawer` mobile** ; galerie principale + bandeau de miniatures (ordre = formulaire), prix / transaction / type / méublé (attribut) / chips boost & visite 360°, titre, adresse + ville/quartier, description (placeholder tant que vide), bloc **Conditions & charges** (caution texte ou montant formaté, durée, forfait / eau / élec / autres), équipements (jusqu’à 12 + décompte), **carte uniquement si le pin a été déplacé** (pas les coordonnées par défaut — constantes `AD_FORM_MAP_DEFAULT_LAT/LNG` dans `ad-form/types.ts`, alignées sur `AdFormMapLocation` / `initialValues`), proximité, encart annonceur. Framer : easing out-quint `cubic-bezier(0.22, 1, 0.36, 1)`.
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

**Owner panel — enterprise pass (Mai 2026)** — refonte transverse de 8 chantiers (bio, avis, abonnements, boost, contrats, finances, profil, paiements).

**Boost — recherche, expiration, exposition publique :**
- **`AdSearchController` (Meilisearch + fallback Eloquent)** : `boost_score:desc` est désormais **toujours** injecté **avant** le tri demandé (sauf `_geoPoint` où la proximité prime). Avant, un `sort=created_at` (défaut) plaçait les annonces boostées au même rang que les non boostées → les annonces sponsorisées **ne remontaient pas**. Idem dans le fallback Eloquent (`orderByDesc('boost_score')` puis le tri demandé).
- Nouvelle commande `app:expire-boosted-ads` (`ExpireBoostedAds.php`) planifiée **toutes les heures** dans `routes/console.php`. Sweep `is_boosted/boost_score` quand `boost_expires_at` est passé — sans cette tâche, la BDD restait incohérente avec `Ad::isBoosted()` qui repassait `false` à l'exécution mais laissait les colonnes intactes (impact index Meilisearch + filtres admin).
- **`AdResource`** expose désormais `is_boosted`, `boost_expires_at`, `boost_score` (via `Ad::isBoosted()` qui re-vérifie l'expiration). Permet le badge **"★ Sponsorisé"** sur `AdCard.tsx` (top-left, sous la pastille comparator).
- Tests : `tests/Feature/AdSearchBoostOrderingTest.php` (boost devant created_at + boost devant price asc), `tests/Unit/ExpireBoostedAdsCommandTest.php` (cron sweep).

**Bio publique — éditeur léger Markdown + compteur :**
- Migration `2026_05_04_195013_extend_user_bio_to_2000_chars.php` passe `users.bio` de **VARCHAR(500) → VARCHAR(2000)**. `UserRequest` validation `max:500 → max:2000`.
- `src/components/owner/PublicBioEditor.tsx` : éditeur **TipTap** (gras, italique, titres, listes, liens) avec persistance **Markdown** via `markdownLightToHtml` / `htmlToMarkdownLight` ; **compteur** avec alerte visuelle proche de la limite.
- Renderer Markdown : `src/lib/markdown-light.ts` (`markdownLightToHtml`) — dépendance zéro, échappe tout ce qui n'est pas dans la whitelist (gras / italique / `<h3>` / listes / liens `https`/`mailto` avec `rel="noopener noreferrer nofollow"`). Aucune dépendance npm ajoutée. Tests Vitest (`src/tests/lib/markdown-light.test.ts`, 8 cas dont sécurité XSS).
- Page `/owner/profile` : remplace `TextField` brut par `PublicBioEditor` en mode édition + rendu HTML sanitisé via `markdownLightToHtml(user.bio)` en mode lecture.

**Subscriptions — refonte complète frontend :**
- **Bug parsing** : la page lisait `data?.data` alors que l'API renvoie `{has_subscription, subscription, stats}` → l'abonnement actif n'apparaissait **jamais** côté Next ; les prix étaient affichés à 0 FCFA car `plan.price` n'existe pas (`price_monthly` / `price_yearly`). **Refonte** : `subscriptions.service.ts` aligné sur `SubscriptionResource` / `SubscriptionPlanResource` ; `subscribe()` / `cancel()` / `toggleAutoRenew()` ajoutés.
- Page `/owner/subscriptions` : toggle **Mensuel / Annuel**, carte "abonnement actuel" avec switch `auto_renew`, bouton **Annuler** (avec dialog de confirmation + raison facultative — l'abonnement reste actif jusqu'à `ends_at`), bouton **Souscrire** branché → POST `/subscriptions/subscribe` → `window.location.assign(payment_url)` (Flutterwave) après `rememberPaymentOriginPath()` pour retomber sur `/owner/subscriptions` au callback.

**Avis owner — réponse + KPI + filtres :**
- `ReviewResource` expose `is_verified`, `owner_response`, `owner_responded_at` (3 champs étaient en BDD via `2026_03_23_173944_add_trust_fields_to_reviews_table` mais pas sérialisés).
- Page `/owner/reviews` rebâtie : **KPI block** (note moyenne large, distribution étoiles 1-5 avec `LinearProgress`), filtres **Tous / Sans réponse / Répondus** + filtre par note (5 niveaux), **bouton Répondre** par avis (dialog avec compteur 1000 chars), affichage de la réponse propriétaire dans un encart accentué teal sous le commentaire.
- Service : `ownerService.respondToReview(reviewId, response)` → `POST /reviews/{id}/respond`.

**Lease contracts — régénération PDF + signature JSON fix :**
- **`SignatureController::show`** envoyait `{data, contract}` plat alors que `/sign/[token]/page.tsx` lisait `data.request.contract` → **page "Lien invalide ou expiré" systématique** dès qu'un locataire cliquait sur le lien email. Fix : envelop `request.contract` (canonique) + conserve les anciennes clés `data` / `contract` pour les clients mobiles.
- **`LeaseContractController::update`** régénère désormais le PDF après chaque modification (rent / dates / parties / conditions) via `LeaseContractService::regeneratePdf()` qui re-render avec les données courantes du contrat + relations ad/quartier/ville, swap `pdf_path`, supprime l'ancien fichier (best-effort). Avant, le PDF téléchargé restait désynchronisé du texte affiché.

**Finances — pagination expenses :**
- `ExpenseController::index` envoie maintenant `{data, meta, links}` (paginator brut Laravel exposait `current_page`/`last_page` à la racine, jamais `meta` → la page `<Pagination>` ne s'affichait pas même avec >20 lignes).

**À faire (chantier reporté) :**
- Income réel : pas de modèle `Payment` de type loyer encaissé ; `profitLoss` sum sur `lease_contracts.monthly_rent` est **trompeur** (multiples contrats, contrats historiques). Décision produit requise (créer un type `RENT` + workflow encaissement, ou un journal manuel).
- Lease lifecycle complet : `status` enum (`draft / active / expired / terminated / archived`), endpoints `renew`, `terminate`, `archive`. Hors scope cette session.
- E-signature légalement contraignante : `signature_hash`, IP + device, audit log dédié (pour l'instant `LeaseSignatureRequest::sign` met juste `status=signed`).

### Owner draft mode — completion pass (Mai 2026)

La fonctionnalité est complète et fonctionnelle.

- **Bug critique base de données** : la migration `2026_03_08_154604_update_ad_status_check_constraint` listait `available, reserved, rent, pending, sold, declined` **sans `'draft'`**, alors que tout le pipeline draft (`AdController::store(is_draft=1)`, `CreateAd::execute(isDraft: true)`, `AdStatusController::publish/autosave`, `AdFormWizard.useServerAutoSave`) repose sur `AdStatus::DRAFT`. Sur toute base où la check constraint était réellement appliquée (CI, fresh DB), **chaque écriture de brouillon retournait `SQLSTATE[23514] ad_status_check`** — silencieusement transformé en 500 ou en rollback côté API. **Fix** : nouvelle migration `2026_05_04_193135_update_ad_status_check_constraint_add_draft.php` qui rétablit le bon set de valeurs (`up()` ajoute `'draft'`, `down()` revient à l'ancien set).
- **Parité auto-save** : `AdFormWizard.autoSaveData` ne sérialisait qu'un sous-ensemble des `AdFormValues` ; tout ce qui n'était pas envoyé restait uniquement en mémoire navigateur tant que l'utilisateur n'avait pas cliqué « Enregistrer le brouillon ». Désormais la snapshot inclut **tous** les champs supportés par `AdStatusController::autosave` : `charges_montant_forfait`, `charges_eau`, `charges_electricite`, `charges_autres`, `distance_main_road_m`, `distance_shops_m`, `distance_transport_m`, `distance_school_m`, `distance_hospital_m`, et **`attributes`** (équipements). Médias (photos / panoramas / PDF) restent volontairement hors auto-save — communiqué via un helperText dédié sous le bouton.
- **Backend `AdStatusController::autosave`** :
  - Validation alignée avec `AdRequest` (PUT) : `charges_eau` / `charges_electricite` passent de `boolean` à `numeric|min:0` (montants en CFA, conformes au cast `integer` du modèle), `deposit_amount` / `minimum_lease_duration` redeviennent `string|max:50` (conforme à l'usage `'2 mois de caution'`), `transaction_type` accepte uniquement `location|vente`, distances bornées à `max:99999`.
  - **`attributes`** : `array|max:50` avec `Rule::exists('property_attributes', 'slug')` filtré sur `is_active=true` (mêmes règles que création/PUT) ; persistance via `array_unique(array_filter)`. `[]` envoyé volontairement vide la liste (cas « toutes les chips désélectionnées »).
  - **`latitude`/`longitude`** : convertis en PostGIS `Point` via `GeoLocation::fromArray()` ; un seul des deux → `location` non écrasé (préserve la coordonnée précédente sur autosave partiel).
  - `is_boost_requested` reste **frontend-only** comme documenté dans `CreateAd` — non persisté.
- **Service `adsService.autosaveDraft`** : signature étendue à `string | number | boolean | string[] | null` ; le hook `useServerAutoSave` envoie les arrays en JSON natif. `onCreateDraftCb` (premier save = POST `is_draft=1` multipart) sérialise désormais les arrays en `attributes[0]=…&attributes[1]=…` et **skip** les arrays vides pour éviter d'écraser des valeurs serveur tant que l'utilisateur n'a pas touché les chips.
- **UX wizard** : helperText permanent sous le bouton brouillon (« Texte et infos enregistrés automatiquement. Photos, visite 360° et PDF : utilisez « Enregistrer le brouillon ». ») + libellé orange existant `Brouillon · Sauvegarde auto indisponible (connexion ?)` lorsque `useServerAutoSave.lastError` est posé.
- **Tests** : `tests/Feature/AdDraftAutosaveTest.php` (8 cas, 31 assertions) couvre champs étendus, `location` complet vs partiel, `attributes` valides / inactifs / vides, ad non-draft → 422, accès non-propriétaire → 403.

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

#### Owner QR & printables (Mai 2026 — restauré après rollback accidentel)
- **Backend**: `App\Services\QrCodeService`, `App\Http\Controllers\Api\V1\QrCodeController`, dépendance directe `chillerlan/php-qrcode`. URLs publiques = `config('app.frontend_url')` + chemins `/ads/{slug}` et `/bailleurs/{username}` avec paramètres UTM (`utm_source=keyhome`, `utm_medium` = `qr` | `placard` | `visitcard` | `visitcard_preview`, `utm_campaign=owner_share`).
- **Routes** (middleware `auth:sanctum`, `owner.role`, `panel.role:owner`, `token.role:agent`) : `GET /api/v1/my/ads/{ad}/qr-code` (JSON), `GET …/qr-code/image` (PNG), `GET …/placarde` (PDF A5) ; `GET /api/v1/my/profile/qr-code`, `…/qr-code/image`, `…/business-card`, `…/business-card/preview` (PDF carte + aperçu filigrané).
- **Vues PDF** : `resources/views/pdf/ad-placarde.blade.php`, `business-card.blade.php`, `business-card-preview.blade.php`.
- **Frontend** : `src/components/owner/QrCodeDialog.tsx`, méthodes dans `owner.service.ts`, helpers `src/lib/qr-public-links.ts` ; entrées **Mes annonces** (menu actions) et **Profil bailleur** (encart « QR code & carte de visite »).
- **Tests** : `tests/Feature/QrCodeTest.php`, `keyhome-frontend-next/src/tests/lib/qr-public-links.test.ts`.

#### Admin Report PDF / CSV (dashboard & rapports)
- Template `resources/views/pdf/admin-monthly-report.blade.php` rebranded: teal (#0d9488) → brand #F6475F throughout (header gradient, section titles, highlights).
- **Async queue (Mai 2026)** : les exports lourds du tableau de bord admin ne bloquent plus la requête HTTP. `ExportActionsWidget` (CSV + PDF métriques), la page **Rapports** (`ScheduledReports` : utilisateurs, annonces, paiements) et **Export CSV des réponses** sur `SurveyResource` dispatchent `ProcessAdminAsyncExportJob` ; le fichier est écrit sur le disque `local` (`admin-queued-exports/{user_id}/`), une ligne `admin_queued_exports` est créée, et une **notification Filament** (base de données) contient un lien signé vers `GET /downloads/admin-asynchronous-export/{export}` (auth + signature, TTL 24 h). Nettoyage : modèle `AdminQueuedExport` **Prunable** via `model:prune` (déjà planifié quotidiennement). Les **ExportAction / ImportAction** Filament sur les ressources restent sur les jobs natifs `filament/actions`.

## Chat System — Bug Fixes & UI Redesign (Session 3)

### Bug Fixes
- **Bug A — PusherJS v8 auth 404**: `src/lib/echo.ts` — replaced deprecated `authorizer` with `channelAuthorization.customHandler` (PusherJS v8 breaking change).
- **Bug B — Hydration mismatch**: `src/hooks/usePasskey.ts` — replaced `useMemo(() => isWebAuthnSupported(), [])` with `useState(false) + useEffect` so server and client both start with `false`; client updates after mount.
- **Bug C — Avatar URLs**: `app/Events/Chat/MessageSent.php`, `app/Http/Resources/Chat/ConversationResource.php`, `app/Http/Resources/Chat/MessageResource.php` — added `resolveAvatarUrl()` helper that mirrors `User::getFilamentAvatarUrl()` logic (checks `http` prefix, then `Storage::disk()->url()`). Added `read_at`/`deleted_at` to broadcast payload.

### MUI X Chat Migration (Apr 2026)

Chat UI replaced with `@mui/x-chat` (v9.0.0-alpha.1) — adapter-driven ChatBox component.

**New files:**
- **`src/lib/keyhome-chat-adapter.ts`** — `ChatAdapter<string>` implementation bridging Laravel API + Echo/Pusher WebSockets. Handles `listConversations`, `listMessages`, `sendMessage`, `setTyping`, `markRead`, `subscribe`. Message dedup via `recentlySentIds` Set. Optimistic→confirmed swap via `message-removed` + `message-added` events. Lazy per-conversation channel subscriptions. Presence via `online-users` channel. Never calls `echo.leave()`.
- **`src/lib/chat-locale-fr.ts`** — French locale for MUI X Chat (`ChatLocaleText` partial). All UI strings in `fr_FR`.
- **`src/components/chat/KeyHomeChatBox.tsx`** — Themed wrapper. Exports `KeyHomeChatBox` (client pink) and `OwnerChatBox` (owner teal). Wires adapter, `currentUser`, French locale, accent `sx` overrides, URL-driven `onActiveConversationChange`, attachment config (JPEG/PNG/WEBP/GIF/PDF/DOC, 20 MB max, 5 files). **Height strategy:** `Box(flex:1, position:relative)` → `Box(position:absolute, inset:0)` → `ChatBox(height:100%)`. The absolute-positioning provides a definite CSS height context for the ChatBox root (which has `height: 100%` baked into its styled component). This bypasses the flex-computed-height-as-definite-height browser inconsistency.

**Updated pages (simplified — ChatBox handles list + thread layout internally):**
- `/messages/page.tsx` → `<KeyHomeChatBox />`
- `/messages/[uuid]/page.tsx` → `<KeyHomeChatBox initialActiveConversationId={uuid} />`
- `/owner/messages/page.tsx` → `<OwnerChatBox />`
- `/owner/messages/[uuid]/page.tsx` → `<OwnerChatBox initialActiveConversationId={uuid} />`

**Architecture decisions:**
- Adapter-driven (uncontrolled mode) — ChatStore manages all state; adapter handles data loading + WS subscriptions.
- Message role mapping: own = `'user'` (right-aligned), other = `'assistant'` (left-aligned). This is a rendering convention for P2P chat.
- `sendMessage` returns an immediately-closed `ReadableStream` (no AI streaming). After API confirms, emits `message-removed` (local ID) + `message-added` (server UUID) to swap optimistic with confirmed.
- WS dedup: `recentlySentIds` Set prevents duplicate add when own message arrives via broadcast.
- `ChatNotificationListener` kept in layout for toast notifications + badge updates (operates on TanStack Query cache, no conflict with ChatStore).

**Preserved files (still used by adapter / layout):**
- `src/lib/chat-api.ts` — API functions
- `src/lib/echo.ts` — Echo singleton
- `src/components/chat/chat-theme.ts` — ChatTheme type + CLIENT_THEME / OWNER_THEME
- `src/components/chat/ChatBadgeIcon.tsx` — Navbar unread badge
- `src/components/chat/ChatNotificationListener.tsx` — Global WS toast + cache sync

**Legacy components (superseded by ChatBox, can be removed):**
- `ConversationList`, `ConversationItem`, `ChatWindow`, `ChatHeader`, `MessageBubble`, `MessageInput`, `TypingIndicator`, `OnlineStatus`, `ReplyPreview`, `AttachmentPreview`
- `ChatPageWrapper` (no longer used — KeyHomeChatBox has its own height wrapper)
- `useChat.ts`, `useConversations.ts`, `usePresence.ts`, `useTypingIndicator.ts`

### UI Redesign — Theme System
- **`src/components/chat/chat-theme.ts`** — new file. Exports `CLIENT_THEME` (pink `#F6475F`) and `OWNER_THEME` (teal `#0D9488`) with `accent`, `accentHover`, `accentLight`, `accentLighter` fields. All chat components accept optional `theme?: ChatTheme` prop (default `CLIENT_THEME`).
- All 6 chat components updated: `ConversationItem`, `ConversationList`, `ChatHeader`, `OnlineStatus`, `MessageBubble`, `MessageInput`, `ChatWindow`.
- Owner pages pass `OWNER_THEME`; client pages pass `CLIENT_THEME` explicitly.

### Layout Fixes
- **`ChatPageWrapper`** — added `ownerLayout` prop. When `true`, uses `flex:1 + minHeight:0 + overflow:hidden` instead of fixed `calc(100dvh - …)` height (owner layout is already a flex child).
- **`OwnerLayoutClient`** — on mobile: hides `OwnerBottomNav` on all `/owner/messages/*` pages; hides `OwnerNavbar` on conversation detail pages. Content box switches to `flex column + overflow:hidden` on messages pages.
- **`DashboardLayout`** — on mobile: hides `Navbar` + `BottomNav` on `/messages/[uuid]` pages for immersive full-screen chat.

### Presence
- **`src/components/chat/GlobalPresenceChannel.tsx`** — new component. Joins `online-users` presence channel for the duration of the session so the user appears online even when not in a chat window. Mounted in both `DashboardLayout` and `OwnerLayoutClient` alongside `FcmRegistrar`.

### Enterprise Audit Fixes (Session 4 — 9 bugs)

1. **Security — `disconnectEcho()` never called on logout** (`useAuthActions.ts`): After logout, the Echo singleton (WebSocket) stayed alive with the previous user's auth credentials. Fix: call `disconnectEcho()` immediately in step 3 of the logout function (before `clearSession()`). Root cause: `disconnectEcho` was exported but never imported by `useAuthActions`.

2. **Race condition — `loadMore` duplicate requests** (`useChat.ts`): `handleScroll` fires many times/second; `void loadMore()` was called each time `scrollTop < 50`. Since `hasMore` is React state and only updates after the response, multiple concurrent `fetchMessages` calls fired with the same cursor, prepending duplicate messages. Fix: added `isLoadingMoreRef` ref guard that short-circuits if a load is already in progress.

3. **UX — `ConversationResource` missing `sender_id` in `last_message`** (`ConversationResource.php`): The conversation list API did not include `sender_id` in the `last_message` object. Without it, the frontend could not display "Vous : ..." for own messages. Fix: added `'sender_id' => $last->sender_id` to the `last_message` array.

4. **UX — `window.confirm` for archive** (`ChatWindow.tsx`): `window.confirm` is a blocking native dialog that looks broken in PWA standalone mode and is blocked in some browsers/iframes. Fix: replaced with an inline amber confirmation banner (`showArchiveConfirm` state) with "Annuler" / "Archiver" buttons themed per panel.

5. **a11y — ESC key doesn't close lightbox** (`AttachmentPreview.tsx`): The image lightbox had no keyboard escape handler. Fix: added `useEffect` that registers `keydown` listener for `Escape` when `lightboxOpen = true`, removes it on cleanup.

6. **Memory leak — long press timeout not cleaned on unmount** (`MessageBubble.tsx`): `longPressRef.current = setTimeout(handleLongPress, 500)` was never cleared if the component unmounted mid-touch. Fix: added `useEffect(() => () => { if (longPressRef.current) clearTimeout(longPressRef.current); }, [])`. Also fixed missing `useEffect` import.

7. **Perf — `handleSendMessage` deps included full `messages` array** (`useChat.ts`): `useCallback` with `[conversationUuid, user, messages]` caused the send callback to be recreated on every incoming message (propagating to `MessageInput`). Fix: extracted `messagesRef` (updated each render, never in deps) and replaced `messages.find(...)` with `messagesRef.current.find(...)`. Deps reduced to `[conversationUuid, user]`.

8. **Perf — Date groups computed inline on every render** (`ChatWindow.tsx`): The `groups` array was rebuilt from scratch on every render (state changes, re-renders from parent). Fix: wrapped in `useMemo([messages])`.

9. **UX — No "Vous :" prefix for own last messages** (`ConversationItem.tsx`): All messaging apps (WhatsApp, iMessage, Telegram) prefix the last message with "Vous :" when it was sent by the current user. Fix: imported `useAuth`, computed `isOwnLastMsg = lastMsg?.sender_id === user?.id`, prefixed `rawPreview` with `"Vous : "` when true. Depends on fix #3 (backend `sender_id` field).

## Known CI/CD Gotchas

### Backend GitLab CI
- **`build_image` (Dockerfile)**: `vendor/` is in `.dockerignore`. The `node-builder` stage stubs `vendor/filament/filament/resources/css/theme.css` before `npm run build` to satisfy Vite's `@import`. Do NOT remove this stub.
- **`build_image` (Dockerfile Alpine)**: `php-fpm-alpine` does NOT include the `shadow` package. `usermod`/`groupmod` are unavailable (exit 127). Use `deluser www-data 2>/dev/null; delgroup www-data 2>/dev/null; addgroup -g 1000 -S www-data && adduser -u 1000 -D -S -H -G www-data www-data`.
- **`test_suite` (PostgreSQL)**: `pg_isready` without `-h` checks the Unix socket which becomes ready before TCP. Always use `pg_isready -U gitlab -h 127.0.0.1 -p 5432` in the wait loop.
- **`.dockerignore`**: `readme/` is ignored to prevent Docker cache invalidation from documentation commits. `vendor/` and `node_modules/` are always excluded.

### Chat Polish Session 5 — Console Bugs + UX (Apr 2026)

**Bug fixes (frontend-only, 0 backend changes):**
1. **CSP R2 images blocked** — `img-src` / `connect-src` in `src/proxy.ts` and `next.config.ts` `remotePatterns` only had `*.r2.dev`; signed chat-attachment URLs use `*.r2.cloudflarestorage.com`. Added that domain.
2. **SW CORS errors** — `public/sw.js` intercepted cross-origin API requests via `isCacheableApi()` without an origin guard. When the frontend and API are on different origins (dev), the re-fetch from the SW context failed CORS, returning `Response.error()`. Fix: moved same-origin guard (`url.origin === self.location.origin`) **before** the cacheable-API branch. Bumped `VERSION` to `v6`.
3. **MUI Menu Fragment warnings** — `<Menu>` clones children for focus management; `<>…</>` Fragments are opaque. Fixed in `OwnerNavbar.tsx` and `owner/ads/page.tsx` by replacing Fragments with keyed arrays.
4. **WebAuthn 403** — The 403 is intentional (`RoleContextMismatchException`) when a passkey belongs to a different role context. Frontend now catches the `ROLE_CONTEXT_MISMATCH` code in `webauthn.service.ts` and throws a clear French error message surfaced by `usePasskeyLogin`.
5. **Recharts `width(-1)`** — `MiniMetricSparkline.tsx` used `height="100%"` on `ResponsiveContainer` inside a container that briefly had zero dimensions. Fix: pass explicit `height={CHART_H}` number and early-return an empty `Box` when `data.length === 0`.

**Chat UX improvements:**
6. **Instant message-view open** — Both `/messages/[uuid]` and `/owner/messages/[uuid]` now use `useQuery` with `initialData` pulled from the TanStack `['conversations']` list cache. Header renders on tick 0 from cached data; no more full-page spinner.
7. **Message skeleton** — `ChatWindow` loading state replaced spinner with 4-bubble skeleton placeholders (2 left, 2 right) with `animate-pulse`.
8. **Typing indicator faster** — `useTypingIndicator` debounce reduced 300→100 ms; `STOP_AFTER_MS` stays 2 s.
9. **Bubble polish** — Same-sender gap tightened to 1 px (`mt-px`), sender-change gap widened to 8 px (`mt-2`). Other's bubble shadow softened to `0 1px 2px rgba(0,0,0,0.04)`.
10. **Chat wallpaper** — Message list background changed from `bg-gray-50` to `#f7f7f8` for subtle contrast.
11. **Header** — Mobile padding tightened (`py-2`). Online dot now has a `animate-ping` pulse ring.
12. **Send button** — Hit target enlarged to 44×44 px on mobile (`p-3 md:p-2.5`, `minWidth/minHeight: 44`). Icon slightly bigger (`18px`).

**Files changed:** `src/proxy.ts`, `next.config.ts`, `public/sw.js`, `src/components/owner/OwnerNavbar.tsx`, `src/app/(owner)/owner/ads/page.tsx`, `src/services/webauthn.service.ts`, `src/components/owner/dashboard/MiniMetricSparkline.tsx`, `src/app/(dashboard)/messages/[uuid]/page.tsx`, `src/app/(owner)/owner/messages/[uuid]/page.tsx`, `src/components/chat/ChatWindow.tsx`, `src/components/chat/MessageBubble.tsx`, `src/components/chat/ChatHeader.tsx`, `src/components/chat/MessageInput.tsx`, `src/hooks/useTypingIndicator.ts`.

### Chat Production-Readiness Audit — Session 6 (Apr 2026)

**Security fixes:**
1. **Attachments injection** — `SendMessageRequest` had no per-item validation on `attachments.*` array. Arbitrary JSON (XSS payloads, fake signed_urls) could be stored in jsonb. Fix: added strict per-field rules for `url`, `signed_url` (must be valid URL), `original_name` (max 255), `mime_type` (whitelist), `size` (int, max 20MB), `type` (in:image,file). Max 5 attachments per message.
2. **Upload MIME at form level** — `UploadAttachmentRequest` accepted any MIME; validation happened only in `AttachmentService` after full upload. Fix: added `mimes:jpeg,jpg,png,webp,pdf,doc,docx` to form-level rules so invalid files are rejected before processing.
3. **`last_message_preview` plaintext** — Verified already `null` (no plaintext stored). `ConversationResource` builds inbox preview from **`previewMessage`** hydrated in `ConversationService` (latest non-deleted row; batched DISTINCT ON on PostgreSQL) — not relying on `latestMessage` FK alone. TanStack inbox list cache merges every **`message.sent` in `ChatNotificationListener`** so previews stay fresh off the Messages screen.

### Session — Chat enterprise hardening pass (Mai 2026, après audit complet)

Findings de l'audit chat (≈15 items) traités intégralement.

**Backend** :
- `MessageService::send()` acquiert maintenant **`lockForUpdate()`** sur `conversations` au début de la transaction → race condition résolue : deux envois concurrents n'écrasent plus `last_message_id` / `last_message_at`.
- `MessageService::getHistory()` ajoute **`orderByDesc('id')`** comme tie-break du curseur → pagination stable même avec timestamps identiques.
- `ConversationService::findOrCreate()` :
 - **Refuse** explicitement `$tenantId === $landlordId` (self-conversation) en levant `ConversationNotAllowedException`.
 - **Catch `UniqueConstraintViolationException`** sur la création : si deux `POST /conversations` concurrents passent entre `SELECT` et `INSERT`, on relit le gagnant de la course au lieu de retourner un 500.
- `routes/api.php` chat : tous les `throttle:` lisent désormais `config('chat.rate_limits')` (`send_message`, `upload_attachment`, `set_typing`, `reaction`, `e2ee_identity_update`). `config/chat.php` complété avec les deux nouvelles entrées.

**Frontend** :
- `OwnerLayoutClient` passe `basePath="/owner/messages"` à `ChatNotificationListener` → la garde « déjà sur le fil » et les deep-links de toast pointent vers le bon panneau (était cassé : owner se retrouvait sur les toasts avec des liens client).
- `MessageInput::handleSend` snapshote `pending` avant `clearAllPending()` ; sur erreur d'envoi, restaure les pièces jointes (avec leurs descripteurs serveur) en plus du texte → plus de perte de travail.
- `MessageBubble::onTouchMove` annule le `longPressRef` quand l'axe se verrouille en `'y'` (scroll vertical) → le `ReactionPicker` ne s'ouvre plus pendant qu'on scrolle.
- `ReplyPreview` lit désormais `decrypted_body` pour les messages `is_client_sealed`, fallback `🔐 Message sécurisé` si déchiffrement indisponible. Distingue audio (`🎙 Message vocal`), image, fichier ; couleur du texte tokenisée (`theme.textSecondary`).
- `ConversationItem` preview liste : ordre de priorité sealed → text body → image / audio / file. Plus de preview vide pour les messages chiffrés.
- `useConversationsTyping` réutilise `selectConversationsForBackgroundWs` (cap 40) au lieu d'un cap interne 20 → couverture typing alignée avec couverture WS messages.
- `OnlineStatus` : « il y a 1 jour » (singulier) au lieu de « 1 jours » ; couleurs offline tokenisées via `theme.textMuted`.
- `AttachmentPreview` focus ring pour images : remplacement `focus:ring-[#F6475F]` hardcodé par `boxShadow` dynamique sur `theme.accent`.

**Voice notes vérifiés end-to-end** : `VoiceRecorder` (MediaRecorder + permission) → upload R2 (`AttachmentService::AUDIO_MIMES` couvre webm / mp4 / aac / ogg / wav, limite 5 MB) → `MessageInput::handleVoiceReady` → `useChat.handleSendMessage` infère `type='audio'` → `MessageService::send` valide `belongsToConversation` → `MessageSent` broadcast avec `attachments[].audio_duration_ms` + `audio_waveform_peaks` → recipient `AttachmentPreview` rend `VoicePlayer`. `SendMessageRequest` valide `audio_duration_ms` (100–120000 ms), `audio_waveform_peaks` (max 120, 0..1). RAS.

**Real-time vérifié** : `REVERB_HOST=localhost` côté backend (binding distinct via `REVERB_SERVER_HOST`), `NEXT_PUBLIC_REVERB_*` configurés côté frontend, `routes/channels.php` autorise tenant + landlord uniquement, `BroadcastServiceProvider` enregistre les routes. `Echo` singleton avec `customHandler` Bearer optionnel + cookie pour `/api/v1/broadcasting/auth`. `X-Socket-Id` injecté via Axios interceptor pour `->toOthers()`.

**Tests** : `tests/Feature/Chat/*` → **57 passed (137 assertions)**, `npx tsc --noEmit` clean, `npm run lint` 0 erreurs (35 warnings pré-existants), `vendor/bin/pint` clean.

**Risques acceptés / non-corrigés** :
- Service locator `app(EncryptionService::class)` dans `Message::getDecryptedBodyAttribute` et `app(AttachmentService::class)` dans `MessageResource` / `MessageSent` — Eloquent models / Resources / Events ne supportent pas le constructor DI ; pattern Laravel idiomatique conservé.
- E2EE : clé privée RSA dans `localStorage` — vulnérable XSS, **documenté** dans `chat-e2ee-crypto.ts`. Mitigé par CSP + Cookie consent.
- `MessageSent` broadcast : pour les messages **non scellés**, le corps déchiffré transite sur le canal privé (auth Sanctum + 2 participants seulement). E2EE optionnel pour confidentialité totale.
- Cap 40 conversations WS : utilisateurs avec plus de 40 fils non lus ratent les events temps réel sur les fils excédentaires ; mitigation = `staleTime: 30s` + `refetchOnWindowFocus` sur la liste qui rattrape au prochain focus.

**Performance fixes:**
4. **N+1 on conversation list** — `ConversationResource::unreadCountFor()` ran 1 COUNT query per conversation. Fix: `ConversationService::getConversationsForUser()` now uses `withCount(['messages as computed_unread_count' => ...])` with a `CASE WHEN` expression to pick `tenant_last_read_at` or `landlord_last_read_at`. `ConversationResource` reads `computed_unread_count` when available, falls back to `unreadCountFor()`.
5. **Missing composite index** — Added migration `2026_04_20_140000_add_composite_indexes_to_messages_table.php` for `(conversation_id, sender_id, status)` — used by `markAsRead` bulk UPDATE and unread count queries.

**Reliability fixes:**
6. **Optimistic send race** — `useChat.ts` `.message.sent` listener used prefix scan (`startsWith(OPTIMISTIC_PREFIX)`) to replace optimistic messages. If 2 rapid sends occurred, the WS event for msg#1 could replace msg#2's optimistic entry. Fix: simplified to rely on `toOthers()` (own messages never arrive via WS); duplicates filtered by exact UUID match.
7. **useConversations stale closure** — Dependency array used `conversations.length` (only re-ran when count changed, not content). Fix: compute stable `convUuids` string (sorted, joined) and use as dependency so WS subscriptions update when conversations change.
8. **Archive idempotency** — `ConversationService::archive()` now checks `$conv->status !== Archived` before writing, preventing wasteful no-op DB updates.

**Accepted risks:**
- `MessageSent` broadcasts decrypted body over WebSocket — **required** for real-time display. Channel auth (only 2 participants can subscribe) is the mitigation.
- `DB::raw` in `getConversationsForUser` interpolates `$user->id` — safe because it's always a UUID from the authenticated `User` model, never user input.

**Test results:** 775 passed, 0 failed. PHPStan [OK]. Pint [OK].

### Session 7 — Chat Audit Fix (Apr 2026)
Comprehensive audit of owner↔client messaging. 7 fixes:

1. **Unread cache stale on send** — `MessageService::send()` now calls `invalidateUnreadCache($recipientId)` via injected `ConversationService`. Previously only `markAsRead` invalidated cache → recipient's unread total was stale up to 30 s.
2. **`App.Models.User.{id}` channel removed** — Used `(int) $user->id === (int) $id` with UUID strings → both cast to `0` → **any authenticated user matched any channel**. Dead code (never subscribed to anywhere). Deleted.
3. **`reply_to_id` + soft-delete** — `SendMessageRequest` now uses `Rule::exists('messages', 'id')->whereNull('deleted_at')` so users cannot reply to soft-deleted messages.
4. **Broadcasting auth 403 flood** — `echo.ts` `customHandler` now short-circuits with error when `getAuthToken()` returns null (Clerk token not ready). Prevents 10+ useless POST `/broadcasting/auth` that always 403.
5. **Mobile chat height overflow** — `ChatPageWrapper` used `calc(100dvh - 56px)` but was nested inside flex parent chain that already handled height. Fixed: `DashboardLayout` uses `height: 100dvh` + `overflow: hidden` on messages pages; `PageTransition` changed `minHeight → height: 100%`; `ChatPageWrapper` uses `flex:1 + height:100%`.
6. **Dead `UserPresence` event** — `app/Events/Chat/UserPresence.php` was never broadcast anywhere (presence relies on Echo's native `join('online-users')` channel). Deleted.
7. **Stale comments aligned** — `useChat.ts` "debounced 300ms" → 100ms; `useTypingIndicator.ts` same; `UserTyping.php` "max 1 per 2s" → "max 30 per minute".

8. **`usePresence` destroyed shared presence channel** — `usePresence` hook called `echo.leave('online-users')` on cleanup, which tore down the global presence subscription owned by `GlobalPresenceChannel`. When user navigated away from a chat, they appeared offline to everyone. Fixed: `usePresence` now binds/unbinds directly on the Pusher channel object (not via Echo's `.here()`/`.joining()`/`.leaving()` wrappers) so it can clean up its own handlers without calling `echo.leave()`.

9. **`ChatNotificationListener` destroyed shared conversation channels** — `echo.leave()` in cleanup tore down subscriptions shared with `useConversations` and `useChat`. When the conversation list changed, the active chat window silently lost real-time updates. Fixed: uses direct Pusher `bind()`/`unbind()` on `echoChannel.subscription`.
10. **`useConversations` destroyed shared conversation channels** — Same `echo.leave()` bug. Fixed with direct Pusher `bind()`/`unbind()`.
11. **`useChat` destroyed shared conversation channels** — Same `echo.leave()` bug across 4 events (`message.sent`, `messages.read`, `message.deleted`, `user.typing`). Fixed with direct Pusher `bind()`/`unbind()`.

12. **Broadcasting auth 403** — The default `/broadcasting/auth` route uses the `web` middleware (session auth). The Next.js PWA sends a Sanctum Bearer token → `$request->user()` returned null → 403 on every channel subscription. Fixed: added `POST /api/v1/broadcasting/auth` in `routes/api.php` with `auth:sanctum` middleware, updated `echo.ts` `customHandler` endpoint to `/api/v1/broadcasting/auth`. The old web route remains for Filament panels (session auth).

**Audit corrections:** `GlobalPresenceChannel` is correctly mounted in both `DashboardLayout` and `OwnerLayoutClient` (audit finding was wrong).

**Echo channel ownership rule:** No hook/component may call `echo.leave()` on a shared channel. Only two legitimate `leave`/`disconnect` call sites exist:
- `GlobalPresenceChannel` → `echo.leave('online-users')` (on auth change / unmount)
- `disconnectEcho()` → `echo.disconnect()` (on logout — tears down entire WebSocket)
All other code (`usePresence`, `useChat`, `useConversations`, `ChatNotificationListener`) must use `echoChannel.subscription.bind()`/`unbind()` to add/remove their specific handlers.

**Broadcasting auth routes:**
- `POST /broadcasting/auth` — web middleware (session auth, used by Filament panels)
- `POST /api/v1/broadcasting/auth` — api middleware + `auth:sanctum` (Bearer when Sanctum PAT is in memory, **or** session cookie via `credentials: 'include'`, used by Next.js PWA)

**Echo private-channel auth (May 2026)** — Real-time broke for users authenticated **only** via Laravel session cookie (no in-memory Sanctum token): the old `customHandler` skipped `/broadcasting/auth` when `getAuthToken()` was null, so no private subscriptions. Fix: always `fetch` `/api/v1/broadcasting/auth` with `credentials: 'include'`; add `Authorization: Bearer` only for PAT-shaped tokens (`shouldUseBearerForBroadcastAuth` in `echo.ts` — omits Clerk-like JWTs so Sanctum does not treat them as PATs). Dev `console.warn` if `NEXT_PUBLIC_REVERB_APP_KEY` or `NEXT_PUBLIC_REVERB_HOST` is unset.

**Test results:** 776 passed, 0 failed. PHPStan [OK]. Pint [OK]. TypeScript `tsc --noEmit` clean.

### CDN Strategy (Apr 2026)

**Architecture:**
- Frontend (Next.js / Vercel) → Vercel Edge Network (automatic, no config) ✓
- Chat attachments (R2) → Cloudflare edge (automatic via R2 public bucket) ✓
- Backend API (Laravel VPS) → **Cloudflare reverse proxy** ← the improvement

**Chosen CDN: Cloudflare** (already have account via R2)
- Only CDN with PoPs in Abidjan, Dakar, Lagos, Nairobi covering CEMAC/UEMOA market
- Acts as reverse proxy for `api.keyhome.neocraft.dev` — zero infra change needed
- Free plan covers the use case

**Code changes made:**
- `app/Http/Middleware/CacheHeaders.php` — global API GET middleware (applied to all API routes via `appendToGroup`):
  - Authenticated: `private, no-store`
  - Public (default): `public, max-age=60, s-maxage=60, stale-while-revalidate=300, stale-if-error=3600`
  - Does NOT overwrite if `CdnCache` already set a header
  - `Vary: Accept-Encoding` only (not `Accept` or `Authorization`)
- `app/Http/Middleware/CdnCache.php` — per-route configurable TTL for reference data:
  - Usage: `->middleware('cdn.cache:3600')`
  - Sets: `public, max-age={ttl}, s-maxage={ttl}, stale-while-revalidate={ttl*24 capped 86400}, stale-if-error=604800`
  - Alias: `cdn.cache` registered in `bootstrap/app.php`

**Routes with cdn.cache applied:**
| Route | TTL | Reason |
|---|---|---|
| `GET /api/v1/cities` | 3600s | Reference data, rarely changes |
| `GET /api/v1/cities/{id}` | 3600s | Same |
| `GET /api/v1/quarters` | 3600s | Same |
| `GET /api/v1/quarters/{id}` | 3600s | Same |
| `GET /api/v1/ad-types` | 3600s | Same |
| `GET /api/v1/ad-types/{id}` | 3600s | Same |
| `GET /api/v1/property-attributes` | 1800s | Semi-static |
| `GET /api/v1/subscriptions/plans` | 1800s | Rarely changes |
| `GET /api/v1/credits/packages` | 1800s | Rarely changes |
| `GET /api/v1/stats/landing` | 300s | Aggregated, refreshed every 5min |
| `GET /api/v1/stats/testimonials` | 3600s | Stable content |

**Cloudflare setup (one-time, in Cloudflare dashboard):**

1. **Add site**: add `keyhome.neocraft.dev` (or `keyhome.app` for prod) to Cloudflare
2. **DNS record**: `api.keyhome.neocraft.dev` → A record → VPS IP → orange cloud (Proxied)
3. **SSL/TLS mode**: Full (strict) — Cloudflare ↔ origin uses HTTPS (Traefik handles TLS)
4. **Cache Rules** (Cloudflare dashboard → Caching → Cache Rules):
   - Rule 1: `uri.path contains "/api/"` AND `http.request.method eq "GET"` AND `not http.request.headers["authorization"] exists` → Cache TTL: Respect origin (`Cache-Control` s-maxage)
   - Rule 2: `http.request.method in {"POST" "PUT" "PATCH" "DELETE"}` → Bypass cache
   - Rule 3: `uri.path contains "/api/v1/broadcasting"` → Bypass cache
   - Rule 4: `uri.path contains "/api/v1/webhooks"` → Bypass cache
5. **Trusted proxy**: add Cloudflare IP ranges to `TRUSTED_PROXIES` env var (or use `*` if behind Traefik)

**Cache invalidation:** When admin changes cities/ad-types/plans, add `cache()->forget(...)` or use Cloudflare's Cache Purge API (`POST /zones/{zone_id}/purge_cache`). Currently no automatic invalidation — manual for now.

### Session Timeout Guard (Apr 2026 — bug fix + enterprise rewrite)

**Bug fixed: "Prolonger la session" disconnected the user instead of refreshing the token.**

Root cause (3 independent issues stacked):
1. **`/auth/refresh` missing from `AUTH_ROUTES` in `api.ts`** — when the refresh call returned a 401 (any network error), the Axios error interceptor dispatched `kh:auth-expired`, which caused `AuthProvider` to immediately wipe all user state **and** tokens. The `catch` block in the old `handleExtend` then also called `logout()`, causing a cascading double-logout.
2. **After a successful refresh**, `persistOwnerToken/persistClientToken` updated the module-level token variable but `AuthProvider`'s React `token` state remained stale (no `setToken()` call).
3. **No Page Visibility API support** — idle timer kept running when the tab was hidden; on return the countdown fired immediately even though the user was active elsewhere.

**Fixes:**
- `src/lib/api.ts` — added `/auth/refresh` to `AUTH_ROUTES`. A 401 on the refresh endpoint now **only** triggers the guard's own error path, not the global auth-expired event.
- `src/providers/AuthProvider.tsx` — added `refreshSession(): Promise<boolean>` to `AuthContextType` and implementation. Calls `/auth/refresh`, updates both the module-level slot (`persistOwnerToken/persistClientToken`) AND the `token` React state via `setToken()`. Returns `false` on failure — caller decides whether to force-logout.
- `src/hooks/useIdleTimeout.ts` — full enterprise rewrite:
  - **Page Visibility API**: on `visibilitychange → visible`, checks elapsed idle time vs `idleMs + countdownMs`. If fully elapsed → fires `onTimeout()` immediately. If in the warning window → shows countdown with correct remaining seconds.
  - **BroadcastChannel** (`kh:session-keep-alive`): when `extendSession()` is called, broadcasts `{ type: 'extend' }` to all tabs. Each tab subscribes and calls `resetIdleTimer()` on receipt — no duplicate warnings across tabs.
  - `warningActiveRef` prevents activity events from resetting the timer while the warning modal is open (only "Prolonger" button can dismiss it).
- `src/components/session/SessionTimeoutGuard.tsx` — uses `refreshSession()` from context (no direct token knowledge), adds `refreshError` state shown for 2 s before force-logout redirect.
- `src/components/session/SessionTimeoutModal.tsx` — added `refreshError?: boolean` prop. When true: replaces countdown content with an error `Alert` ("Session expirée, redirection…") and disables both buttons. Added spinner inside "Prolonger" button during async refresh. Added `disableEscapeKeyDown` to prevent user from accidentally dismissing the modal.

**Correct flow (after fix):**
1. User idle 15 min → modal appears with 60 s countdown
2. User clicks "Prolonger" → `POST /auth/refresh` (Bearer = current Sanctum token)
3. **Success** → new token persisted (module-level + React state) → `extendSession()` → idle timer reset → modal closes → all other tabs also reset via BroadcastChannel
4. **Failure (token expired)** → `refreshSession()` returns `false` → `refreshError=true` → error UI shown 2 s → `logout()` → redirect to correct login page (owner or client)

### Chat Display Fix — Session 7 (Apr 2026)

**Symptoms reported:** chat not scrollable; messages not instant (had to reload page); ChatHeader (participant profile) not visible on client side.

**Root causes & fixes:**

1. **`@mui/x-chat` is AI/LLM-only, not P2P** — `src/components/chat/KeyHomeChatBox.tsx` previously wrapped MUI X `<ChatBox>` whose `sendMessage` contract requires a `ReadableStream` of AI response chunks. P2P chat returns an empty stream, causing phantom assistant messages and broken UI states. Fix: complete rewrite of `KeyHomeChatBox` as a split-pane layout over the existing native components (`ConversationList` + `ChatWindow`). Desktop = 320 px sidebar + flex-1 thread; mobile = full-screen list OR window based on `initialActiveConversationId`. Resolves active conversation from TanStack cache first, falls back to `GET /conversations/{uuid}` for deep links. Files `lib/keyhome-chat-adapter.ts` and `lib/chat-locale-fr.ts` are now orphaned but kept (no runtime references).

2. **Height chain ambiguity → not scrollable, ChatHeader hidden** — `ChatPageWrapper` used `flex:1 + height:100%` together which is fragile; combined with `flex` direction confusion in nested wrappers, `ChatWindow`'s `flex-1 + min-h-0` chain didn't always get a definite height, so the messages list couldn't scroll and `ChatHeader` (`shrink-0`) was sometimes pushed out. Fix: in `KeyHomeChatBox`, replace `<ChatPageWrapper>` with `<div className="absolute inset-0 flex …">`. The DashboardLayout / OwnerLayoutClient already provide a `position: absolute, inset: 0` ancestor on messages pages, so anchoring the chat to `inset:0` gives a guaranteed definite height regardless of intermediate flex layers (`PageTransition`, etc.). Also added `relative` to `ChatWindow`'s root so the scroll-to-bottom button positions inside it.

3. **WebSocket subscription race → messages not instant** — `useChat.ts` and `useConversations.ts` read `(echoChannel as any).subscription` synchronously and bailed early when `undefined`, with no retry. While `laravel-echo`'s `PusherChannel` constructor sets `subscription` synchronously, in StrictMode / fast nav / freshly-recreated singleton (after `disconnectEcho()`) the underlying pusher channel can be momentarily missing or not yet subscribed, silently dropping events. Fix: race-safe `tryBind()` helper retries every 50 ms (max 1 s = 20 attempts) until `pusherCh` is available, then binds all handlers atomically. Cleanup cancels pending retries and only unbinds when bindings were applied. Pattern matches the proven `usePresence.ts`. Applied to both `useChat` (per-conversation) and `useConversations` (per-UUID in the list).

**Files changed:** `src/components/chat/KeyHomeChatBox.tsx` (full rewrite), `src/components/chat/ChatWindow.tsx` (added `relative`), `src/hooks/useChat.ts` (race-safe binding), `src/hooks/useConversations.ts` (race-safe binding).

### Chat Real-time Fix — Session 8 (Apr 2026)

**Root cause (macOS-specific):** `REVERB_HOST=0.0.0.0` in `.env` was used for **both** the Reverb server bind address AND the broadcasting driver's outbound connection target. On macOS, binding on `0.0.0.0` is correct (listens on all interfaces), but *connecting outbound* to `0.0.0.0` is undefined behavior and silently fails. The Laravel HTTP server caught the `\Throwable` in `MessageService::send()` and `ConversationService::markAsRead()`, so the broadcast was swallowed. Reverb never received the event; the frontend never got a WebSocket push. Users had to reload the page to see new messages.

**Fix:** Changed `REVERB_HOST=0.0.0.0` → `REVERB_HOST=localhost` in `.env`. The server bind address is controlled by `REVERB_SERVER_HOST` (default `0.0.0.0`, unchanged), so Reverb still listens on all interfaces. The broadcasting driver now connects to `http://localhost:8080` which always works on macOS.

**Secondary fix — `X-Socket-Id` header for `->toOthers()`:**
- Added `getEchoSocketId()` to `src/lib/echo.ts` — returns the Pusher socket ID without creating a new Echo instance.
- Added a synchronous Axios interceptor in `src/lib/api.ts` that attaches `X-Socket-Id: {socketId}` to every request when the Echo singleton is already connected. This enables Laravel's `->toOthers()` to correctly exclude the sender's socket from the broadcast, preventing the sender from receiving their own messages via WebSocket (previously they relied on the dedup check in `onMessageSent`).
- Added `X-Socket-Id` to `config/cors.php` `allowed_headers` so the CORS preflight allows this header.

**Verb config disambiguation:**
- `REVERB_SERVER_HOST` — interface the Reverb *server process* binds on (default `0.0.0.0`). Not set in `.env`; uses default.
- `REVERB_HOST` — public hostname used in WebSocket handshake AND the host the broadcasting *driver* connects to. Set to `localhost` for local dev; set to the public hostname (e.g. `reverb.keyhome.app`) in production.

**Files changed:** `.env` (`REVERB_HOST`), `config/cors.php` (`X-Socket-Id`), `src/lib/echo.ts` (`getEchoSocketId`), `src/lib/api.ts` (socket interceptor).

### Chat UI/UX — Session 9 (Apr 2026)

#### 1. Owner panel always light
**Root cause:** `OwnerThemeProvider` read `useThemeMode()` and applied `ownerDarkTheme` when OS was in dark mode.  
**Fix:** `src/providers/OwnerThemeProvider.tsx` — hard-coded `ownerLightTheme`; removed `ownerDarkTheme` dependency and `useThemeMode` import. The owner dashboard is a professional management tool that must always be light.

#### 2. Client chat dark mode
`src/components/chat/chat-theme.ts` — extended `ChatTheme` interface with 8 new surface tokens:
- `isDark: boolean` — dark variant flag
- `listBg` — sidebar/list background
- `surfaceBg` / `surfaceText` — received message bubble bg + text
- `textPrimary` / `textSecondary` / `textMuted` — text hierarchy
- `inputBg` — textarea/search field background

`CLIENT_DARK_THEME` added (slate palette, pink accent unchanged). `CLIENT_THEME` and `OWNER_THEME` backfilled with the new fields (all light values).

`src/components/chat/KeyHomeChatBox.tsx` — `theme` prop is now optional; when omitted (client pages), auto-selects `CLIENT_DARK_THEME` when `useThemeMode()` returns `'dark'`, else `CLIENT_THEME`. `OwnerChatBox` still passes `OWNER_THEME` explicitly → unaffected.

**Components updated to use theme tokens (no more hardcoded colours):**
- `ConversationList.tsx` — `bg-white` → `theme.listBg`; search bg, text, empty state text
- `ConversationItem.tsx` — all `text-gray-*` classes → `theme.textPrimary/Secondary/Muted`; border divider adapts in dark
- `MessageBubble.tsx` — received bubble: removed `bg-white text-gray-800`, now uses `theme.surfaceBg/surfaceText`; deleted bubble, system pill, timestamps, and hover action buttons all adapt
- `MessageInput.tsx` — container bg, textarea bg/text/ring, document pill text all use theme tokens

#### 3. Instant message input clear
**Bug:** `handleSend` in `MessageInput.tsx` awaited `onSend()` before calling `setBody('')`, so text stayed visible until the API responded (noticeable 1–2 s lag).  
**Fix:** capture `bodyToSend` + `attachmentToSend`, call `setBody('')` + `clearPending()` + reset textarea height **before** `await onSend(...)`. On error, restore body so the user can retry.

#### 4. Conversation list — avatar/name/last-message hierarchy
`ConversationItem.tsx` restructured:
- **Row 1:** participant name + timestamp (most prominent)
- **Row 2:** last message preview + unread badge
- **Row 3:** `🏠 Ad title` (subtle, accent-coloured, below the message so name+preview have visual priority)
- **Avatar corner badge:** 22×22 px property cover thumbnail pinned bottom-right of the 48 px avatar so the linked ad is identifiable at a glance without text

#### 5. FCM push notification bugs fixed
`app/Jobs/SendChatPushNotificationJob.php`:
- Loads the recipient user to resolve role (`AGENT` → `/owner/messages/{uuid}`, `CUSTOMER` → `/messages/{uuid}`)
- Adds `url` key to `withData()` so notification taps deep-link directly to the conversation

`public/sw.js` push handler:
- FCM wraps title/body inside `payload.notification.*` sub-object (not at top level). Previous `{...defaults, ...payload}` spread left `data.title = "KeyHome"` → fixed by explicitly promoting `payload.notification.title/body`
- Hoists `payload.data.url` into `notification.data.url` so `notificationclick` can navigate to the correct conversation

#### 6. Ad context in conversation list (previous session)
`ConversationResource.php` — added `slug` to the ad array.  
`types/chat.ts` — added `slug` to `ConversationAd` interface.  
`ChatHeader.tsx` — desktop (`md+`): full-width "Annonce liée" row (cover + title + `ExternalLink`). **Mobile:** compact **`Building2`** button in the nav bar opens a **bottom sheet** (title, thumbnail, « Ouvrir l'annonce ») so message list gets more height — app-like shell. **Viewport (client + owner layouts):** `maximumScale: 1` + `userScalable: false` so the PWA/chat shell does not invite pinch-zoom like a website (Safari may still allow minimal zoom in edge cases).

#### 7. Cross-panel security guard & panel-aware chat links
**Bug:** Owner/agent clicking "Voir l'annonce" in chat navigated to `/ads/[slug]` (client panel), loading the client layout with owner session — cross-panel access.

**Fix — 3 layers:**
1. **`ChatTheme.isOwnerPanel: boolean`** — new field on all 4 theme variants. `CLIENT_THEME`/`CLIENT_DARK_THEME` = `false`, `OWNER_THEME`/`OWNER_DARK_THEME` = `true`. Flows through all chat components via existing `theme` prop.
2. **`ChatHeader.tsx`** — `adHref` now panel-aware: `theme.isOwnerPanel ? /owner/ads/${ad.id} : /ads/${ad.slug}`. Profile link similarly redirects to `/owner/tenants` for owners.
3. **`MessageBubble.tsx` → `UrlChip`** — new `resolveAdHref()` rewrites internal `/ads/[slug]` URLs to `/owner/ads/[slug]` when `isOwnerPanel=true`. Always opens in a new tab (`target="_blank"`) so the conversation isn't lost. The backend `AdController::show()` accepts both UUID and slug (lines 247-253), so `/owner/ads/[slug]` resolves correctly.
4. **`ConversationItem.tsx`** — ad pill chip in the conversation list also panel-aware (`/owner/ads/${ad.id}` vs `/ads/${ad.slug}`).
5. **`DashboardLayout`** — cross-panel guard: if authenticated user has `AGENT`/`ADMIN` role and accesses a `PRIVATE_PATHS` client route, redirect to `/owner/dashboard`. Public pages (`/ads/[slug]`, `/home`) remain accessible.
6. **`NewMessageNotification.php`** — email link now recipient-aware: `AGENT` → `/owner/messages/{uuid}`, others → `/messages/{uuid}`. Prevents owners landing in client panel from email taps.
7. **`AdStatusChanged.php`** — owner-targeted notification (dispatched by `NotifyOwnerOfStatusChange` listener). Email action button + web push `data.url` switched from `/ads/{slug}` (client) to `/owner/ads/{id}` (owner panel).
8. **Lint cleanup** — fixed 3 React Compiler / ESLint errors that were silently in the build: `ChatNotificationListener.tsx` ref mutation during render (moved to `useEffect`), `useTypingIndicator.ts` manual memoization warning (block-disable), `echo.ts` `as any` (replaced with `as unknown as never`).

### Session — Chat / PWA / Theme Polish (Apr 2026)

10 fixes across the chat experience, PWA delivery and theme consistency.

**Chat / messaging:**
1. **Unread badge counts conversations, not messages** — `ChatBadgeIcon.tsx`, `OwnerSidebar.tsx`, `ConversationList.tsx` now derive the badge from `conversations.length` (or `filter(c => c.unread_count > 0).length`) instead of summing `total`. WhatsApp-style behaviour matching user mental model.
2. **`ad.slug` no longer null in chat** — `ConversationController::store()`, `::show()` and `ConversationService::getConversationsForUser()` now eager-load `'ad:id,title,slug'` (was `'ad:id,title'`). Frontend `Link` to `/ads/{slug}` now resolves correctly instead of `/ads/null`.
3. **Owner ad-edit success button relabeled** — `(owner)/owner/ads/[id]/page.tsx` button now reads "Aperçu public (nouvel onglet)" and uses `noopener,noreferrer` rel attributes. Clarifies that the button opens the public client-side preview, not the owner editor.
4. **Branded chat toast** — replaced notistack's sky-blue `info` variant with custom `ChatToast` component (`components/chat/ChatToast.tsx`). Registered as the `chatMessage` variant in `ToastProvider`. `ChatNotificationListener` accepts an `accentColor` prop (`#F6475F` for client, `#0D9488` for owner). Toast features panel-aware accent border, gradient icon, click-to-open, dedicated dismiss button, French aria labels.
5. **Smart back button on chat list** — added `lib/smart-back.ts` (`smartBack(router, fallbackHref)`). If `document.referrer` is empty, cross-origin or matches an auth path (`/login`, `/register`, `/verify-email`, etc.), navigation falls back to the provided href; otherwise `router.back()`. `ConversationList` back button now uses this. Client fallback `/home` (was `/`).
6. **Chat list scroll containment** — `ConversationList.tsx` scroll container now uses `min-h-0 overflow-y-auto` with explicit `overscrollBehavior: 'contain'` style and `onWheel={(e) => e.stopPropagation()}` to defensively block wheel events from bubbling to `<body>` when the list isn't tall enough to internally scroll. Inner virtualizer container has `Math.max(totalSize, 1)px` so wheel always has a target.
7. **Image attachment responsiveness** — `AttachmentPreview.tsx` swapped Next.js `<Image>` (whose inline `width`/`height` attrs were overriding Tailwind `max-h-64`) for a plain `<img>` wrapped in a sized button with `maxWidth: 'min(280px, 70vw)'`, `maxHeight: 280`, `objectFit: 'contain'`, `width: 'fit-content'`. Tall portrait screenshots now render at proper aspect ratio inside the 75% bubble across all viewports; tap-to-zoom lightbox unchanged.

**PWA / push delivery:**
8. **Single service worker** — removed dual-SW race that silently dropped FCM push events on standalone PWA installs. `useFcmToken.ts` now reuses `await navigator.serviceWorker.ready` instead of registering a separate `firebase-messaging-sw.js`. `public/sw.js` push handler is panel-aware: chat-message payloads (`data.type === 'chat_message'`) get tagged `chat-{conversation_uuid}` with `renotify: true` so multiple messages stack per conversation. SW version bumped `v7 → v8`. Old `firebase-messaging-sw.js` replaced with a self-unregistering stub for users with the old SW cached.

**Presence / last seen:**
11. **"Vu il y a X" now works** — `OnlineStatus` UI already supported `lastSeenAt`, but no data flowed. Added:
    - Migration `add_last_seen_at_to_users_table` (nullable `timestampTz` + index).
    - `TouchLastSeen` middleware appended to the `api` group: throttles `users.last_seen_at` updates to once per minute per user via Cache; uses raw `DB::update` to skip Eloquent observers (no `updated_at` mutation, no observer cascades). Heartbeat write failures are swallowed so they never break a real API response.
    - `ConversationResource` exposes `other_participant.last_seen_at` (ISO-8601 or `null`).
    - `ConversationService::getConversationsForUser()`, `ConversationController::store/show` eager-load `last_seen_at` on tenant/landlord.
    - Frontend `ConversationParticipant` type gains `last_seen_at: string | null`.
    - `ChatHeader` now passes `lastSeenAt={participant?.last_seen_at}` to both branches of `OnlineStatus`. Offline users now display "Vu il y a 3 min" / "Vu hier" via `date-fns/formatDistanceToNow` with French locale, instead of just "Hors ligne".

**Theme / session:**
9. **`SessionTimeoutGuard` mounted per panel** — `(dashboard)/layout.tsx` (rose) and `OwnerLayoutClient.tsx` under `OwnerThemeProvider` (teal) — pas dans `providers.tsx` racine. Précédemment sous le thème client uniquement ou au-dessus du thème bailleur → modale rose incohérente.
10. **Theme policy clarified** — `OwnerThemeProvider` now uses the resolved `mode` (was `choice === 'dark' ? 'dark' : 'light'`, ignoring `system`). Owner panel now follows OS preference like the client panel. `LandingThemeProvider` now uses `choice !== 'light'` so the public marketing landing defaults to dark unless the user explicitly picks light. Authenticated panels (client + owner) follow system; landing forces dark by default.

**Files changed (frontend):** `src/components/chat/{ChatBadgeIcon,ChatNotificationListener,ChatToast,ConversationList,AttachmentPreview}.tsx`, `src/components/owner/OwnerSidebar.tsx`, `src/components/owner/OwnerLayoutClient.tsx`, `src/providers/{ToastProvider,OwnerThemeProvider}.tsx`, `src/components/landing/LandingThemeContext.tsx`, `src/app/providers.tsx`, `src/app/(dashboard)/layout.tsx`, `src/app/(dashboard)/messages/{page,[uuid]/page}.tsx`, `src/app/(owner)/layout.tsx`, `src/app/(owner)/owner/ads/[id]/page.tsx`, `src/hooks/useFcmToken.ts`, `src/lib/smart-back.ts` (new), `public/sw.js`, `public/firebase-messaging-sw.js`.

**Files changed (backend):** `app/Http/Controllers/Api/V1/ConversationController.php`, `app/Services/Chat/ConversationService.php`.

**Test results:** Backend 776 passed (1 risky pre-existing). Frontend `tsc --noEmit` clean, `npm run lint` 0 errors / 50 pre-existing warnings, `npm run build` succeeds.

### Session — Reverb VPS Deployment (Apr 2026)

Reverb (`php artisan reverb:start`) was completely missing from the deployed stack — the chat real-time pipeline silently degraded to "refresh to see messages" in preprod/prod. Fixed:

1. **`docker-compose.yml`** (prod) — new `reverb` service: same `${APP_IMAGE}` as `app`/`worker`, runs `reverb:start --host=0.0.0.0 --port=8080`, joins `keyhome-network` + `traefik-public`, depends on `app`+`redis` healthy. Healthcheck via `pgrep -f 'reverb:start'`. Resource caps `384m / 0.75 cpu`. Traefik labels route `${REVERB_DOMAIN:-reverb.keyhome.app}` (TLS via Let's Encrypt) → port 8080.
2. **`docker-compose.preprod.yml`** — symmetric service with `${REVERB_DOMAIN:-reverb-api.keyhome.neocraft.dev}`. Joins `preprod-network` + `keyhome-prod-network` (shared Redis) + `traefik-public`. Caps `256m / 0.50 cpu`.
3. **`.env.example`** — corrected the prod template that conflated public-hostname vars (`REVERB_HOST`/`REVERB_PORT`/`REVERB_SCHEME` consumed by Pusher SDK + broadcaster auth signatures) with internal-listen vars (`REVERB_SERVER_HOST`/`REVERB_SERVER_PORT` consumed by the daemon). Old template had `REVERB_HOST=0.0.0.0` which would break frontend connections in prod. Added `REVERB_DOMAIN` for compose.
4. **`.env.preprod.example`** — flipped `BROADCAST_CONNECTION=log → reverb` (was silently logging all broadcasts to file) and added the full Reverb env block with key-generation comments.
5. **`docs/REVERB_DEPLOY.md`** — full deploy runbook: env-var matrix per environment, DNS records, `docker compose up -d reverb` steps, sanity checks (`wscat`, presence test), rollback, scaling via `REVERB_SCALING_ENABLED=true` + Redis pub/sub.

**DNS prerequisite (manual, must precede deploy):**
- `reverb.keyhome.app` A record → prod VPS IP
- `reverb-api.keyhome.neocraft.dev` A record → preprod VPS IP

**Vercel frontend env vars (must be added before frontend redeploy):**
- `NEXT_PUBLIC_REVERB_APP_KEY` (must equal backend `REVERB_APP_KEY`)
- `NEXT_PUBLIC_REVERB_HOST` = matching public hostname
- `NEXT_PUBLIC_REVERB_PORT=443`, `NEXT_PUBLIC_REVERB_SCHEME=https`

**Local-dev `.env`** unchanged — already had `BROADCAST_CONNECTION=reverb` and direct localhost config that works with `composer run dev`.

**Files changed:** `docker-compose.yml`, `docker-compose.preprod.yml`, `.env.example`, `.env.preprod.example`, `docs/REVERB_DEPLOY.md` (new).

**Preprod/prod chat silent failure (May 2026):** Laravel’s Reverb broadcaster
connects with Guzzle to `REVERB_HOST`. Inside Docker, that value is often a
public hostname (`reverb.*`) that **does not resolve** on the internal bridge →
broadcasts never reach the Reverb container. **Fix:** optional env vars
`REVERB_BROADCAST_HOST` / `REVERB_BROADCAST_PORT` / `REVERB_BROADCAST_SCHEME`
in `config/broadcasting.php` (defaults unchanged when unset). **Compose:**
`app` + `worker` on prod and preprod set `reverb` + `8080` + `http`. Browsers
unchanged (`NEXT_PUBLIC_REVERB_*` + public `REVERB_HOST`).

### Session — Reverb perf tuning (Apr 2026)

Follow-up to the deployment session: the initial `reverb` service shipped with default container limits, which would have capped a single instance at ~1 k concurrent WebSocket connections regardless of CPU/memory headroom.

1. **`nofile` ulimit → 10 000.** Containers do *not* honour `/etc/security/limits.conf` on the host (that file is consumed by PAM at login time); they inherit `nofile` from the docker daemon (typically 1024 soft). Added a per-service `ulimits: { nofile: { soft: 10000, hard: 10000 } }` block to both `docker-compose.yml` and `docker-compose.preprod.yml` on the `reverb` service. Verify with `docker compose exec reverb sh -c 'cat /proc/1/limits | grep "open files"'`.
2. **`ext-ev` PHP extension** (originally tried `ext-uv` — see addendum). ReactPHP's default fallback loop is `stream_select()`, O(n) per tick and capped at 1024 fds by `FD_SETSIZE` regardless of the ulimit above. Installed `ext-ev` in the Alpine `Dockerfile` (`apk add libev-dev` + `pecl install ev` + `docker-php-ext-enable ev`) so ReactPHP autodetects libev (epoll on Linux, kqueue on BSD/macOS) — O(1) per tick, scales to the 10 k fd ceiling. Loop priority order: `ev` ← us → `uv` → `event` → `stream_select`. Verify with `docker compose exec reverb php -m | grep ev`.

Both tunings are documented in `docs/REVERB_DEPLOY.md` under "Kernel & runtime tuning". Image-size delta from `ext-ev`: ~1 MB.

**Addendum — `ext-uv` → `ext-ev` switch.** First attempt used `ext-uv` (`pecl install uv` + `libuv-dev`). The `build_image` CI stage failed at the `pecl install uv` step because the PECL `uv` package has been unmaintained since 2022 (v0.3.0) and no longer compiles on PHP 8.4. Switched to `ext-ev` 1.1.5 (2023), which (a) is actively maintained, (b) supports PHP 8+, and (c) is *higher priority* than `uv` in ReactPHP's loop autodetection anyway. Same epoll/kqueue performance characteristics. **Lesson for future agents:** when adding PECL extensions for PHP 8.4 images, check the PECL package's last release date — anything older than late 2024 risks PHP 8.4 incompatibility.

**Files changed:** `docker-compose.yml`, `docker-compose.preprod.yml`, `Dockerfile`, `docs/REVERB_DEPLOY.md`.

### Frontend GitLab CI / Vercel
- **`format` job**: `npm run format:check` — runs Prettier check. Fix by running `npm run format` locally before pushing.
- **`lint` job**: `npm run lint` — zero errors allowed. Warnings are tolerated but minimised.
- **Vercel build**: runs `tsc --noEmit` as part of Next.js production build. TypeScript errors fail the build even if ESLint passes. Always check that interface changes don't break downstream callers.
- **`test_unit` job (coverage)**: Coverage thresholds have been **removed** from `vitest.config.ts`. The job passes/fails based on test results only. Coverage is still collected and reported (reporters: text, json, html, lcov, cobertura) as CI artifacts — do NOT re-add `thresholds` until the test suite has meaningful coverage.
- **`build:preprod` + `deploy:preprod` removed**: Vercel handles `cedrickdev` preview deployments automatically. `build:check` (`npm run build`) runs on all non-`main` branches including `cedrickdev` as the quality gate. The `VERCEL_TOKEN`/`VERCEL_ORG_ID`/`VERCEL_PROJECT_ID` variables are only needed for `main` (production deploy).

### Chat / PWA fixes (May 2026, pass 2)

- **`ChatHeader` mobile « Annonce liée »** — bottom sheet rendu via `createPortal` sur `document.body` avec `z-index: 14000` (au-dessus des AppBar ~1200) ; backdrop `cursor-pointer` ; verrou `overflow: hidden` sur `body` à l’ouverture. Corrige les taps ignorés (navbar recouvrant un pseudo `z-200` Tailwind invalide).
- **Persistance session SPA/PWA** — `TokenService::createForUser` utilise `config('sanctum.expiration')` (43200 min) au lieu de `now()->addDay()` ; défaut `SESSION_LIFETIME` porté à **43200** dans `config/session.php` ; cookie `kh_role` **30 j** (`auth-session.ts`) aligné sur la session Laravel.
- **FCM** — dernier jeton en **localStorage** (`src/lib/fcm-token-key.ts`) pour survivre au cold start PWA ; `getRegistration()` puis fallback `ready` ; reset d’enregistrement au changement de `user.id` ; **logout** : `DELETE /fcm/token` **avant** `POST /auth/logout` pendant que le Bearer est encore valide ; `public/sw.js` **v11** (bump cache).
- **Chat client — profil bailleur** : `ConversationResource` expose `other_participant.username` ; `ChatHeader` (panneau client uniquement) : bouton **Profil public du bailleur** (`UserRound`) → `/bailleurs/{username|id}` en **nouvel onglet** (à côté de l’annonce liée).

### Session — Chat Parity Modernization (May 2026)

Full-parity sweep aligning the chat UX with WhatsApp / Messenger while keeping the KeyHome accent split (pink client `#F6475F`, teal owner `#0D9488`). Touches every layer (DB, services, events, routes, hooks, components).

**`POST /api/v1/conversations` serialization** — `ConversationResource` formats `last_message_at`, `other_participant.last_seen_at`, and `last_message.sent_at` via `toIso8601OrNull()` (accepts `DateTimeInterface`, parses ISO strings, never calls `->toIso8601String()` on a bare string). Prevents intermittent HTTP 500 when building the find-or-create response if a timestamp bypasses Eloquent casts (e.g. partial relation loads or legacy rows).

**Owner panel symmetry** — `OwnerLayoutClient.tsx` now mounts `GlobalPresenceChannel` + `ChatNotificationListener` (with teal `accentColor="#0D9488"`) and switches to a `100dvh` + `position: absolute, inset: 0` shell on `/owner/messages*` so the chat fills the viewport on mobile (matching the dashboard layout treatment). The owner navbar / bottom nav are hidden on the conversation detail screen for an immersive view. Without this, owner users had no global presence, no toast on incoming messages, no live unread badge, and a broken mobile message list.

**Real-time hardening (backend)** —
- `MessageRead` switched from `ShouldBroadcast` (queued) to `ShouldBroadcastNow` for parity with `MessageSent` / `MessageDeleted`.
- `MessageRead`, `MessageDeleted`, `MessageReactionAdded/Removed`, `ConversationArchived` all broadcast with `->toOthers()` so the sender no longer receives their own events.
- `MessageService::delete` now realigns `conversations.last_message_id` to the most recent non-deleted message in the same transaction (with `lockForUpdate`) — fixes the dangling pointer that left `last_message=null` on the conversation list.
- `SendChatPushNotificationJob` treats `UserRole::ADMIN` like `AGENT` for the deep-link base path (`/owner/messages/`) — admins were silently routed to the client panel.

**Real-time hardening (frontend)** —
- `src/lib/api.ts` — synchronous request interceptor attaches `X-Socket-Id` from `getEchoSocketId()` so Laravel `->toOthers()` can correctly exclude the sender on every chat write. Also adds `/auth/refresh` and `/broadcasting/auth` to `AUTH_ROUTES` so a 401 there does NOT fire the global `kh:auth-expired` event.
- `useChat.ts` — `handleSendMessage` deps reduced to `[conversationUuid, user, updateCache, queryClient]`; the latest values for `connectionState` and `stopTyping` are read via `connectionStateRef` / `stopTypingRef`. Avoids re-creating the callback on every state change.
- `useChat.ts` — auto mark-as-read now listens to both `window.focus` AND `document.visibilitychange` (Page Visibility API), so PWAs returning from background mark-read without needing a fresh focus event.
- `ChatNotificationListener.tsx` — bind loop wrapped in a race-safe `tryBindOne(uuid, attempts)` retry (50 ms × 20) mirroring the pattern in `useChat`/`useConversations`. The previous synchronous read of `(echoChannel as any).subscription` could silently miss `message.sent` events on the first render.
- `useTypingIndicator.ts` — `STOP_AFTER_MS` raised from `1000` to `3000` (matches WhatsApp / iMessage). Documentation aligned with the code.
- Bounce easings `cubic-bezier(0.34, 1.36, 0.64, 1)` removed from `MessageBubble.tsx` and `ChatWindow.tsx` (banned by repo design rules) — both now use the standard out-quint `(0.22, 1, 0.36, 1)`.

**Attachment ownership validation** — `AttachmentService::belongsToConversation($url, $conversationId)` returns true only when the `url` starts with `chats/{conversationId}/`. `MessageService::send` calls it for every attachment and `abort(422)` if any URL belongs to another conversation. Defence in depth: the form request validates the structure, this check enforces the ownership contract.

**GIF & audio policy** — `AttachmentService::IMAGE_MIMES` now includes `image/gif` (modern UX). New `AttachmentService::AUDIO_MIMES` constant covers `audio/{webm,mp4,mpeg,mp3,ogg,wav}`. `UploadAttachmentRequest` accepts `gif` + audio extensions. `SendMessageRequest` adds `audio` to `attachments.*.type` and `attachments.*.mime_type`, plus optional `audio_duration_ms` (100–120000) and `audio_waveform_peaks` (array of 0..1 floats, max 120) for voice notes.

**ConversationArchived event** — new event class `app/Events/Chat/ConversationArchived.php` (`ShouldBroadcastNow` + `toOthers()`) on `private-conversation.{id}` with alias `conversation.archived`. `ConversationService::archive` is now idempotent and broadcasts only when the status transitions to `Archived`.

**Reactions** — full feature pass:
- Migration `2026_05_01_183513_create_message_reactions_table.php` — UUID PK, FKs cascade-delete on `messages` / `users`, unique `(message_id, user_id, emoji)`, indexes on both FKs.
- Model `App\Models\MessageReaction` (HasUuids, no timestamps trait — single `created_at` only).
- Service `App\Services\Chat\ReactionService` — `add` (idempotent: same emoji from same user is a no-op) / `remove` (returns true if a row was deleted). Both broadcast `MessageReactionAdded` / `MessageReactionRemoved` with `toOthers()` after the DB write succeeds.
- Routes (under `auth:sanctum` + `throttle:60,1`):
  - `POST /api/v1/messages/{uuid}/reactions` body `{emoji}`
  - `DELETE /api/v1/messages/{uuid}/reactions` body `{emoji}`
- `MessageController` got `addReaction` / `removeReaction` actions, both wired through `App\Http\Requests\Chat\ReactionRequest` (`emoji`: required string max 16).
- `MessageResource` exposes `reactions: Array<{emoji, count, user_ids[]}>` when the relation is eager-loaded. `MessageService::getHistory` eager-loads `reactions:id,message_id,user_id,emoji`.
- Frontend: `useChat.toggleReaction(uuid, emoji)` performs an optimistic mutation (add or remove on the cached `Message.reactions`), then POSTs/DELETEs and rolls back on failure. WebSocket subscriptions to `message.reaction.added` / `message.reaction.removed` apply remote changes (deduped on user_id).
- UI: `ReactionPicker` (long-press picker + 6 default emojis: ❤️ 👍 😂 😮 😢 🙏) + reaction pills under each bubble (mine highlighted with accent ring). New `Smile` button in the desktop hover toolbar opens the same picker. Long-press now opens the picker (replaces the prior "long-press = reply"); reply is still accessible via the swipe-to-reply gesture below.

**Read receipts visual** — `MessageBubble.StatusIcon` already mapped statuses to `Clock` / `Check` / `CheckCheck` / accent-coloured `CheckCheck`. The `useChat` `messages.read` listener marks the sender's bubbles as `read` + sets `read_at` so the tick turns accent-colored on the same Reverb tick as the recipient's mark-read.

**Sticky day separator** — `ChatWindow.tsx` derives a `stickyDate` from the first virtualized item that's at or below `scrollTop`, walks back to the most recent `'separator'` item, and renders a glass-pill ("Aujourd'hui" / "Hier" / "12 mars 2026") above the message list. The pill fades in only while scrolling (`isScrolling` toggled by an 800 ms idle timer) — WhatsApp / iMessage behaviour without needing a real CSS-sticky inside the virtualizer.

**Swipe-to-reply** — `MessageBubble` `onTouchStart/Move/End` handlers track horizontal drag distance vs vertical (axis lock at 10 px). When the user swipes ≥ 60 px in the WhatsApp direction (right on others' messages, left on own), the `onReply(message)` callback fires. A reply icon hint fades in proportionally to the swipe progress. Long-press on the same bubble still opens the reaction picker — both gestures coexist.

**Multi-attachment compose** — `MessageInput` was rewritten around a `pending: PendingItem[]` state (max 5). Each item carries its own `previewUrl`, `attachment` (server-confirmed), and `uploadProgress` so multiple files upload in parallel with independent progress bars. Removed the single-file flag (`pendingFile` etc.) entirely. The "+" tile inside the preview row offers another file picker as long as `pending.length < 5`. The send button is gated on `stillUploading === false`.

**Voice notes (WhatsApp-style)** — new `MessageInput` mic button (visible when there's nothing to send) opens `VoiceRecorder`:
- MediaRecorder API with feature-detected MIME (priority `audio/webm;codecs=opus` → fallbacks). 2-min hard cap with periodic ticker.
- On stop, decodes the blob via `AudioContext.decodeAudioData` and reduces the channel data to 40 normalised peaks (0..1) for the waveform.
- Uploads as a regular attachment, then enriches the descriptor with `type: 'audio'`, `audio_duration_ms`, `audio_waveform_peaks` and feeds it to the existing pending-attachment row, ready to send.
- `VoicePlayer` (`MessageBubble` → `AttachmentPreview`): circular play/pause button + bar waveform with played-vs-remaining colouring + drag-to-scrub on the track + tabular `mm:ss` countdown. Falls back to a plain progress bar when no peaks are available.

**Mobile keyboard handling (iOS)** — new hook `useVisualViewportInset()` reads `window.visualViewport` and returns the bottom-inset in CSS pixels. `MessageInput` applies `transform: translateY(-${inset}px)` so the bar sits above the on-screen keyboard. When the keyboard hides, the bar restores its safe-area inset.

**Cleanup** — `ChatPageWrapper.tsx` deleted (no consumers left). `chat-api.ts::setTyping` REST helper removed (typing flows through Pusher whispers in `useTypingIndicator`).

**New events alias map (cumulative):** `message.sent`, `messages.read`, `message.deleted`, `message.reaction.added`, `message.reaction.removed`, `conversation.archived`, `client-typing` (whisper).

**New tests:**
- `tests/Feature/Chat/ChatRealtimeBroadcastTest.php` (9) — `MessageRead` is `ShouldBroadcastNow`, `MessageDeleted` broadcasts on delete, `last_message_id` realign (with previous + null fallback), `ConversationArchived` broadcast + idempotency, attachment ownership validation (rejects foreign URL, accepts scoped one), `MessageSent` regression.
- `tests/Feature/Chat/MessageReactionTest.php` (7) — add / dedup / outsider 404 / remove (toggle off) / no-op on absent / empty emoji 422 / >16 chars 422.
- `keyhome-frontend-next/src/tests/hooks/useTypingIndicator.test.ts` (3) — 100 ms debounce, 3 s stop, `stopTyping()` cancels.
- `keyhome-frontend-next/src/tests/components/OnlineStatus.test.tsx` (6) — `formatLastSeenShort` helper for all 6 time windows.

**Files changed (backend):**
`app/Events/Chat/{ConversationArchived,MessageRead,MessageReactionAdded,MessageReactionRemoved}.php` (new), `app/Events/Chat/MessageRead.php` (broadcast type), `app/Http/Controllers/Api/V1/MessageController.php`, `app/Http/Requests/Chat/{ReactionRequest,SendMessageRequest,UploadAttachmentRequest}.php`, `app/Http/Resources/Chat/{ConversationResource,MessageResource}.php`, `app/Jobs/SendChatPushNotificationJob.php`, `app/Models/{Message,MessageReaction}.php`, `app/Services/Chat/{AttachmentService,ConversationService,MessageService,ReactionService}.php`, `database/migrations/2026_05_01_183513_create_message_reactions_table.php`, `routes/api.php`, plus the two new test files.

**Files changed (frontend):**
`src/components/owner/OwnerLayoutClient.tsx` (presence + chat notif + mobile messages layout), `src/lib/api.ts` (X-Socket-Id interceptor + AUTH_ROUTES), `src/proxy.ts` (CSP: Firebase connect-src, R2 `cloudflarestorage`, dev Reverb `ws://localhost:8080` + matching `wss`/`ws` for `NEXT_PUBLIC_REVERB_HOST`), `src/lib/chat-api.ts` (reactions endpoints + setTyping removed), `src/types/chat.ts` (audio + reactions), `src/hooks/{useChat,useTypingIndicator,useVisualViewportInset}.ts`, `src/components/chat/{ChatNotificationListener,ChatWindow,MessageBubble,MessageInput,ReactionPicker,VoiceRecorder,VoicePlayer,OnlineStatus,AttachmentPreview}.tsx`, removed `src/components/chat/ChatPageWrapper.tsx`, plus the two new vitest files.

**Test results:**
- Backend: chat suite (`tests/Feature/Chat/*`) — 50 passed (94 assertions). Pint clean.
- Frontend: `npx tsc --noEmit` clean. New vitest specs: 9 passed (9 assertions). `npm run build` succeeds (Turbopack, 91 routes generated).

### Chat audit hardening (May 2026)

- **`reply_to_id`** — `SendMessageRequest` scopes `exists(messages.id)` to `conversation_id` = route `uuid` so replies cannot point at another thread.
- **Archived conversations** — `MessageService::send` and `ConversationController::uploadAttachment` return **422** if `status === archived` (find-or-create from an ad still reopens archived threads for new contact).
- **`MarkConversationReadJob`** — dispatched only when `GET …/messages` has no `cursor` (first page); pagination no longer spawns duplicate jobs / broadcasts.
- **FCM** — `SendChatPushNotificationJob` skips soft-deleted messages.
- **Frontend** — `src/lib/chat-subscriptions.ts`: at most **40** private channels for global listeners; **unread** threads prioritized. Used by `ChatNotificationListener` and `useConversations` (prefetch top 3 uses same ordering). `useChat`: `CHAT_MESSAGES_STALE_MS` (23 h) + `refetchOnWindowFocus` + refetch **merges** loaded older pages and preserves optimistic rows; renews R2 `signed_url` before default 24 h TTL. `VoicePlayer` doc aligned with server TTL.
- **Tests** — `ConversationControllerTest`: reply cross-conversation 422, archived send/upload 422, mark-read job not duplicated on cursor page.

### Chat E2EE (text, May 2026)

- **Model** — Hybrid: default messages still use server `CHAT_ENCRYPTION_KEY` (`EncryptionService`). Optional **client-sealed** messages (`is_client_sealed`): AES-GCM plaintext on the client, opaque `body` / `body_iv` on the server (no server decrypt). Attachments/voice remain **server-encrypted** only (sealed branch rejects attachments in `SendMessageRequest`).
- **Identity** — `users.chat_e2ee_public_key_pem`; `GET`/`PUT` `/api/v1/my/chat-e2ee/public-key` (`ChatE2eeIdentityController`). `UserResource` exposes PEM to the owning user only.
- **Session keys** — `conversations.e2ee_wrapped_key_tenant` / `e2ee_wrapped_key_landlord` (RSA-OAEP--wrapped AES-256). First sealed message in a thread **must** send `e2ee_wrapped_keys`; later messages omit. `ConversationResource` includes `e2ee.tenant_public_key_pem`, `landlord_public_key_pem`, `wrapped_conversation_key_b64` (recipient-specific), `both_keys_registered`, `session_ready`.
- **Realtime & pushes** — `MessageSent` / `MessageResource`: no plaintext `body` when sealed; `e2ee` carries ciphertext + iv. `NewMessageNotification` + FCM use a generic “message sécurisé” preview for sealed rows.
- **Inbox list (May 2026)** — `GET /conversations` `last_message` includes `e2ee` for sealed rows (same shape as WS). `ConversationItem` merges TanStack message cache (`useChatMessagesCacheEntry`) + decrypts locally when `session_ready`, so the thread row shows real text instead of a permanent “Message sécurisé” placeholder when the device can open the session key. **`useSyncExternalStore` must use the same `getSnapshot` for the server snapshot** (`getServerSnapshot === getSnapshot`); a third argument that always returns `undefined` while the client reads dehydrated `chat-messages` causes a Next.js hydration mismatch and **breaks row clicks / interactivity**.
- **Frontend** — `src/lib/chat-e2ee-crypto.ts` (WebCrypto RSA-OAEP-256 + AES-GCM), `chat-e2ee-identity.ts` + `AuthProvider` bootstrap; `useChat` accepts `conversation`, seals text when both participants have registered keys, decrypts into `decrypted_body`; offline queue replays with **server** encryption (`skipE2ee`). **Reply quotes** — API / WS leave `reply_to.body` null for sealed parents; `enrichReplyToQuotes` (`src/lib/chat-reply-enrich.ts`) plus a `useChat` effect copies a snippet from the parent row's `decrypted_body` (or plaintext `body`) so the in-bubble citation is not stuck on “Message sécurisé” when the device can read the parent. **Risk:** E2EE private key in `localStorage` (XSS); cross-device requires new identity / lost history for that thread.

**Tests:** `tests/Feature/Chat/ChatE2eeTest.php` (identity API, first/follow-up sealed send, missing key 422, broadcast payload, conversation resource PEMs, **conversation list `last_message.e2ee`**).

---

## Enterprise Audit — Mai 2026

Audit multi-agents exhaustif réalisé le 2 mai 2026. 7 agents parallèles ont couvert : backend/SOLID, auth/sécurité, paiement/chat, IA/geo, PWA/push, frontend/design, DevOps.

### Bugs critiques à corriger en priorité (CRITICAL / HIGH)

**1. [SECURITY][HIGH] `RequireApiMfa` middleware jamais monté sur les routes**
- Alias enregistré dans `bootstrap/app.php` L63 mais aucune route ne l'utilise.
- L'authentification MFA admin via l'API REST est **optionnelle** de fait.
- **Fix :** appliquer `middleware('mfa.admin')` aux groupes de routes admin sensibles.

**2. [SECURITY][HIGH] `processWebhook()` — lock sans transaction (race condition PostgreSQL)**
- `Payment::...->lockForUpdate()->first()` s'exécute hors `DB::transaction()`.
- Sur PostgreSQL, le lock est libéré dès la fin de l'instruction en autocommit : deux webhooks concurrents peuvent traiter le même paiement.
- **Fix :** envelopper tout `processWebhook()` dans `DB::transaction()` (même pattern que `syncPaymentStatus()`).

**3. [PAYMENTS][HIGH] Activation d'abonnement sans garde d'idempotence**
- `HandlePostPaymentActions::activateSubscription()` ne vérifie pas `Subscription::where('payment_id', $payment->id)->exists()` avant de créer.
- Un retry de job ou un double-trigger webhook → abonnements en double.
- **Fix :** guard `firstOrCreate(['payment_id' => $payment->id])` ou unique constraint.

**4. [AI][HIGH] `RecommendationEngine` charge tous les ads en mémoire PHP**
- `Ad::...->get()` sur tous les ads `AVAILABLE` visibles → OOM à l'échelle.
- **Fix :** pagination + scoring SQL, ou pré-calcul en batch/cache, ou limiter à N candidats pré-filtrés par scoring rapide.

**5. [AI][HIGH] TrustScore bailleur — O(n × KeyScore) sur chaque calcul**
- `computeLandlord()` boucle tous les ads actifs et appelle `keyScoreService->compute($ad)` pour chacun.
- **Fix :** stocker/cacher le KeyScore par ad (déjà caché 1h dans `KeyScoreController`) → agréger depuis le cache, ou réduire via `average('key_score')` si la colonne est persistée.

**6. [AI][HIGH] Newsletter HTML — aucune sanitisation côté serveur**
- `AiDescriptionEnhancer::enhanceNewsletter()` retourne du HTML généré par LLM sans sanitisation.
- Si ce contenu est stocké et rendu sans escaping → XSS.
- **Fix :** passer le résultat via `HTMLPurifier` ou `Tiptap`/`htmlspecialchars` selon le rendu.

**7. [PUSH][HIGH] `RetentionPushService::notifyViewingReminders` — URL client incorrecte**
- Envoie `url: '/owner/reservations'` au **locataire** (rôle CUSTOMER).
- La bonne route client est `/my/reservations`.
- **Fix :** `$url = '/my/reservations'` pour la notification destinée au client.

### Issues haute priorité (MEDIUM)

**Backend / Architecture**
- `User` model appelle `PointService` depuis le boot via `app()` (service locator, couplage fort) — déplacer dans `UserObserver::created()` avec injection de dépendance.
- `~60%` des controllers API utilisent encore `$request->validate()` au lieu de Form Requests dédiées.
- `SearchAlertController` utilise `JsonResource` anonyme au lieu d'une resource typée.
- `AgencyController::show` construit la pagination en ligne — extraire dans `AgencyService`.

**Sécurité**
- `EnsureTokenMatchesRole` ne s'applique pas aux `TransientToken` (sessions SPA) — le middleware `EnsureOwnerRole` compense mais c'est fragile.
- `password` dans `$fillable` sur `User` (risque en cas de `::create($request->all())` involontaire).
- `Clerk JWT` : aucun allowlisting explicite de l'algorithme (`alg: RS256` seulement) avant vérification.
- Logs WebAuthn en mode debug incluent email/identifiers — ajouter `email` à `MaskSensitiveDataProcessor::sensitiveKeys`.
- Sanctum `expiration: 43200 min` (30j) vs `createToken(..., now()->addDay())` (1j) — discordance à documenter ou corriger.

**Paiements**
- Refund partiel laisse le payment en status `SUCCESS` — opaque pour la comptabilité.
- `flutterwave-signature` header non vérifié (seul `verif-hash` l'est) — harmless aujourd'hui mais à documenter.

**IA / Geo**
- Extraction JSON par regex superficielle (`{...}`) dans `AiSearchService::extractJson()` — JSON imbriqué ou multi-objet peut échouer.
- Clé Gemini passée en query param URL (`key=...`) → visible dans logs proxy/CDN — utiliser header `x-goog-api-key`.
- `AiDescriptionEnhancer` : pas de failover automatique sur HTTP 429/5xx (retourne le texte original).
- Absence de métriques cache hit/miss sur le pipeline AI search.
- `DirectionsService` : pas de negative cache sur échec ORS — chaque requête retente le serveur.
- Consentement TrustScore non vérifié à l'intérieur de `TrustScoreService::compute()` — `artisan app:recompute-trust-scores --user=X` contourne le consentement.

**PWA / Push**
- Icons owner manifest (`manifest-owner.json`) réutilisent un seul logo PNG au lieu d'un jeu d'icônes 192/512/maskable dédié.
- `CACHEABLE_OWNER_PATHS` dans SW est dead code quand l'API est cross-origin (guard `url.origin !== self.location.origin` filtrant avant).
- `RetentionPushService` utilise `Cache` facade (pas Redis garanti) — en multi-node les frequency caps peuvent être incohérents si `CACHE_DRIVER` n'est pas `redis`.
- `useFcmToken` appelle `Notification.requestPermission()` dès `isAuthenticated` — peut se déclencher avant la fin de l'onboarding (avant `kh:welcome-dismissed`).
- Clé VAPID absente → échec runtime sans erreur préventive au boot.

**Frontend**
- `mapbox-gl` importé statiquement sur la page search (coût bundle/main-thread même map off) — utiliser `dynamic(() => import('mapbox-gl'))`.
- `template.tsx` dashboard/owner utilise `ease: [0.25, 0.1, 0.25, 1]` au lieu de l'out-quint standard `[0.22, 1, 0.36, 1]`.
- `StickyPropertyBar` utilise Framer Motion `type: 'spring'` (potentiellement perceptible comme bounce).
- Dérive token : `globals.css --kh-text-secondary: #555555` vs `tokens.ts light.textSecondary: '#5A5A5A'`.
- Hex `ROSE = '#ec4899'` hardcodé dans `owner/dashboard/page.tsx` — à migrer dans `tokens`.
- `IconButton` sur l'historique de recherche en dessous de 44×44px (`p: 0.25`, icon 12px).

**DevOps**
- Rector absent du stage `quality` CI — seuls Pint et PHPStan y figurent.
- Services optionnels Docker (monitoring : Prometheus, Grafana, etc.) sans `deploy.resources.limits` ni healthchecks.
- `.env.example` : `SESSION_DRIVER=file` (défaut Laravel) non adapté au scaling horizontal (utiliser `redis`).
- `QUEUE_CONNECTION=sync` dans certains exemples env — jobs critiques (email, push) seraient synchrones.
- Pas de PgBouncer documenté pour la mise à l'échelle des connexions PostgreSQL.

### Issues basses priorité (LOW)

- Commentaire "floating back button" dans `AdDetailClient.tsx` est stale (le bouton a été supprimé).
- Commentaire `sw.js` header indique "v3" alors que `VERSION = "v8"`.
- Commentaire `PWAInstallPrompt.tsx` dit "next session" alors que c'est `localStorage` persistant.
- Docs `messaging_doc.md` décrit encore `firebase-messaging-sw.js` comme gestionnaire de push actif (stale).
- `AuthController::login` retourne un tableau brut au lieu d'une ressource typée (`UserResource`).
- Réponse de `AgencyController::show` mélange envelope custom et resource standard.
- Message de succès en anglais dans `AdController::destroy`.
- `Contracts/` séparation `app/Contracts` vs `app/Services/Contracts` — à documenter.

---

## Analyse SWOT KeyHome (Mai 2026)

### Forces (Strengths)

**Produit / Fonctionnel**
- Moteur de recherche AI multi-provider (Groq/OpenAI/Gemini/Together/Mistral) avec circuit breakers, cache 24h et fallback regex — différenciateur compétitif fort sur le marché africain.
- Chat temps-réel E2EE hybride (AES-256-CBC serveur + AES-GCM WebCrypto client-sealed) — niveau de sécurité rare pour un SaaS immobilier.
- Dual-PWA installable (client crimson + bailleur teal) avec scopes séparés — expérience mobile native sans coût App Store.
- TrustScore bidirectionnel (7 signaux locataire, 7 propriétaire) avec consentement GDPR — confiance marketplace différenciante.
- KeyScore propriétaire (score d'attractivité d'annonce) — valeur ajoutée unique.
- Scorecard quartier (Overpass + ORS + haversine) — insight local absent des concurrents.
- Chat réactions, voice notes WhatsApp-style, swipe-to-reply, E2EE, pièces jointes R2 — messagerie enterprise-grade.

**Technique / Architecture**
- Laravel 12 + Filament 4 avec séparation claire services/actions/DTOs/contrats dans les zones bien couvertes.
- Gateway paiement abstrait (`PaymentGatewayInterface`) — Flutterwave (mobile money) + Stripe (carte, via Laravel Cashier 16) coexistent ; routing per-méthode via `PaymentMethod::gateway()` et registry dans `AppServiceProvider`.
- `preventLazyLoading()` en dev — détection N+1 systématique.
- Multi-worker Docker (critical/payments/emails/tours) avec resource limits.
- PostGIS pour les requêtes géospatiales avancées.
- Meilisearch intégré avec Scout — full-text search sub-50ms.
- HSTS + CSP + SecurityHeaders — posture sécurité solide.
- CI/CD GitLab multi-stage (quality → build → deploy → smoke → notify) avec rollback auto sur échec migration.
- MFA Filament (TOTP + Email) + Passkeys WebAuthn.
- Reverb WebSockets self-hosted avec ext-ev (epoll) + ulimit 10k fd — scaling préparé.

**Marché**
- Premier marché : Cameroun/CEMAC/UEMOA — peu de concurrents tech-first.
- Multilocation (SaaS multi-tenant agences + propriétaires indépendants).
- Currency locale XOF/XAF native — pas de friction conversion.

### Faiblesses (Weaknesses)

**Sécurité (blocantes pour un go-live enterprise)**
- MFA admin API non montée (`RequireApiMfa` non utilisé sur les routes).
- Race condition webhook paiement (lock sans transaction → double-spend possible).
- Absence d'idempotence sur l'activation d'abonnement.
- Clé Gemini exposée en query param URL (logs/CDN).
- `password` dans `$fillable` sur `User` (risque de mass-assignment).

**Scalabilité**
- `RecommendationEngine` charge tous les ads en mémoire PHP → OOM à l'échelle.
- TrustScore bailleur O(n × KeyScore) sur chaque calcul → dégradation avec large catalogue.
- Pas de PgBouncer → épuisement des connexions PostgreSQL sous charge.
- `Cache` facade pour RetentionPush (non garanti Redis en multi-node).

**Qualité de code**
- ~40% des controllers API manquent de Form Requests dédiées.
- `User` model appelle `PointService` depuis le boot (couplage fort).
- DTO quasi-absent hors auth (seulement `LoginResult`, `RegistrationResult`).
- Couverture de tests insuffisante sur les flows critiques (WebAuthn, paiements, TrustScore).

**Frontend**
- Mapbox importé statiquement sur la page search (bundle performance).
- Dérive entre `globals.css` et `tokens.ts` (valeurs couleur).
- Quelques animations non-conformes (template.tsx easing, StickyPropertyBar spring).

**Push notifications**
- URL incorrect dans RetentionPush (client → page owner).
- FCM permission prompt avant fin d'onboarding.
- Icons PWA owner non optimisées.

### Opportunités (Opportunities)

- **IA générative** : l'infrastructure multi-provider est prête → ajouter description auto d'annonce, évaluation automatique du prix, assistant conversationnel de recherche.
- **Marché Africa** : digitalisation immobilière en forte croissance, concurrence faible sur l'UX — fenêtre d'opportunité avant acteurs régionaux.
- **Mobile** : PWA standalone + React Native shell → distribution gratuite sur Android/iOS sans App Store fees.
- **TrustScore** : seul score confiance bidirectionnel connu sur ce marché → argument commercial premium (abonnement "badge vérifié").
- **MLS / API partenaires** : l'API REST versionnée `/api/v1/` et les ressources Eloquent sont prêtes pour une ouverture partenaires (CMS agences, portails régionaux).
- **Analytique** : widgets Filament + Nightwatch + Pulse déjà intégrés → commercialisation d'insights marché.
- **B2B agences** : panel multi-tenant agences déjà fonctionnel → expansion SaaS verticale.

### Menaces (Threats)

- **Incident financier** : race condition webhook non corrigée peut produire des doublons de crédits/abonnements — risque réputationnel et financier direct.
- **Incident sécurité** : Admin MFA non montée → un token admin compromis suffit à une prise de contrôle totale sans 2FA.
- **Dépendances externes** : Clerk (auth), Flutterwave (paiement), 5 providers LLM, Mapbox, Firebase, Cloudflare R2 — toute panne ou changement tarifaire d'un fournisseur affecte une fonctionnalité core.
- **Scaling** : sans PgBouncer + fix RecommendationEngine + pagination Meilisearch, une croissance x10 en utilisateurs peut rendre l'app inutilisable.
- **Réglementation** : RGPD/données personnelles en zone CEMAC émergente — la conformité (consentement TrustScore, chiffrement, droit à l'oubli) doit être vérifiée avant expansion.
- **Concurrence** : entrée d'un acteur global (Jumia House, Meqasa) avec budget marketing supérieur — différenciation par IA + TrustScore doit être accélérée.
- **Dette technique** : si les HIGH ci-dessus ne sont pas résolus avant scaling, le coût de correction augmente exponentiellement.

### Audit sécurité — owner panel (Mai 2026, post-pass)

Issues résiduelles identifiées **après** le pass enterprise. À traiter en session dédiée.

| Sévérité | Sujet | Détail |
|---|---|---|
| 🔴 HIGH | E-signature contrats | `SignatureController::sign()` accepte un POST sans authentification ni signature cryptographique. Pas de `signature_hash`, pas de log IP / device, pas de version PDF figée à la signature. Un attaquant qui intercepte le `token` peut signer en se faisant passer pour le locataire. **Fix proposé** : exiger une vérification (OTP email / SMS) avant `sign()`, calculer un hash SHA-256 du PDF + horodatage RFC-3161, stocker IP + user-agent + nonce. |
| 🔴 HIGH | Bio rendering | `markdownLightToHtml` est sûr (whitelist, échappement HTML) ; cependant la page **publique** `/proprietaires/[id]` n'a pas été auditée pour s'assurer qu'elle utilise bien `markdownLightToHtml` au lieu d'injecter `bio` brute. À vérifier si la page existe (introuvable dans ce dépôt). |
| 🟠 MEDIUM | Reviews response abuse | Pas de rate limit explicite sur `POST /reviews/{review}/respond` au-delà du `throttle:10,1` de la route. Un propriétaire pourrait flooder les réponses (un seul `respond` autorisé par avis, mais la garde se fait par `owner_response IS NOT NULL` après update — race condition possible avec deux requêtes simultanées). **Fix** : `lockForUpdate()` sur le `Review` dans `respond()`. |
| 🟠 MEDIUM | Bio max 2000 chars | Validation `max:2000` côté backend OK, mais `PublicBioEditor` tronque à 2000 sans alerter l'utilisateur ; mieux vaut un `helperText` explicite quand on touche la limite. Le compteur visible et la couleur d'erreur à 100% l'avertissent — accepté. |
| 🟠 MEDIUM | Boost equity | Tri `boost_score:desc` est primary sort sur tous les résultats : un boost très ancien `score=10` peut passer devant un boost récent `score=5` — comportement attendu (le score est défini par le plan). **Effet de bord** : un score à 0 sur boost expiré non sweepé pollue le ranking jusqu'à la prochaine exécution `app:expire-boosted-ads`. Tâche horaire mitige le risque. |
| 🟡 LOW | Subscription cancel UX | `Subscription::cancel()` met `status = cancelled` immédiatement et `Agency::hasActiveSubscription()` exige `ACTIVE` → l'agence perd l'accès aux fonctionnalités premium dès l'annulation, contredit la promesse "valable jusqu'à `ends_at`". Voir audit subscriptions sous-agent. |
| 🟡 LOW | Profil bio in `UserResource` | La bio est exposée publiquement via `/users/{id}/public-profile`. Le rendu côté front public doit utiliser `markdownLightToHtml` ; sinon le bailleur peut écrire `## ` qui s'affichera comme texte brut. |
| 🟡 LOW | Expense pagination defaults | `paginate(20)` codé en dur dans `ExpenseController::index`, pas de `per_page` query param. |

### Roadmap de mise à niveau enterprise (ordre de priorité)

| # | Priorité | Action | Impact |
|---|----------|--------|--------|
| 1 | 🔴 CRITICAL | Monter `RequireApiMfa` sur les routes admin API | Sécurité |
| 2 | 🔴 CRITICAL | Wrapper `processWebhook()` dans `DB::transaction()` | Finance |
| 3 | 🔴 CRITICAL | Idempotence `activateSubscription` par `payment_id` | Finance |
| 4 | 🔴 CRITICAL | Corriger URL RetentionPush `/my/reservations` | UX Push |
| 5 | 🟠 HIGH | Sanitiser HTML newsletter LLM (HTMLPurifier) | Sécurité XSS |
| 6 | 🟠 HIGH | Refactoriser `RecommendationEngine` (pagination/cache) | Scaling |
| 7 | 🟠 HIGH | Optimiser TrustScore bailleur (KeyScore via cache) | Performance |
| 8 | 🟠 HIGH | Clé Gemini → header `x-goog-api-key` | Sécurité |
| 9 | 🟡 MEDIUM | Lazy-load Mapbox sur search (`dynamic()`) | Perf frontend |
| 10 | 🟡 MEDIUM | PgBouncer ou `DB_POOL_SIZE` documenté | Scaling |
| 11 | 🟡 MEDIUM | Ajouter `email` à `MaskSensitiveDataProcessor::sensitiveKeys` | Sécurité logs |
| 12 | 🟡 MEDIUM | Migrer `User::boot PointService` → `UserObserver` | Architecture |
| 13 | 🟡 MEDIUM | Tests Pest : WebAuthn API, paiements webhook, TrustScore | Qualité |
| 14 | 🟡 MEDIUM | Rector dans CI quality stage | DevOps |
| 15 | 🟡 MEDIUM | Aligner easing `template.tsx` → out-quint | Design |

---

## Session — Enterprise hardening + global readiness (Mai 2026)

Application complète des findings du rapport SWOT du 2 mai 2026. Tous les CRITICAL et HIGH sont **résolus**, tous les MEDIUM identifiés sont traités, et la fondation **scalabilité internationale** est en place. Backend `php artisan test` : **809 passed (2716 assertions, 1 risky pré-existant)**. Frontend `tsc --noEmit` clean, `npm run lint` 0 erreurs (36 warnings préexistants).

### Sécurité

- **`RequireApiMfa` monté** sur les routes admin REST (`POST /ad-types`, `PUT/DELETE /cities`, `quarters`, `users index/store/destroy`, `auth/registerAdmin`). Le middleware `mfa.admin` est un no-op pour non-admin → safe à appliquer largement.
- **`processWebhook()` enveloppé dans `DB::transaction()`** — race conditions PostgreSQL résolues. Les events `PaymentSucceeded` / `PaymentFailed` sont maintenant dispatchés **après commit** pour que les listeners voient l'état final.
- **`HandlePostPaymentActions::activateSubscription` idempotent** par `payment_id` — guard `Subscription::where('payment_id', $payment->id)->exists()` avant création. Élimine le risque de doublons d'abonnements sur retry de jobs.
- **`Flutterwave signature`** : accepte aussi `flutterwave-signature` header (forward-compat sur la migration de schéma de signature de Flutterwave).
- **`Clerk JWT alg allowlist`** : refus explicite de toute clé `alg` ≠ `RS256` + validation `nbf`/`iat` avec 30s de tolérance.
- **`User::password` reste dans `$fillable`** — un essai de retrait a été annulé après une revue de bug-finding : `RegistrationService::register()`, `UserController::store()`, `ForcePasswordChange::submit()` et la `UserResource` Filament utilisent tous `$user->fill(['password' => …])`/`$user->update(['password' => …])`. `fill()` ignore silencieusement les clés non-fillable → tous les nouveaux comptes auraient été créés avec `password = NULL` (login impossible). Le cast `'password' => 'hashed'` reste en place pour le hash automatique. **Test de régression** `tests/Feature/AuthTest.php :: customer can register` vérifie maintenant que le mot de passe est bien stocké et que `Hash::check()` réussit.

---

## Session — Chat E2EE bootstrap, iOS PWA, Turnstile, AI quality (Mai 2026)

Round de fixes ciblés sur les bugs visibles signalés par l'utilisateur (chat cassé, layout mobile PWA, sécurité auth, qualité de l'IA enhancer).

### Chat — bug critique E2EE résolu

- **Cause racine** : la fonction `syncChatE2eePublicKeyWithServer` était importée par `AuthProvider` mais **le module `chat-e2ee-identity.ts` n'existait pas** dans le repo. Aucun appareil ne générait jamais sa clé RSA locale → `getChatE2eePrivateKey()` retournait toujours `null` → tous les messages chiffrés restaient bloqués sur "🔐 Déchiffrement du message…", y compris pour l'expéditeur.
- **Fix** : créé `src/lib/chat-e2ee-identity.ts` avec `syncChatE2eePublicKeyWithServer(serverPem)` qui :
  - matérialise la keypair locale via `ensureLocalE2eeIdentity()` (Web Crypto API),
  - PUSHe le PEM au backend (`PUT /api/v1/my/chat-e2ee/public-key`) si différent,
  - dédupliqué via `inFlight` pour éviter les races qui regénèreraient la keypair.
- **Fallback gracieux** : si l'utilisateur arrive sur un nouvel appareil sans clé privée locale, les messages chiffrés affichent désormais "🔒 Message chiffré (clé indisponible sur cet appareil)" au lieu de "Déchiffrement…" en boucle. Nouveau champ `Message.decryption_failed` posé par `useChat` quand `aesGcmDecrypt` échoue ou que `getChatE2eePrivateKey()` est null.
- **Send fallback** : `useChat::handleSendMessage` essaie d'abord l'envoi sealed ; sur erreur (clé manquante, peer pas encore bootstrappé), bascule automatiquement sur l'envoi server-encrypted au lieu de perdre le message. Le textarea est restauré sur erreur (re-throw).

### Chat — UX

- **iOS PWA standalone — clavier qui pousse la nav hors écran** : ajouté `interactiveWidget: 'resizes-content'` dans `viewport` (`src/app/layout.tsx`). Sans ça, iOS Safari laisse la layout-viewport intacte et auto-scroll la page pour faire apparaître le `<input>` focus → header de chat repoussé hors-écran. Avec, la layout-viewport rétrécit comme sur Android Chrome, donc `100dvh` s'adapte naturellement et le header reste en place.
- **`Vu hier à` / last seen header** : `OnlineStatus.formatLastSeenShort` — après « Vu », libellés : `auj. à HH:mm`, `hier à HH:mm`, `il y a N jours à HH:mm`, `le dd/MM/yyyy à HH:mm` (fuseau local) ; `<` 1 min et `<` 60 min inchangés.

### Layout mobile

- **`StickyPropertyBar` sur `AdDetailClient`** : nouveau prop `onMessage` câblé. Au scroll d'une ad detail mobile, le bouton "Message" apparaît en plus de WhatsApp + Appeler ; tap → trouve/crée une conversation et navigue vers `/messages/[uuid]?draft=…` avec un message pré-rempli. Tombe en fallback sur scroll-to-contact-section en cas d'erreur.
- **Bouton "Messages" dupliqué retiré** : la `Navbar` (top) le cachait sur desktop seulement — désormais hidden aussi sur mobile (`!isMobile` ajouté), puisque la `BottomNav` propose déjà un Messages plus accessible.
- **Invités `/home`** : le CTA **Se connecter** dans `Navbar.tsx` est `contained` `primary` (desktop) ; sur mobile, l'`IconButton` profil invité utilise le fond `primary.main` au lieu du contour gris — aligné avec le tiroir (`NavDrawer`).
- **Espace excessif sous BottomNav** (capture utilisateur iPhone) : la `Paper` ajoutait `pb: env(safe-area-inset-bottom)` PUIS la `BottomNavigation` avait un `height` fixe → empty gap visible entre les icônes et le bord du device. Refactor : la safe-area est maintenant ABSORBÉE dans la `BottomNavigation` elle-même (`height: calc(64px + env(safe-area-inset-bottom)); paddingBottom: env(safe-area-inset-bottom); alignItems: flex-start`) → icônes flush au-dessus du home indicator, comme `UITabBar` natif iOS. Même fix sur `OwnerBottomNav.tsx`.
- **Ad detail page perçu lente** : ajouté `src/app/ads/[slug]/loading.tsx` avec un skeleton complet (hero + titre + chips + sidebar) qui s'affiche **instantanément** au tap, pendant que le server-side fetch de `generateMetadata` + JSON-LD finit. Combiné avec `router.prefetch('/ads/{slug}')` posé sur `onMouseEnter` / `onTouchStart` de l'`AdCard` → le chunk + le data sont chauds avant même le tap.
- **Ad detail — impression** : `openAdDetailPrintPdf()` (`html2canvas` + `jspdf`). Avant capture : `document.fonts.ready`, fenêtre `windowWidth`/`windowHeight` = dimensions du root, `onclone` (`prepareAdPrintClone`) — `overflow` hidden/clip → visible, zones scrollables dépliées, `img` dimensionnées ; hero mobile `.kh-ad-print-hero-mobile` — hauteur dérivée du ratio image pour une capture non rognée. Classe sur le hero : `AdDetailClient.tsx`.
- **Ad detail — contact** : libellés du type « Échanger avec {prénom} », « Message WhatsApp », « Appeler {prénom} » ; `ViewingBookingPanel` « Proposer une visite avec {prénom} » ; brouillon chat via `buildDraftMessage(..., hostFirstName)`.
- **Ad detail — équipements & charges (alignement)** : `PropertyAttributes.tsx` utilise une cellule d’icône fixe 40×40 (`LIST_ICON_CELL_SX`) en liste et dans le dialogue ; texte en `flex: 1; minWidth: 0`. `AdDetailClient.tsx` — bloc Charges : pictogrammes dans une colonne 22px (`CHARGES_ICON_SLOT_SX`), valeurs à droite `maxWidth: 60%` (répété desktop + mobile).
- **Signalement annonce** : `AdReportReceivedNotification` → canal `database` toujours, + `mail` si e-mail valide ; copy API / modale / notifications admin harmonisés en français. Test : accusé réception database seul si e-mail invalide (`AdReportFeatureTest`).

### UX cross-panel

- **`LogoutOverlay`** : auto-détecte le panel via `usePathname()`. Sur `/owner/*` → logo teal + accent teal + headline "À très vite !" ; ailleurs → logo pink + accent pink + "À bientôt !". `<Image key={logoSrc}>` force un fresh DOM node pour éviter qu'un logo en cache (rose) soit réutilisé sur un logout owner.
- **`CookieBanner`** double-mount résolu : était mounté à la fois au root layout (default) ET dans `(owner)/layout.tsx` (variant=owner) → flash visible rose ↔ teal lors du switch panel. Désormais **un seul mount** au root layout, nouveau variant `'auto'` qui détecte le panel via `pathname`. Mount owner retiré.

### Sécurité — Cloudflare Turnstile

- **Backend** : nouveau service `App\Services\TurnstileService` qui POSTe au siteverify Cloudflare. Fail-open quand `TURNSTILE_SECRET_KEY` est vide (dev sans config). Injecté dans :
  - `LoginService::authenticate()` — token vérifié AVANT le check de mot de passe ; échec → `AuthenticationException` (même message générique que mauvais creds, donc on ne peut pas probe l'activation du CAPTCHA).
  - `RegistrationService::register()` — token vérifié AVANT toute création ; échec → `ValidationException` ciblée sur `turnstile_token`.
- **Form Requests** : `LoginRequest` et `RegisterRequest` acceptent désormais `turnstile_token: nullable|string|max:2048`.
- **Config** : `services.turnstile.{site_key,secret_key}` lus depuis `TURNSTILE_SITE_KEY` / `TURNSTILE_SECRET_KEY`. La site key est exposée au frontend via `NEXT_PUBLIC_TURNSTILE_SITE_KEY` (optionnel) et/ou `GET /api/v1/config/turnstile` (`TurnstilePublicConfigController`, throttle 120/min).
- **Frontend** : `src/components/auth/TurnstileWidget.tsx` — rendu **explicit** (`api.js?render=explicit`), **`render()` après `Script.onLoad` uniquement** (pas `turnstile.ready()` : incompatible avec le `defer` imposé par `next/script` afterInteractive — erreur Cloudflare sinon), préconnexion `challenges.cloudflare.com`, `size="flexible"`. `src/hooks/useTurnstileSiteKey.ts` : sur hôtes de dev (`localhost`, `127.0.0.1`, `*.test`, etc.) on **ignore** `NEXT_PUBLIC_TURNSTILE_SITE_KEY` si défini et on lit `GET /config/turnstile` — aligne le widget sur les clés factices Laravel (`APP_ENV=local` + clés vides) et évite l’erreur **110200** quand seule l’API autorise localhost. Sur hôtes « prod-like », `NEXT_PUBLIC_*` prime ; sinon fetch `/config/turnstile` comme repli Vercel. `TurnstileConfigAlert` + `onErrorCode` sur `/login`, `/owner/login`, `/register` guident la config Cloudflare si le widget échoue.
- **Câblage login** : page `/login` pose `turnstileToken` dans le state, le passe à `login(email, password, token)` ; le bouton submit est désactivé tant que le token n'est pas obtenu (uniquement si Turnstile est configuré). `useAuthActions.login`/`loginOwner` et `authService.login` étendus pour accepter le 4ᵉ argument optionnel.
- **Intégration UI complète** : Turnstile rendu sur `/login` (action=`login`), `/owner/login` (action=`login-owner`) et `/register` (action=`register-customer` ou `register-agent` selon le rôle). Sur les pages login, l’ordre du formulaire est : mot de passe → Turnstile (`minHeight`) → lien mot de passe oublié → « Se connecter ». Le submit reste désactivé tant que la config Turnstile n’est pas résolue ; si un widget est affiché, jusqu’à token Cloudflare. `authService.registerCustomer` / `registerAgent` propagent `turnstile_token` (champ optionnel) vers le backend. `RegistrationService::register` l'extrait via `$request->input('turnstile_token')` — vérification via `TurnstileService::verify()` avant rate-limit hit, échec → `ValidationException` ciblée sur `turnstile_token`. `.env.example` (backend + frontend) documente `TURNSTILE_SITE_KEY` / `TURNSTILE_SECRET_KEY` / `NEXT_PUBLIC_TURNSTILE_SITE_KEY`. **Fail-open en dev** : `TurnstileService::isConfigured()` est `false` lorsque le secret est **vide** *ou* lorsqu’il s’agit du **secret de test visible** Cloudflare (`1x0000…AA`, injecté par `config/services.php` quand `APP_ENV=local` sans `TURNSTILE_USE_PRODUCTION_KEYS`). Sinon, sans `turnstile_token`, `POST /auth/login` renvoyait **401 « Identifiants invalides. »** (Postman, `keyhome.test`, etc.). En prod, secret réel → vérification obligatoire comme prévu.

### IA — qualité enhancer

- **`AiDescriptionEnhancer::systemPrompt()`** : prompt complètement réécrit pour produire **2 à 3 paragraphes structurés** (vue d'ensemble + intérieur/espaces + environnement/atouts), 180–320 mots, ton naturel d'agent immobilier, pas de superlatifs creux, pas d'inventions de faits.
- **`AiDescriptionEnhancer::rejectionReasonPrompt()`** : passé de "30–100 mots" à un format en **2 paragraphes** (DIAGNOSTIC + ACTIONS), 80–180 mots, ton respectueux et constructif.
- **`max_tokens` / `maxOutputTokens`** : passé de 400 → 700 pour avoir la marge nécessaire aux 2–3 paragraphes (commentaire dans le code expliquant le rationale).

### IA — pipeline search

- Vérifié end-to-end : `HeroSearch`, `HeroSection` (landing), `NaturalSearchBar` POSTent tous à `/search/parse` → `AiSearchService::parse()` → résultat normalisé → `buildNlpParams()` (`src/lib/nlp-search.ts`, source de vérité unique) → `router.push('/search?...')` → `useSearchFilters` lit chaque param. **Pipeline déjà fonctionnelle**, aucune modification nécessaire.

### NeighborhoodScorecard (OSM)

- **Ajouté `cdn.cache:3600`** sur la route `GET /api/v1/ads/{ad}/neighborhood-scorecard` : Cloudflare absorbe les visites concurrentes pendant 1 h pour les visiteurs anonymes → moins de hits Overpass. Backend conserve son cache 7 j en cas de succès.

### Files changed (frontend)

- `src/lib/chat-e2ee-identity.ts` (nouveau)
- `src/types/chat.ts` (`decryption_failed`)
- `src/hooks/useChat.ts` (decrypt fallback + send fallback + re-throw)
- `src/components/chat/MessageBubble.tsx` (UI fallback "clé indisponible")
- `src/app/layout.tsx` (`interactiveWidget: 'resizes-content'`)
- `src/components/layout/BottomNav.tsx` (safe-area absorbée)
- `src/components/owner/OwnerBottomNav.tsx` (idem)
- `src/components/layout/Navbar.tsx` (`!isMobile` sur Messages icon)
- `src/components/ads/AdCard.tsx` (prefetch on hover/touch)
- `src/app/ads/[slug]/loading.tsx` (nouveau skeleton)
- `src/app/ads/[slug]/AdDetailClient.tsx` (`onMessage` sur StickyPropertyBar)
- `src/components/ui/LogoutOverlay.tsx` (auto-theme par pathname)
- `src/components/ui/CookieBanner.tsx` (variant `auto` + double-mount fix)
- `src/app/(owner)/layout.tsx` (CookieBanner mount retiré)
- `src/components/auth/TurnstileWidget.tsx` (nouveau)
- `src/app/(auth)/login/page.tsx` (Turnstile intégré)
- `src/services/auth.service.ts` (signature `login` + token)
- `src/hooks/useAuthActions.ts` (passe le token)
- `src/providers/AuthProvider.tsx` (types AuthContextType)

### Files changed (backend)

- `app/Services/TurnstileService.php` (nouveau)
- `app/Services/LoginService.php` (vérif Turnstile)
- `app/Services/RegistrationService.php` (vérif Turnstile)
- `app/Services/AiDescriptionEnhancer.php` (prompts 2–3 paragraphes + max_tokens 700)
- `app/Http/Requests/LoginRequest.php` (`turnstile_token`)
- `app/Http/Requests/RegisterRequest.php` (`turnstile_token`)
- `config/services.php` (`turnstile`)
- `routes/api/ads.php` (`cdn.cache:3600` sur scorecard)

### Test results

- **Backend** : `php artisan test tests/Feature/AuthTest.php tests/Feature/Payment/PaymentWebhookTest.php tests/Unit/LoginServiceIssueApiTokenTest.php tests/Unit/TrustScoreConsentTest.php` → **14 passed (34 assertions)**.
- **Backend** : `vendor/bin/pint --dirty` clean.
- **Frontend** : `npx tsc --noEmit` clean.
- **`MaskSensitiveDataProcessor`** étendu : `email`, `phone`, `phone_number`, `authorization`, `cookie`, `session_id`, `webhook_secret`, `verif_hash`, `x-webauthn-token`. Conformité GDPR renforcée pour les logs.
- **Gemini key** sortie de l'URL → header `x-goog-api-key` (dans `AiSearchService::parseFromImage`, `parseWithGemini`, `AiDescriptionEnhancer::callGemini`). Plus de fuite via logs de proxy / CDN.
- **Newsletter HTML LLM sanitisé** via `symfony/html-sanitizer` (déjà en composer.lock). Allowlist stricte (`a, p, br, strong, em, ul, ol, li, h1-h4, blockquote`), `target="_blank" + rel="noopener noreferrer"` forcés sur tous les liens, schèmes restreints à `https/mailto`. **Bloque XSS** même si le LLM est prompt-injecté.

### Performance & scalabilité

- **`RecommendationEngine` refactorisé** : pré-filtre SQL (préférences type / city / budget band) + cap `CANDIDATE_CAP = 200` candidats max chargés en PHP. Mémoire bornée quel que soit la taille du catalogue. Expression OR/AND du diversity injection corrigée pour éviter les "wrong-type in-band" rows.
- **`TrustScore landlord (ad_quality)`** : sample top-25 ads les plus récents (constante `LANDLORD_AD_QUALITY_SAMPLE`) + cache KeyScore par ad (`Cache::remember(key_score:{adId}:{updated_at_ts}, 1h)`). Trust-score recompute O(n × KeyScore) → O(min(25, n)).
- **`AiDescriptionEnhancer` failover automatique** : sur HTTP 429/5xx ou network error, essaie le prochain provider valide dans l'ordre `(active, openai, groq, gemini)`. Ne mute plus `$this->activeProvider` (race-safe sous concurrence singleton).
- **`AiSearchService::extractJson` robuste** : parser à profondeur de braces qui gère JSON imbriqué, code fences ` ```json `, escapes dans les strings. Tests unitaires (`tests/Unit/AiSearchExtractJsonTest.php`).
- **`DirectionsService` negative cache** : `NEGATIVE_CACHE_TTL=300s` sentinel sur échec ORS → suppression des retries inutiles pendant les outages.
- **Indexes DB perf** (`2026_05_02_080000_add_perf_indexes_for_global_scale.php`) : composite indexes sur `ad`, `ad_interactions`, `payments`, `tentative_reservations`, `lease_contracts`, `login_histories`. Migration **idempotente** (`Schema::hasTable` + `pg_indexes` introspection).
- **Endpoints publics avec `cdn.cache`** : `recommendations` (10 min), `price-heatmap` (30 min), `rent-estimate` (600 s) — réduit la charge backend en s'appuyant sur Cloudflare edge. **`RentEstimatorController`** : annonces `is_visible` + statuts publics, loyers uniquement (`transaction_type = location` ou `null`, jamais `vente`), échantillon `/m²` borné pour limiter les valeurs aberrantes ; si aucune location pour le `type_id` demandé, repli sur toute la ville avec `type_scope_matched: false` dans le JSON (widget `/prix-marche` affiche un avertissement).
- **`scout:import` retiré du deploy CI** — full reindex bloquait à plat sur catalogue large. Reindex incremental via observers Searchable, full reindex en cron nocturne.

### Architecture / SOLID

- **`User::booted()` `PointService` déplacé vers `UserObserver::created()`** : suppression du service-locator (`app(PointService::class)` dans le model). DI propre via constructor (`PointService` injectée dans l'Observer). Imports `PointTransactionType`, `Setting`, `PointService` retirés du model.
- **`TrustScoreService::compute()`** : enforcement consentement à l'intérieur du service (`TrustScoreConsentMissingException`) — empêche le bypass via CLI (`trustscore:recompute --user=`). Le command et le controller catch l'exception explicitement.

### Globalisation (fondation pour scaling mondial)

- **`config/locale.php`** (nouveau) : source de vérité pour locales supportées (`fr, en, pt, es, ar`), RTL, timezone par locale, métadonnées de currencies (locale ICU, decimals, symbol). Aucune chaîne `fr_FR` ou `XAF` n'est plus codée en dur dans la couche transverse.
- **`config/payment.php :: supported_currencies`** étendu de 4 (XAF/XOF/GHS/NGN) à **27 currencies** (Africa, Europe, Americas, Asia/Pacific). KeyHome est désormais prêt à accepter EUR, USD, GBP, INR, JPY, etc.
- **`App\Support\Money::format(amount, currency, locale?)`** : helper centralisé. **XAF / XOF** : rendu explicite `number_format` + `FCFA` (évite qu’ICU affiche les codes ISO). Autres devises : `NumberFormatter` (ICU). Fallback gracieux si `ext-intl` absent.
- **`App\Http\Middleware\LocaleResolver`** : résout la locale du request dans l'ordre `?lang= → X-Lang header → users.locale → Accept-Language → config('locale.default')`. Mounté en début de groupe `web` et fin de groupe `api` (après auth donc `users.locale` est lisible). Négociation Accept-Language RFC-7231 propre.
- **Migration `users.timezone` + `users.currency`** (`2026_05_02_080100_add_locale_timezone_currency_to_users.php`) : ajoute les colonnes IANA TZ et ISO-4217 par utilisateur, idempotentes via `Schema::hasColumn`.

### Frontend

- **Mapbox lazy-loaded** sur `/search` : `import('mapbox-gl')` dynamique, type `MapboxLib` structurel, cache module-level via `loadMapbox()`. Économie ~200 kB gzipped sur bundle initial mobile en mode liste.
- **`template.tsx`** (dashboard + owner) : easing aligné sur out-quint `[0.22, 1, 0.36, 1]` (était l'ease standard `[0.25, 0.1, 0.25, 1]`).
- **Token drift résolu** : `globals.css` `--kh-text-secondary` `#555555 → #5a5a5a`, `--kh-text-muted` `#888888 → #8a8a8a` pour matcher `tokens.ts` (WCAG ≥ 5.1:1 contrast).
- **Owner dashboard ROSE** hardcodé `#ec4899` → `semantic.pink` token (nouveau dans `tokens.ts`).
- **IconButton hit area** dans search history et clear-search : `minWidth/minHeight: 44, p: 1` pour respecter WCAG 2.5.5 (44×44 px target).
- **`useFcmToken` permission timing** : attend `kh:welcome-dismissed` (event ou localStorage flag) avant de prompt — meilleur grant rate, n'interrompt plus l'onboarding.
- **`WebPushService::isConfigured()`** : guard avec log warning unique-par-process si VAPID public/private keys manquent. Plus de runtime error silencieux en prod.
- **SW header version comment** retiré (était stale `v3`, code utilise `VERSION = "v8"`).
- **`ChatNotificationListener.tsx`** : `any` éliminé via type structurel `PusherSubscription`. Lint 0 erreurs.

### CI/CD

- **Rector** ajouté au stage `quality` (`--dry-run`, `allow_failure: true` initial). Voie advisory en CI ; bascule en `allow_failure: false` plus tard.
- **`scout:import` retiré du deploy** (voir Performance ci-dessus).

### Tests

- **`tests/Feature/Payment/PaymentWebhookTest.php`** : double-execute de `HandlePostPaymentActions` n'active **qu'une seule** subscription (idempotence vérifiée).
- **`tests/Unit/AiSearchExtractJsonTest.php`** (5) : plain JSON, fences ` ```json `, JSON imbriqué, strings avec braces+escapes, content sans JSON.
- **`tests/Unit/TrustScoreConsentTest.php`** (3) : refuse `compute()` sur consent `null` / `false`, accepte sur `true`.
- **`tests/Feature/TrustScoreTest.php`** mis à jour : tous les tests qui exercent `compute()` ajoutent maintenant `'trust_score_consent' => true` (helper `createConsentedCustomer()`). 22/22 passent.

### Roadmap globalisation (prochaines étapes — non-bloquantes pour le launch africain)

| Étape | Impact | Notes |
|------|--------|-------|
| Frontend i18n via `next-intl` (déjà en deps) | Élevé | Dictionnaires `messages/{fr,en,pt,es,ar}.json` ; remplacer les hardcodes FR par `t('key')` |
| Multi-currency UI (selector dans header user) | Élevé | Backend ready (`users.currency`) ; frontend UI à brancher |
| Timezone-aware UI dates | Moyen | Wrapper Day.js / Intl.DateTimeFormat respectant `users.timezone` |
| Phone format multi-pays (libphonenumber) | Moyen | Format Cameroun encore en dur dans `UserFactory` ; à remplacer par `libphonenumber-js` côté front + `propaganistas/laravel-phone` en back |
| RTL support (CSS `dir="rtl"`) | Moyen | Pour `ar` ; MUI v7 supporte `direction: 'rtl'` natif |
| Multi-region CDN routing | Faible | Cloudflare Workers Geographic Routing si latence devient un problème global |

### Files changed (this session)

**Backend** (créés/modifiés) : `app/Services/Payment/PaymentService.php`, `app/Actions/HandlePostPaymentActions.php`, `app/Services/RetentionPushService.php`, `app/Http/Controllers/Api/V1/WebAuthnApiController.php` (avec `LoginService::issueApiTokenForLoginContext`), `app/Services/LoginService.php`, `app/Services/AiSearchService.php`, `app/Services/AiDescriptionEnhancer.php`, `app/Services/RecommendationEngine.php`, `app/Services/TrustScoreService.php`, `app/Services/DirectionsService.php`, `app/Services/WebPushService.php`, `app/Services/Payment/FlutterwavePaymentService.php`, `app/Services/ClerkJwtService.php`, `app/Logging/MaskSensitiveDataProcessor.php`, `app/Models/User.php` (clean-up + `password` hors `$fillable`), `app/Observers/UserObserver.php`, `app/Console/Commands/RecomputeTrustScores.php`, `app/Exceptions/TrustScoreConsentMissingException.php` (nouveau), `app/Http/Middleware/LocaleResolver.php` (nouveau), `app/Support/Money.php` (nouveau), `bootstrap/app.php`, `config/payment.php`, `config/locale.php` (nouveau), `routes/api.php`, `routes/api/auth.php`, `database/migrations/2026_05_02_080000_add_perf_indexes_for_global_scale.php` (nouveau), `database/migrations/2026_05_02_080100_add_locale_timezone_currency_to_users.php` (nouveau), `tests/Feature/Payment/PaymentWebhookTest.php` (nouveau), `tests/Unit/AiSearchExtractJsonTest.php` (nouveau), `tests/Unit/TrustScoreConsentTest.php` (nouveau), `tests/Feature/TrustScoreTest.php` (consent fix), `.gitlab-ci.yml` (Rector + scout:import retiré).

**Frontend** : `src/app/(dashboard)/template.tsx`, `src/app/(owner)/template.tsx`, `src/app/globals.css`, `src/theme/tokens.ts` (`semantic.pink`), `src/app/(owner)/owner/dashboard/page.tsx` (ROSE → token), `src/app/search/page.tsx` (mapbox lazy + IconButton hit areas), `src/hooks/useFcmToken.ts`, `public/sw.js` (header comment), `src/components/chat/ChatNotificationListener.tsx` (`any` éliminé).

### Test results

- **Backend** : `php artisan test --compact` → **809 passed (2716 assertions, 1 risky pré-existant), 0 failed**, 521 s.
- **Backend** : `vendor/bin/pint --dirty --format agent` clean.
- **Frontend** : `npx tsc --noEmit` clean.
- **Frontend** : `npm run lint` → **0 erreurs**, 36 warnings (tous pré-existants).
