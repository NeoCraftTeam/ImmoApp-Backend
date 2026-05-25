# KeyHome — Audit Organisation Codebase
**Date :** 25 mai 2026 | **Scope :** Backend (Laravel 12) + Frontend (Next.js 16) | **Niveau :** Standard

---

## Résumé Exécutif

| Dimension | Backend | Frontend | Verdict |
|---|---|---|---|
| Structure de dossiers | ✅ Bon socle | ⚠️ `lib/` trop plat | Moyen |
| SOLID | ✅ SRP/DI respectés | N/A | Bon |
| Groupement services | ⚠️ Partiellement | ⚠️ `owner/` seul sous-dossier | À corriger |
| Docs / `.md` | ❌ 9 fichiers racine | — | À corriger |
| Lisibilité new dev | ⚠️ Controllers plats | ⚠️ `lib/` non documenté | Moyen |

---

## 1. Backend — Laravel 12

### 1.1 Ce qui est bien fait ✅

- **Séparation des couches** : `Controllers / Services / Models / Actions / DTOs / Contracts / Policies / Rules / Enums` — architecture en couches propre.
- **Interfaces** : 5 contrats dans `app/Contracts/` (`PaymentGatewayInterface`, `AiSearchServiceInterface`, `RecommendationEngineInterface`, `TrustScoreServiceInterface`…).
- **Services sous-groupés** : `Payment/`, `Chat/`, `Admin/`, `Media/`, `WebAuthn/` existent.
- **Qualité code** : `final readonly` services, `declare(strict_types=1)`, PHPStan level 5 OK.
- **Tests** : 610 tests organisés par feature.

### 1.2 Gaps identifiés

#### ❌ GAP-B1 — Dossier `app/Ai/` créé mais **vide**

```
app/Services/
  AiSearchService.php        ← devrait être dans Ai/
  AiDescriptionEnhancer.php  ← devrait être dans Ai/
  AiDigestService.php        ← devrait être dans Ai/
  NaturalSearchRegexParser.php ← devrait être dans Ai/
  RecommendationEngine.php   ← devrait être dans Ai/
  AcquisitionChannelClassifier.php ← devrait être dans Ai/
```

**Recommandation :** Déplacer ces 6 services dans `app/Services/Ai/` (le dossier existe déjà).

#### ⚠️ GAP-B2 — Services Auth non regroupés

```
app/Services/
  LoginService.php       ←┐
  RegistrationService.php ←┤ regrouper dans Auth/
  TokenService.php        ←┤ (dossier app/Auth/ existe mais pour autre chose)
  ClerkJwtService.php     ←┤
  OtpService.php         ←┘
```

**Recommandation :** Créer `app/Services/Auth/` et y déplacer ces services.

#### ⚠️ GAP-B3 — Services de notification non regroupés

```
app/Services/
  SmsService.php         ←┐
  WhatsAppService.php    ←┤ regrouper dans Notification/
  WebPushService.php     ←┘
  RetentionPushService.php ←
```

**Recommandation :** Créer `app/Services/Notification/`.

#### ⚠️ GAP-B4 — Services géo non regroupés

```
app/Services/
  IsochroneService.php              ←┐
  DirectionsService.php             ←┤ regrouper dans Geo/
  NeighborhoodScorecardService.php  ←┘
```

**Recommandation :** Créer `app/Services/Geo/`.

#### ⚠️ GAP-B5 — Controllers API : 88 fichiers dans un seul dossier plat

`app/Http/Controllers/Api/V1/` contient 88 controllers sans sous-groupement.
Un nouveau développeur ne peut pas naviguer par domaine métier.

**Recommandation (progressive — ne pas casser les namespaces)** :

```
Api/V1/
  Ad/           ← AdController, AdSearchController, AdStatusController, BulkAdController,
                   AdAiController, AdDraftEditController, AdImageSearchController…
  Auth/         ← AuthController, ClerkAuthController, SocialAuthController,
                   RegistrationController, EmailVerificationController…
  Payment/      ← PaymentController, RefundController, StripePaymentMethodController,
                   CreditController, InvoiceController, BoostController…
  Chat/         ← ConversationController, MessageController, ChatE2eeIdentityController
  Lease/        ← LeaseContractController, SignatureController, TenantController
  Viewing/      ← ViewingReservationController, ViewingAvailabilityController
  Geo/          ← AdGeoController, QuarterController, CityController, IsochroneController
  User/         ← UserController, UserPreferenceController, LoginHistoryController…
```

**Effort estimé :** 2-3h (namespace update + test run). Priorité : **moyenne** (ne casse rien, juste inconfort navigation).

#### ❌ GAP-B6 — SRP potentiellement violé sur 4 controllers

| Fichier | Taille | Risque |
|---|---|---|
| `UserController.php` | 30 369 o (~900 lignes) | ⚠️ SRP |
| `PaymentController.php` | 27 823 o | ⚠️ SRP |
| `AdSearchController.php` | 25 669 o | ⚠️ SRP |
| `SocialAuthController.php` | 26 202 o | ⚠️ SRP |

**Recommandation :** Extraire les méthodes en sous-controllers ou déléguer davantage aux services.

#### ❌ GAP-B7 — `app/Docs/` contient des fichiers de test/prompt

```
app/Docs/PROMPT_TESTS_COMPLETS_GLOBAL.md
app/Docs/PROMPT_TESTS_SECURITE.md
```

Ces fichiers ne sont pas du code applicatif. Ils polluent le namespace `App\Docs`.

**Recommandation :** Déplacer dans `docs/testing/` ou supprimer si obsolètes.

#### ❌ GAP-B8 — 9 fichiers `.md` éparpillés à la racine

```
/  (racine)
  5_Survey_Module_Backend_Plan.md   → docs/product/
  AUDIT_FIX_PLAN.md                 → docs/audit-2026/
  COMPREHENSIVE_ASSESSMENT_REPORT.md → docs/audit-2026/
  EMAIL_TEMPLATE_AUDIT.md           → docs/audit-2026/
  SESSION_ISOLATION_FIX.md          → docs/architecture/
  ai.md                             → docs/architecture/ ou docs/research/
  audit_report.md                   → docs/audit-2026/
  production_readiness_report.md    → docs/audit-2026/
  update_summary.md                 → docs/operations/
```

Seuls `AGENTS.md`, `CLAUDE.md`, `GEMINI.md`, `README.md` ont leur place à la racine.

#### ⚠️ GAP-B9 — 3 dossiers d'audit dupliqués dans `docs/`

```
docs/audit/          ← 1 fichier
docs/audit-2026/     ← 18 fichiers
docs/audits/         ← 1 fichier
audit/               ← 3 fichiers (RACINE — dupliqués de audit-2026/)
```

**Recommandation :** Consolider tout dans `docs/audit/` et supprimer `audit/` à la racine.

#### ⚠️ GAP-B10 — DTOs trop peu utilisés

Seulement 2 DTOs (`LoginResult`, `RegistrationResult`) pour 72 services.
Plusieurs services retournent des `array` non typés là où des DTOs apporteraient de la clarté.

---

## 2. Frontend — Next.js 16

### 2.1 Ce qui est bien fait ✅

- **`src/`** correctement utilisé comme racine source.
- **`components/`** organisé par domaine métier (`ads/`, `auth/`, `chat/`, `payment/`, `owner/`…) — structure feature-based recommandée par Next.js.
- **`types/`** dossier dédié (13 fichiers TypeScript).
- **`hooks/`** dossier dédié (55 hooks).
- **`providers/`** dédié (8 fichiers).
- **`services/`** pour les appels API — bonne séparation.

### 2.2 Gaps identifiés

#### ❌ GAP-F1 — `lib/` est un dépotoir de 80+ fichiers plats

`src/lib/` contient 82 fichiers sans aucun sous-dossier (sauf `analytics/`).
Un nouveau dev ne peut pas trouver `chat-e2ee-crypto.ts` sans chercher.

**Recommandation — regroupements proposés :**

```
src/lib/
  auth/         ← auth-session.ts, auth-token.ts, auth-api-errors.ts,
                   clerk-frontend-origins.ts, clerk-signup-safe-update.ts,
                   oauth-providers.ts, oauth-redirect.ts, passkey-support.ts,
                   register-intent.ts, register-theme.ts
  chat/         ← chat-api.ts, chat-attachment-audio.ts, chat-e2ee-crypto.ts,
                   chat-e2ee-identity.ts, chat-reply-enrich.ts, chat-subscriptions.ts,
                   conversation-list-cache.ts, conversation-list-preview.ts,
                   conversation-list-sealed-decrypt.ts, conversation-list-time.ts, echo.ts
  payment/      ← payment-gateway-return.ts, payment-history-display.ts,
                   payment-return.ts, stripe.ts, stripe-checkout-total.ts,
                   stripe-confirm-return.ts
  tour/         ← psvKeyboardActions.ts, psvPitchClampForPartialEquirect.ts,
                   inferEquirectangularPanoData.ts
  owner/        ← owner-auth-assets.ts, owner-auth-flow.ts, owner-auth-theme.ts,
                   owner-dashboard-analytics.ts, owner-panel-access.ts,
                   owner-placarde-preview.ts, owner-shell-fab.ts
  geo/          ← geo.ts
  analytics/    ← (existe déjà ✅)
  seo/          ← seo-verification.ts, csp-allowlist.ts
  ui/           ← safe-area-init-inline.ts, safe-area-insets.ts,
                   mui-outlined-input-label-start-icon.ts
  utils/        ← constants.ts, currency.ts, sanitize.ts, error-messages.ts,
                   api-errors.ts, api.ts, site-url.ts, trusted-redirect.ts
```

#### ⚠️ GAP-F2 — `services/` : sous-dossier `owner/` mais tout le reste plat

```
src/services/
  owner/          ← 8 fichiers (groupé ✅)
  ads.service.ts  ← plat
  payments.service.ts ← plat
  auth.service.ts ← plat
  …
```

**Recommandation :** Soit regrouper tous les services par domaine (`ad/`, `auth/`, `payment/`…), soit garder tout plat (cohérence). Supprimer l'incohérence `owner/` isolé.

#### ⚠️ GAP-F3 — `components/ui/` : 46 composants plats

**Recommandation :** Sous-grouper en `forms/`, `feedback/`, `navigation/`, `data-display/`.

#### ⚠️ GAP-F4 — Absence de `README.md` dans `src/`

Un nouveau développeur n'a pas de point d'entrée documenté pour comprendre `src/lib/`, `src/hooks/`, `src/services/`.

---

## 3. Docs — Organisation globale

### 3.1 Structure actuelle (problèmes)

```
/  (racine)
  ├── AGENTS.md           ✅ racine OK (instructions AI agents)
  ├── CLAUDE.md           ✅ racine OK
  ├── GEMINI.md           ✅ racine OK
  ├── README.md           ✅ racine OK
  ├── 5_Survey_Module_Backend_Plan.md  ❌ → docs/product/
  ├── AUDIT_FIX_PLAN.md               ❌ → docs/audit/
  ├── COMPREHENSIVE_ASSESSMENT_REPORT.md ❌ → docs/audit/
  ├── EMAIL_TEMPLATE_AUDIT.md         ❌ → docs/audit/
  ├── SESSION_ISOLATION_FIX.md        ❌ → docs/architecture/
  ├── ai.md                           ❌ → docs/research/
  ├── audit_report.md                 ❌ → docs/audit/
  ├── production_readiness_report.md  ❌ → docs/audit/
  ├── update_summary.md               ❌ → docs/operations/
  │
  ├── audit/                          ❌ doublon → fusionner dans docs/audit/
  │   ├── audit_backend.md
  │   ├── audit_filament_panels.md
  │   ├── audit_mobile_apps.md
  │   └── audit_nextjs_frontend.md
  │
  └── docs/
      ├── audit/       ❌ 1 fichier seulement
      ├── audit-2026/  ✅ principal (18 fichiers)
      ├── audits/      ❌ 1 fichier doublon
      └── …
```

### 3.2 Structure cible recommandée

```
docs/
  README.md                  ← index général (existe ✅)
  architecture/
    overview.md              ✅
    backend-layers.md        ✅
    auth-flows.md            ✅
    payment-system.md        ✅
    codebase-organisation-audit.md  ← ce fichier
    SESSION_ISOLATION_FIX.md ← à déplacer
  audit/                     ← consolider audit/ + audit-2026/ + audits/ ici
    [tous les rapports d'audit]
  product/
    [roadmaps, plans feature]
  operations/
    [runbooks, déploiement, update summaries]
  research/
    [NLP, AI, SEO recherches]
  security/
    [audits sécurité]
  marketing/
    [calendrier publications]
  infrastructure/
    [Cloudflare, VPS, CI/CD]
  integrations/
    [Clerk, Flutterwave, Stripe, Meilisearch]
```

---

## 4. Conformité SOLID

| Principe | Backend | Frontend | Statut |
|---|---|---|---|
| **S** — Single Responsibility | ⚠️ 4 controllers >25KB | N/A | À surveiller |
| **O** — Open/Closed | ✅ Contracts + DI | ✅ Service layer abstrait | OK |
| **L** — Liskov | ✅ Interfaces respectées | N/A | OK |
| **I** — Interface Segregation | ✅ Interfaces focalisées | ✅ Hooks focalisés | OK |
| **D** — Dependency Inversion | ✅ `AppServiceProvider` bindings | ✅ Services injectés via hooks | OK |

Le seul point faible SOLID est **SRP** sur quelques gros controllers.

---

## 5. Plan d'action priorisé

| # | Action | Périmètre | Sévérité | Effort |
|---|---|---|---|---|
| 1 | **Déplacer 9 `.md` de la racine** vers `docs/` | Backend | 🟡 Medium | 15 min |
| 2 | **Consolider `audit/` + `audit-2026/` + `audits/`** dans `docs/audit/` | Backend | 🟡 Medium | 20 min |
| 3 | **Déplacer `app/Docs/`** vers `docs/testing/` | Backend | 🟡 Medium | 5 min |
| 4 | **Déplacer les 6 services AI** dans `app/Services/Ai/` | Backend | 🟡 Medium | 30 min |
| 5 | **Regrouper services Auth** dans `app/Services/Auth/` | Backend | 🟡 Medium | 30 min |
| 6 | **Regrouper services Notification** dans `app/Services/Notification/` | Backend | 🟢 Low | 20 min |
| 7 | **Regrouper services Geo** dans `app/Services/Geo/` | Backend | 🟢 Low | 15 min |
| 8 | **Créer sous-dossiers `src/lib/`** (auth/, chat/, payment/, tour/, owner/, utils/) | Frontend | 🟡 Medium | 1h |
| 9 | **Uniformiser `src/services/`** — supprimer l'incohérence `owner/` seul | Frontend | 🟢 Low | 20 min |
| 10 | **Créer `src/README.md`** point d'entrée frontend | Frontend | 🟢 Low | 30 min |
| 11 | **Extraire sous-controllers** pour UserController, PaymentController | Backend | 🔴 High (maintenabilité) | 3-4h |
| 12 | **Ajouter DTOs** pour les 5 services les plus complexes | Backend | 🟢 Low | 2h |

---

## 6. Checklist nouveau développeur

Ce qu'un nouveau dev doit trouver en < 5 minutes :

```
□ README.md racine          → existe ✅
□ AGENTS.md (conventions)   → existe ✅
□ docs/architecture/        → existe ✅ (5 fichiers)
□ Comment lancer le projet  → AGENTS.md § Build & Run ✅
□ Comment lancer les tests  → AGENTS.md § Testing ✅
□ Comment faire un commit   → AGENTS.md (convention git) ✅
□ Où sont les services ?    → app/Services/ (partiellement groupés) ⚠️
□ Où sont les types API ?   → app/Http/Resources/ ✅
□ Où sont les contrats ?    → app/Contracts/ ✅
□ src/README.md frontend    → MANQUANT ❌
□ Conventions frontend      → aucun fichier dédié ❌
```

---

*Rapport généré le 25/05/2026 — Sources : Next.js official docs (project-structure), Laravel docs, PHP Insights analysis, scan direct des deux codebases.*
