# KeyHome Feature Audit — Landing Page + Admin Panel Resources — 2026-05-22

---

## Executive Summary

1. **Landing page is solid in structure** (9 sections, dark/light mode, NLP search, dynamic stats, PWA) but misses several high-impact conversion levers: no OG image on root metadata, testimonials are hardcoded, no video walkthrough, no mobile app download CTA, bailleur plans absent from pricing section.
2. **Admin panel has 27 resources and 21 analytics widgets** — excellent coverage — but 12 models have no Filament resource (Invoice, LeaseContract, LoginHistory, TentativeReservation, SearchAlert, Document, AdBoost, AdInteraction, Expense, TeamInvitation, AnonymousSurveyResponse, NotificationPreference).
3. **Critical security gap**: Filament admin has no MFA/2FA — Filament v4 ships built-in TOTP support, should be enabled immediately.
4. **Testimonials are fully static fallback** with hardcoded rating "4.6" and "120+" — no API fetch. A `PlatformTestimonial` model or an admin-managed curated reviews endpoint is needed.
5. **Missing OG image** on the root `/` landing page metadata and no WhatsApp / live chat widget — both are low-effort, high-impact conversion wins for the West African market.

---

## Critical Findings (must fix before next launch)

| # | Finding | Module | Impact |
|---|---------|--------|--------|
| 1 | **No MFA on Filament admin** — attacker with stolen password = full platform access | Admin Panel | 🔴 Critical |
| 2 | **No `og:image`** on root landing page (OG title/description present but image missing) | Landing Page | 🔴 Critical |
| 3 | **Testimonial rating hardcoded** ("4.6 / 120+") — not from real data | Landing Page | 🟡 Medium |
| 4 | **InvoiceResource missing** — team cannot view/download/reconcile invoices from admin UI | Admin Panel | 🔴 Critical |
| 5 | **LeaseContractResource missing** — lease contracts not accessible in admin panel | Admin Panel | 🔴 Critical |
| 6 | **LoginHistoryResource missing** — no security audit trail in admin UI | Admin Panel | 🔴 Critical |

---

## Module Reports

---

### MODULE: Landing Page

#### Current KeyHome Implementation

- **Stack**: Next.js 16 + MUI + Framer Motion + Clerk + TanStack Query
- **Key files**:
  - `src/app/page.tsx` — root page metadata
  - `src/components/landing/LandingPage.tsx` — section orchestrator
  - `src/components/landing/HeroSection.tsx` — NLP search + animated stats
  - `src/components/landing/TestimonialsSection.tsx` — social proof
  - `src/components/landing/PricingSection.tsx` — credit packs
  - `src/components/landing/FeaturesSection.tsx` — 6 feature cards
  - `src/app/layout.tsx` — root layout with JsonLd + CookieBanner + PWAInstallPrompt

#### What is already implemented ✅

| Feature | Notes |
|---------|-------|
| 9-section landing (Hero → Footer) | HeroSection, FeaturesSection, HowItWorksSection, PricingSection, LandlordSection, TestimonialsSection, FAQSection, CTASection, NewsletterSection |
| NLP AI search in hero | POST `/search/parse`, `buildNlpParams`, animated typewriter placeholder |
| Dynamic hero stats | count-up animation, `useLandingStats()` + `useCountUp()` |
| Quick search suggestions | 4 hardcoded shortcuts in hero |
| City/type landing pages | `/immobilier/[ville]` + `/type-bien/[type]` |
| Dark/light mode | `LandingThemeContext` with full token set |
| Cookie consent / RGPD | `CookieBanner` + `ConsentModeUpdater` in root layout |
| Organization JSON-LD | `JsonLd` component in root `<head>` |
| PWA install prompt | `PWAInstallPrompt` in root layout |
| OG + Twitter metadata | Title + description + type + locale in `page.tsx` |
| Footer SEO links | City, type-bien, comparaison, nearby links |
| Testimonials section | 4 static cards + aggregate "4.6 / 120+" badge |
| Pricing section | Dynamic from API (`creditsService`) with 3-tier fallback |
| Bailleur section | Separate value prop for landlords |
| FAQ section | Exists |
| Back-to-top button | Fixed, animated, accessible aria-label |
| `reducedMotion="user"` | Framer Motion respects system preference |
| LCP optimization | Three.js canvas skipped on mobile (`window.innerWidth < 768`) |
| Canonical URL | `alternates.canonical` in metadata |

#### Expert Best Practices Found

| Practice | Source | Priority |
|----------|--------|----------|
| Place social proof near CTAs | shipixen.com — 34% boost in conversions | HIGH |
| Dynamic testimonials from real data | kndigital.co — static data damages trust | HIGH |
| App store download badges | Africa proptech — 55%+ mobile traffic | HIGH | 
| Bailleur plan cards in pricing section | SaaS best practice — dual buyer audience | MEDIUM |
| OG image on all pages | SEO/sharing best practice | HIGH |
| Comparison section vs alternatives | grafit.agency — "Why KeyHome?" | MEDIUM |
| First-person CTA copy ("Je commence") | shipixen.com — 90% boost in click-throughs | MEDIUM |
| Country/city-aware personalization | shipixen.com — 6x higher conversions | LOW |

#### Security Findings

- ✅ RGPD consent banner implemented
- ✅ Organization JSON-LD in `<head>`
- ✅ No sensitive keys in client bundle (env vars correctly prefixed)
- ⚠️ `og:image` missing — weak social sharing (no preview image in WhatsApp/Facebook shares)

#### Performance Benchmarks

- ✅ Three.js lazy-loaded + skipped on mobile (LCP win)
- ✅ `HeroVideoBackground` and `ThreeCanvas` are `dynamic()` with `ssr: false`
- ✅ `reducedMotion="user"` prevents janky animations on low-end devices
- ⚠️ `useLandingStats()` fires on first render — if slow, it should use `suspense: false` + skeleton

#### Gap Analysis

| Gap | Details | Severity | Effort |
|-----|---------|----------|--------|
| `og:image` missing on root `/` | `page.tsx` OG block has no `images:` key; `/og` route exists but isn't referenced | 🔴 High | 30min |
| Testimonials fully static | `FALLBACK_TESTIMONIALS` array, `displayRating: '4.6'` hardcoded — no API call | 🟡 Medium | 1 day (needs model or curated endpoint) |
| No video walkthrough | No explainer video in hero or HowItWorks | 🟡 Medium | 2-3 days |
| No app store badges | Flutter mobile apps exist but no Google Play / App Store links on landing | 🟡 Medium | 2h |
| PricingSection = client only | Bailleur subscription plan cards absent from pricing section | 🟡 Medium | 1 day |
| FeaturesSection omits 3D tour & contracts | Platform has these features but they're not marketed | 🟢 Low | 2h |
| CTA section has only 1 action | Secondary CTA for landlords ("Publier gratuitement") absent | 🟢 Low | 1h |
| No "Pourquoi KeyHome" comparison | No table vs traditional agency | 🟢 Low | 1 day |
| Hero stats skeleton missing | `statsLoading` not used to show placeholder during fetch | 🟢 Low | 1h |

---

### MODULE: Admin Panel Resources

#### Current KeyHome Implementation

- **Stack**: Laravel 12 + Filament 4 + PostgreSQL + Spatie Media Library
- **Panels**: Admin (`/admin`) + Agency (`/agency`) + Owner (`/owner` — Next.js panel)
- **Admin resources (27)**: AcquisitionUser, ActivityLog, AdReport, AdType, Ad, Agency, BoostPack, City, NewsletterCampaign, NewsletterSubscriber, Payment, PendingAd, PointPackage, PointTransaction, PromoCode, PropertyAttributeCategory, PropertyAttribute, Quarter, Refund, Review, SiteVisit, SubscriptionPlan, Subscription, Survey, SurveyTemplate, UnlockedAd, User
- **Admin widgets (21)**: Revenue, RevenueAdvanced, RevenueProjection, RevenueChart, StatsOverview, UserChart, UserStatus, AdsByCity, AdsByType, CohortRetention, ConversionFunnel, ExportActions, GeographicHeatmap, InteractionStats, InteractionTrend, PendingAdsStats, QualityStats, RegistrationsByAcquisition, Retention, Activation, ActiveAlerts, AcquisitionStats
- **Admin pages (9)**: Dashboard, FailedJobsMonitor, ForcePasswordChange, ManagePermissions, ManageSettings, NotificationCenter, PaymentMethods, ScheduledReports, AdminLogin

#### Expert Best Practices Found

| Practice | Source | Priority |
|----------|--------|----------|
| MFA/TOTP for admin login | filamentphp.com/docs/4.x/users/mfa — built-in since v4 | HIGH |
| Resource policies (canView / canEdit per role) | filamentphp.com/docs security — resource-level authorization | HIGH |
| Chunked bulk exports (avoid memory OOM) | github filament #10675 — use queue + chunking | HIGH |
| Soft-delete restore across all resources | Best practice for audit compliance | MEDIUM |
| Infolist views for all resources | Read-only detail pages separate from edit forms | MEDIUM |
| Scoped tenancy for Agency panel | rohansakhale.com — multi-tenant SaaS Filament v4 | MEDIUM |

#### Security Findings

- ❌ **No MFA/TOTP** — `ForcePasswordChange.php` exists but zero MFA challenge. Filament v4 ships `MultiFactorAuthentication` out of the box; enabling it requires ~1h.
- ✅ `ManagePermissions` page + `AdminPermission` enum — role-based access control exists
- ✅ `ActivityLog` resource — audit trail is tracked
- ⚠️ `LoginHistory` model exists but **no Filament resource** — admins cannot inspect suspicious login patterns from the UI
- ⚠️ Bulk export of Users/Payments in a single page load could cause memory issues at scale (github issue #10675 pattern) — verify chunking is used in Exporters

#### Missing Resources — Full Gap List

| Model | Missing Resource | Business Impact | Severity |
|-------|-----------------|----------------|----------|
| `Invoice` | `InvoiceResource` | Team cannot view, download, or reconcile invoices from admin | 🔴 Critical |
| `LeaseContract` + `LeaseSignatureRequest` | `LeaseContractResource` | Cannot track or manage rental contracts in admin | 🔴 Critical |
| `LoginHistory` | `LoginHistoryResource` | No security audit trail visible in admin UI | 🔴 Critical |
| `TentativeReservation` | `TentativeReservationResource` | Cannot track property visits / reservations in admin | 🟡 Medium |
| `SearchAlert` + `SearchAlertMatch` | `SearchAlertResource` | Cannot audit search alerts or delivery failures in admin | 🟡 Medium |
| `Document` | `DocumentResource` | KYC/uploaded documents not manageable from admin UI | 🟡 Medium |
| `AdBoost` | `AdBoostResource` | Boost activation history not auditable in admin | 🟡 Medium |
| `Expense` | `ExpenseResource` | Landlord expense tracking invisible to admin | 🟢 Low |
| `AdInteraction` | `AdInteractionResource` | Per-ad interaction drill-down not accessible in admin | 🟢 Low |
| `TeamInvitation` | `TeamInvitationResource` | Agency team invitations not manageable in admin | 🟢 Low |
| `AnonymousSurveyAnswer/Response` | `AnonymousSurveyResponseResource` | Survey response data not viewable in admin | 🟢 Low |
| `NotificationPreference` | `NotificationPreferenceResource` | Cannot audit or reset notification preferences in admin | 🟢 Low |

#### Performance Gaps

- ✅ `AdResource::getEloquentQuery()` eagerly loads `user.agency, quarter, ad_type, media` — N+1 eliminated
- ✅ `UserResource` has export + import with `ExportBulkAction` using `ExportFormat`
- ⚠️ No chunking strategy confirmed in bulk exporters for large datasets — verify `UserExporter` / `PaymentExporter` use `WithChunkReading`
- ⚠️ `SiteVisitResource` and `AdInteraction` could grow to millions of rows — ensure table queries are scoped with date filters by default

#### Feature Completeness Gaps

```
☑ Export/Import: present on User, Ad, Payment
☑ Soft delete + restore: present on Ad, User
☑ BulkAction groups: present
☑ RelationManagers: Ads/Payments on User; Payments on Ad
☑ Infolist (ViewAction): present on User, Ad
☑ Search/filter/sort: present
☐ MFA login: NOT implemented
☐ InvoiceResource: NOT implemented
☐ LeaseContractResource: NOT implemented
☐ LoginHistoryResource: NOT implemented
☐ TentativeReservationResource: NOT implemented
☐ SearchAlertResource: NOT implemented
☐ DocumentResource: NOT implemented
```

---

## Gap Analysis Matrix

### Landing Page Checklist

```
✅ Cookie consent / RGPD
✅ JSON-LD Organization schema
✅ Canonical URL
✅ PWA install prompt
✅ Lazy-loaded heavy assets
✅ Mobile LCP optimization (Three.js skipped)
✅ reducedMotion respected
✅ Dark mode
✅ FAQ section
✅ Newsletter capture
✅ Dynamic pricing from API
✅ City/type SEO pages
❌ og:image on root page.tsx
❌ Real testimonial data (API-backed)
❌ Video walkthrough / explainer
❌ App store download badges
❌ WhatsApp/live chat widget
❌ Bailleur subscription plans in pricing
❌ Secondary landlord CTA in CTASection
❌ Competitor comparison section
```

### Admin Panel Security Checklist

```
✅ Role-based access (ManagePermissions + AdminPermission enum)
✅ ActivityLog audit trail
✅ ForcePasswordChange for new admins
✅ Soft delete on critical resources
❌ MFA/TOTP NOT enabled (Filament v4 built-in available)
❌ LoginHistory NOT surfaced in admin UI
❌ Bulk export chunking not confirmed
```

---

## Interoperability Report

*(Scoped to services used directly by landing page and admin panel.)*

| Service | Dimension | Status | Notes |
|---------|-----------|--------|-------|
| Filament v4 | MFA support | ⚠️ Available but not enabled | `filamentphp.com/docs/4.x/users/mfa` — TOTP built-in |
| Filament v4 | Partial re-render | ✅ | v4 ships component re-rendering optimization |
| Next.js (landing) | OG image route | ⚠️ `/og` route exists but not wired to root metadata | Add `images: [{ url: '/og' }]` to `page.tsx` |
| Framer Motion | reducedMotion | ✅ | `MotionConfig reducedMotion="user"` wraps all landing |
| TanStack Query | Landing stats | ✅ | `useLandingStats()` fetches dynamically |
| Clerk | Landing nav | ✅ | Owner panel URL detection from env |
| ConsentModeUpdater | RGPD | ✅ | In root layout, fires after CookieBanner consent |

---

## Priority Action Plan

| # | Action | Module | Severity | Effort | Owner |
|---|--------|--------|----------|--------|-------|
| 1 | Enable Filament v4 MFA/TOTP on admin panel | Admin | 🔴 Critical | 2h | backend |
| 2 | Add `og:image` to root `page.tsx` metadata (point to `/og` route) | Landing | 🔴 Critical | 30min | frontend |
| 3 | Create `InvoiceResource` (list, view, export) | Admin | 🔴 Critical | 4h | backend |
| 4 | Create `LeaseContractResource` with `LeaseSignatureRequest` RelationManager | Admin | 🔴 Critical | 1 day | backend |
| 5 | Create `LoginHistoryResource` (read-only, filtered per user) | Admin | 🔴 Critical | 2h | backend |
| 6 | Replace hardcoded testimonials with curated API endpoint or admin-managed list | Landing | 🟡 Medium | 1 day | full-stack |
| 7 | Add app store download badges (Google Play + App Store) to hero and CTA | Landing | 🟡 Medium | 2h | frontend |
| 8 | Add WhatsApp Business floating widget to landing page | Landing | 🟡 Medium | 3h | frontend |
| 9 | Add bailleur subscription plan cards to `PricingSection` | Landing | 🟡 Medium | 1 day | frontend |
| 10 | Create `TentativeReservationResource` | Admin | 🟡 Medium | 3h | backend |
| 11 | Create `SearchAlertResource` (list alerts + match history) | Admin | 🟡 Medium | 3h | backend |
| 12 | Create `DocumentResource` (KYC docs viewer) | Admin | 🟡 Medium | 4h | backend |
| 13 | Add 60-90s explainer video to HeroSection or HowItWorksSection | Landing | 🟡 Medium | 2-3 days | content+frontend |
| 14 | Create `AdBoostResource` (boost activation history) | Admin | 🟢 Low | 2h | backend |
| 15 | Add secondary landlord CTA to `CTASection` | Landing | 🟢 Low | 1h | frontend |
| 16 | Add "Pourquoi KeyHome vs agences" comparison block | Landing | 🟢 Low | 1 day | frontend |
| 17 | Hero stats loading skeleton (use `statsLoading` flag) | Landing | 🟢 Low | 1h | frontend |
| 18 | Add FeaturesSection card for 3D tour + contracts features | Landing | 🟢 Low | 2h | frontend |
| 19 | Create `ExpenseResource` | Admin | 🟢 Low | 2h | backend |
| 20 | Confirm bulk exporter chunking in `UserExporter` / `PaymentExporter` | Admin | 🟢 Low | 30min | backend |

---

## Sources & References

- https://shipixen.com/blog/10-essential-features-every-saas-landing-page-needs-in-2025
- https://kndigital.co/real-estate-landing-page-examples/
- https://www.grafit.agency/blog/saas-landing-page-best-practices
- https://www.saashero.net/design/landing-page-design-trust-signals/
- https://filamentphp.com/docs/4.x/users/multi-factor-authentication
- https://filamentphp.com/docs/5.x/advanced/security
- https://rohansakhale.com/building-scalable-multi-tenant-saas-solutions-with-filament-v4-a-complete-guide
- https://github.com/filamentphp/filament/issues/10675 (bulk export memory issue)
- https://edmondscommerce.co.uk/research/php/laravel-admin-panels/ (Filament v4 partial re-render)
- https://ownkey.com/blog/future-of-proptech-africa
