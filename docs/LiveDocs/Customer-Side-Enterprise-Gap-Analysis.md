# Customer-Facing Side — Enterprise-Level Gap Analysis

> **Date**: 2025-01-19
> **Scope**: Next.js customer frontend (`keyhome-frontend-next/`) — public pages, authenticated dashboard, search, payments, engagement
> **Audited by**: Product Owner · Product Tester · SEO Agent (6 parallel audits)

---

## Executive Summary

**Overall Score: 58 / 100**

KeyHome's customer-facing frontend has excellent visual design, solid dark mode support, and a well-architected feature set covering search, payments, viewings, and 360° tours. However, it suffers from **critical accessibility failures** (WCAG Level A non-compliant), **zero frontend component tests**, several fully-built API features with **no UI exposure**, and an **empty messaging system**. These gaps block enterprise readiness and limit conversion potential.

---

## Dimension Scores

| Dimension | Score | Assessment |
|-----------|-------|------------|
| UI/UX Quality | 7 / 10 | Polished visuals, dark mode, responsive — but WCAG Level A failures in keyboard nav, focus traps, form labels |
| Search & Discovery | 6 / 10 | Solid map+list search with AI parsing — missing faceted nav, clustering, amenity filters, neighborhood profiles |
| Feature Completeness | 6 / 10 | Core flows complete (auth, payments, bookings, reviews) — messaging empty, notifications/alerts UI missing |
| SEO & Performance | 7 / 10 | Strong metadata + JSON-LD + ISR — missing OG images on 4 page types, hreflang, blog depth |
| Testing & Quality | 3 / 10 | Zero frontend component tests, 7 shallow E2E files, accessibility score 28/100 |
| Engagement & Retention | 4 / 10 | No messaging, no gamification, no referral, hidden tools (estimator, heatmap, KeyScore) |

---

## TIER 0 — Critical Blockers (Fix Before Launch)

### 1. WCAG Level A Non-Compliance — Accessibility Score 28/100
**Impact**: Legal liability, excludes users with disabilities, fails enterprise procurement requirements.

| Issue | Components Affected | Severity |
|-------|---------------------|----------|
| No `<label>` elements on form inputs | Login, Register, Profile, Review, Search | CRITICAL |
| Image carousel not keyboard-navigable | `AdCard.tsx`, `AdDetailClient.tsx` | CRITICAL |
| Search autocomplete not keyboard-accessible | `HeroSearch.tsx`, `NaturalSearchBar.tsx` | CRITICAL |
| Calendar date picker not keyboard-accessible | `ViewingBookingPanel.tsx` | CRITICAL |
| Modal focus traps missing | `PaymentModal.tsx`, `CompleteOAuthProfileDialog.tsx`, + 3 others | CRITICAL |
| No skip-to-content navigation link | `Navbar.tsx` | HIGH |
| Validation errors not linked via `aria-describedby` | All form pages | HIGH |
| No `aria-live` regions for dynamic content | Search results, payment status, booking confirmations | HIGH |

**Effort**: 3–4 weeks | **Priority**: P0

### 2. Zero Frontend Component Tests
**Impact**: No confidence in UI behavior; regressions go undetected; blocks CI/CD gating.

| Gap | Details |
|-----|---------|
| Component tests | 0 tests for 40+ components |
| E2E coverage | 7 files but only basic page render checks |
| Critical untested flows | Payment modal, booking calendar, review submission, search filters, image carousel |
| Service tests | 12 exist (good) — but no interaction testing |

**Effort**: 4–6 weeks (initial 80% coverage) | **Priority**: P0

### 3. Empty Messaging System — Folder Exists, Zero Implementation
**Impact**: Customers cannot communicate with landlords in-app; all inquiries happen via external WhatsApp/phone, losing engagement data.

- `src/app/(dashboard)/messages/` directory exists but is **completely empty**
- No messaging service, no components, no real-time infrastructure
- Booking inquiries, property questions, and negotiation all happen off-platform

**Effort**: 6–8 weeks (WebSocket + UI + API) | **Priority**: P0

---

## TIER 1 — Product Blockers (Pre-Growth)

### 4. Notifications UI Missing — API Exists, No Page
- Backend: `notifications.service.ts` with full CRUD (fetch, mark read, delete)
- Frontend: **Zero UI** — no `/dashboard/notifications` page
- Push notifications: Service worker + VAPID registered, but no user-facing management
- Users have no way to view historical notifications

**Effort**: 1–2 weeks | **Priority**: P1

### 5. Search Alerts — CRUD API Built, No UI
- `searchAlerts.service.ts`: list, create, update, remove fully implemented
- `SearchAlertButton.tsx`: Creates alerts from search — but no management page
- Users can create alerts but cannot view, edit, or delete them
- No email/push delivery on new matching ads

**Effort**: 2–3 weeks (UI + email digest worker) | **Priority**: P1

### 6. Hidden Power Tools — Built But Not Discoverable
Three valuable features have working APIs but no exposed UI:

| Tool | Service | Status |
|------|---------|--------|
| Rent Estimator | `estimator.service.ts` → `/rent-estimate` | API works, `RentEstimatorWidget.tsx` exists, not linked in nav |
| Price Heatmap | `estimator.service.ts` → `/price-heatmap` | `PriceHeatmapLayer.tsx` built but hidden from search UI |
| KeyScore | `estimator.service.ts` → `/ads/{id}/keyscore` | `KeyScoreBadge.tsx` exists, inconsistently shown |

**Effort**: 1–2 weeks | **Priority**: P1

### 7. Search Filters Gap — Missing Enterprise Filters
Currently available: city, type, bedrooms, price range, surface, parking (6 filters)

| Missing Filter | Backend Data Exists? | Competitor Standard |
|----------------|---------------------|---------------------|
| Bathrooms | ✅ Yes | Standard |
| Furnished | ✅ Yes | Standard |
| Pet Friendly | ✅ Yes | Standard |
| WiFi / AC / Pool | ✅ Yes (amenities) | Standard |
| Availability Date | ❌ No | Expected |
| Verified Only | ❌ No | Expected |
| Has 3D Tour | ✅ Yes | Differentiator |
| Posted Date Range | ✅ Yes | Standard |

**Effort**: 2–3 weeks | **Priority**: P1

### 8. Map Clustering & Advanced Map Features
- 200 raw markers render simultaneously — **cluttered at scale**
- No marker clustering for dense areas
- No polygon/radius draw-to-search
- No heatmap toggle (component built but hidden)
- No night/satellite style toggle

**Effort**: 2–3 weeks | **Priority**: P1

---

## TIER 2 — Competitive Parity

### 9. Missing OG Images on 4 Page Types
- ❌ City pages (`/immobilier/[ville]`) — no OG image
- ❌ Property type pages (`/type-bien/[type]`) — no OG image
- ❌ Blog posts (`/blog/[slug]`) — no featured image field at all
- ❌ Comparison pages (`/comparaison/[slug]`) — no OG image

**Impact**: Reduced CTR from social media shares. Only root OG image used everywhere.
**Effort**: 1 week | **Priority**: P2

### 10. Blog Content Depth — Only 3 Posts, Placeholder Content
- `blog/posts.ts`: 3 articles with body = "Contenu complet à venir" (placeholder)
- No blog categories page
- No author schema
- No featured images
- No internal linking strategy
- Competitors: 50–500+ SEO-optimized articles

**Effort**: Ongoing (content team) | **Priority**: P2

### 11. Missing Sort Options
Currently: Newest, Price ↑, Price ↓, Surface ↑

| Missing Sort | Impact |
|-------------|--------|
| Relevance (BM25) | Text search returns random order |
| Rating | Can't find highest-rated properties |
| Distance from user | Can't sort by proximity |
| Popularity (views) | Can't find trending listings |

**Effort**: 1–2 weeks | **Priority**: P2

### 12. No Faceted Navigation — Filter Counts Missing
- Users apply filters blindly — no count showing "Apartments (47)"
- Must refetch after every filter change to discover empty results
- No `/ads/facets` endpoint called (exists in service but unused)

**Effort**: 1–2 weeks | **Priority**: P2

### 13. hreflang & Multi-Language Preparation
- Only French (`fr_FR`) supported
- No `x-default` hreflang tag
- No English content preparation
- Blocks expansion to Anglophone African markets (Ghana, Nigeria)

**Effort**: 2–4 weeks (infrastructure) | **Priority**: P2

### 14. Missing BreadcrumbList Schema on Category Pages
- ✅ Implemented on blog posts
- ❌ Missing on city pages, type pages, comparison pages
- Impacts Google rich snippet eligibility

**Effort**: 2–3 days | **Priority**: P2

### 15. Homepage Canonical URL — Relative Instead of Absolute
```tsx
// Current (page.tsx line 23):
alternates: { canonical: '/' }         // ❌ Relative
// Should be:
alternates: { canonical: 'https://keyhome.app/' }  // ✅ Absolute
```

**Effort**: 5 minutes | **Priority**: P2

---

## TIER 3 — Growth Enablers

### 16. No Referral System
- No referral codes, tracking, or reward mechanism
- Missing viral growth loop — critical for African markets where word-of-mouth dominates
- Opportunity: Credits reward for successful referrals

**Effort**: 3–4 weeks | **Priority**: P3

### 17. No Gamification / Engagement Loops
- Point balance displayed but no progression system
- No badges (Top Reviewer, First Booking, 10 Favorites)
- No daily login streaks
- No leaderboards
- Missing habit-forming engagement mechanics

**Effort**: 4–6 weeks | **Priority**: P3

### 18. No Neighborhood Profiles
- Competitors (Zillow, SeLoger): Walk score, schools, crime stats, area amenities
- KeyHome: Zero neighborhood data
- Quarter-level data exists in backend but only for ad filtering
- No `/immobilier/douala/bastos` discovery pages

**Effort**: 4–6 weeks | **Priority**: P3

### 19. No Agent/Landlord Public Profiles
- Can't follow agents or view their complete listing portfolio
- No "See all X listings by this landlord" page
- No agent verification badges
- Missing trust-building for repeat customer↔agent relationships

**Effort**: 3–4 weeks | **Priority**: P3

### 20. Recently Viewed — Local Only, No Backend Sync
- `useRecentlyViewed.ts`: localStorage with max 10 items
- Lost on browser clear, not synced across devices
- Authenticated users should have server-synced history

**Effort**: 1 week | **Priority**: P3

### 21. Animations Don't Respect `prefers-reduced-motion`
- `PageLoader.tsx`, `AppLoader.tsx`, `PaymentSuccessScreen.tsx`, `PWAInstallPrompt.tsx`
- All Framer Motion animations ignore user preference
- Accessibility requirement (WCAG 2.3.3)

**Effort**: 3–4 hours | **Priority**: P3

---

## TIER 4 — Differentiation

### 22. No Video Tours
- Competitors support video walkthroughs alongside photos
- KeyHome has 360° tours (strong differentiator) but no standard video support

**Effort**: 2–3 weeks | **Priority**: P4

### 23. No Mortgage/Rent Calculator
- Competitors: Built-in mortgage calculators with local bank rates
- Opportunity for African market: Customized FCFA calculators with local conditions

**Effort**: 2–3 weeks | **Priority**: P4

### 24. No Export/Print Property Listings
- Can't generate PDF summary of a property
- Can't print comparison tables
- No email-to-friend feature for individual listings

**Effort**: 1–2 weeks | **Priority**: P4

### 25. No Multi-Channel Notifications
- Only web push + in-app (no UI)
- Missing: SMS, WhatsApp, email digest
- Critical alerts (booking confirmation, payment receipt) only reach users if they have push enabled

**Effort**: 3–4 weeks | **Priority**: P4

### 26. Hardcoded Sitemap Slugs
```ts
// sitemap.ts — hardcoded instead of dynamic:
const blogSlugs = ['eviter-arnaques-immobilieres-cameroun', ...];
const comparisonSlugs = ['louer-vs-acheter', ...];
// Should pull from data:
const blogSlugs = BLOG_POSTS.map(p => p.slug);
```

**Effort**: 30 minutes | **Priority**: P4

### 27. Missing LocalBusiness Schema on City Pages
- City pages (`/immobilier/[ville]`) lack `LocalBusiness` JSON-LD
- Missing opportunity for Google local pack inclusion

**Effort**: 2–3 days | **Priority**: P4

---

## Competitor Feature Comparison

| Feature | Zillow | SeLoger | Rightmove | **KeyHome** |
|---------|--------|---------|-----------|-------------|
| Full-text search ranking | ✅ BM25 | ✅ Semantic | ✅ Relevance | ❌ Keyword match |
| Search suggestions/history | ✅ | ✅ | ✅ | ❌ |
| Saved search email alerts | ✅ | ✅ | ✅ | 🟡 API only |
| Map clustering | ✅ | ✅ | ✅ | ❌ Raw markers |
| Polygon/radius search | ✅ | ✅ | ✅ | ❌ |
| Neighborhood profiles | ✅ Walk score | ✅ Quartier | ✅ Area stats | ❌ |
| Mortgage calculator | ✅ | ❌ | ✅ | ❌ |
| 360° Virtual tours | 🟡 Some | ✅ Growing | ✅ | ✅ **Strong** |
| Video tours | ✅ | ✅ | ✅ | ❌ |
| In-app messaging | ✅ | ✅ | ✅ | ❌ Empty |
| Agent profiles | ✅ | ✅ | ✅ | ❌ |
| Amenity filters | ✅ Multiple | ✅ | ✅ | ❌ (data exists) |
| Multi-language | ✅ 40+ | ✅ Regional | ✅ | ❌ French only |
| Mobile app (native) | ✅ iOS/Android | ✅ | ✅ | ❌ PWA only |
| Accessibility (WCAG AA) | ✅ | ✅ | ✅ | ❌ Score 28/100 |

**KeyHome Differentiators**: 360° tours, AI natural language search, credit-based contact unlock model, market price estimator (hidden)

---

## Priority Implementation Matrix

| # | Item | Priority | Effort | Impact |
|---|------|----------|--------|--------|
| 1 | WCAG accessibility fixes (forms, keyboard, focus) | P0 | 3–4 weeks | Legal + UX |
| 2 | Frontend component test suite | P0 | 4–6 weeks | Quality |
| 3 | In-app messaging system | P0 | 6–8 weeks | Conversion |
| 4 | Notifications center UI | P1 | 1–2 weeks | Retention |
| 5 | Search alerts management UI + email delivery | P1 | 2–3 weeks | Re-engagement |
| 6 | Expose hidden tools (estimator, heatmap, KeyScore) | P1 | 1–2 weeks | Perceived value |
| 7 | Extended search filters (bathrooms, furnished, amenities) | P1 | 2–3 weeks | Discovery |
| 8 | Map clustering + advanced map features | P1 | 2–3 weeks | UX at scale |
| 9 | OG images for all page types | P2 | 1 week | Social CTR |
| 10 | Blog content expansion + CMS | P2 | Ongoing | SEO |
| 11 | Additional sort options (rating, distance, relevance) | P2 | 1–2 weeks | Discovery |
| 12 | Faceted navigation with counts | P2 | 1–2 weeks | Search UX |
| 13 | Multi-language infrastructure (hreflang) | P2 | 2–4 weeks | Market expansion |
| 14 | BreadcrumbList schema on category pages | P2 | 2–3 days | SEO |
| 15 | Fix homepage canonical URL | P2 | 5 minutes | SEO |
| 16 | Referral system with credit rewards | P3 | 3–4 weeks | Growth |
| 17 | Gamification (badges, streaks, leaderboards) | P3 | 4–6 weeks | Engagement |
| 18 | Neighborhood profile pages | P3 | 4–6 weeks | Discovery |
| 19 | Agent/landlord public profiles | P3 | 3–4 weeks | Trust |
| 20 | Backend sync for recently viewed | P3 | 1 week | Cross-device |
| 21 | `prefers-reduced-motion` respect | P3 | 3–4 hours | Accessibility |
| 22 | Video tour support | P4 | 2–3 weeks | Content |
| 23 | Mortgage/rent calculator | P4 | 2–3 weeks | Engagement |
| 24 | Export/print listings | P4 | 1–2 weeks | Utility |
| 25 | Multi-channel notifications (SMS, WhatsApp) | P4 | 3–4 weeks | Reach |
| 26 | Dynamic sitemap slug generation | P4 | 30 minutes | SEO |
| 27 | LocalBusiness schema on city pages | P4 | 2–3 days | SEO |

---

## Testing Gap Summary

| Layer | Current | Target | Gap |
|-------|---------|--------|-----|
| Backend API tests | ✅ Excellent (70+ tests) | Maintain | — |
| Frontend service tests | ✅ 12 tests | 25+ | 13 missing |
| Frontend component tests | ❌ 0 tests | 40+ | 40 missing |
| E2E tests | ⚠️ 7 files (shallow) | 15+ deep flows | 8+ missing |
| Accessibility audit | 28/100 | 85+/100 | 57 points |
| Visual regression | ❌ None | Key pages | Full gap |

**Priority test targets**: PaymentModal, ViewingBookingPanel, ReviewForm, AdDetailClient, SearchPage, AuthPages

---

## Quick Wins (< 1 Day Each)

1. Fix homepage canonical URL (5 minutes)
2. Dynamic sitemap slug generation (30 minutes)
3. Add `prefers-reduced-motion` checks to animations (3–4 hours)
4. Add BreadcrumbList to city/type/comparison pages (2–3 hours)
5. Add `aria-label` to all icon-only buttons (2–3 hours)
6. Link form errors with `aria-describedby` (3–4 hours)
7. Add skip-to-content navigation link (1 hour)

---

*Cross-reference: [Admin/Backend Gap Analysis](Enterprise-level%20Gap%20Analysis.md) (72/100) · [Owner Panel Gap Analysis](Owner-Panel-Enterprise-Gap-Analysis.md) (65/100)*
