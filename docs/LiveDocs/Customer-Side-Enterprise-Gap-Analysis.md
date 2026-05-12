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

| Issue | Components Affected | Severity | Status |
|-------|---------------------|----------|--------|
| No `<label>` elements on form inputs | Login, Register, Profile, Review, Search | CRITICAL | ✅ Done |
| Image carousel not keyboard-navigable | `AdCard.tsx`, `AdDetailClient.tsx` | CRITICAL | ✅ Done |
| Search autocomplete not keyboard-accessible | `HeroSearch.tsx`, `NaturalSearchBar.tsx` | CRITICAL | ✅ Done |
| Calendar date picker not keyboard-accessible | `ViewingBookingPanel.tsx` | CRITICAL | ✅ Done |
| Modal focus traps missing | `PaymentModal.tsx`, `CompleteOAuthProfileDialog.tsx`, + 3 others | CRITICAL | ✅ Done (MUI Dialog built-in) |
| No skip-to-content navigation link | `Navbar.tsx` | HIGH | ✅ Done |
| Validation errors not linked via `aria-describedby` | All form pages | HIGH | ✅ Done |
| No `aria-live` regions for dynamic content | Search results, payment status, booking confirmations | HIGH | ✅ Done |
| Missing `aria-label` on icon-only buttons | Various components | HIGH | ✅ Done |

**Effort**: 3–4 weeks | **Priority**: P0

### 2. 🟡 Frontend Component Tests — Initial Coverage Added
**Impact**: No confidence in UI behavior; regressions go undetected; blocks CI/CD gating.

| Gap | Details | Status |
|-----|---------|--------|
| Component tests | AdCard (9 tests), useSearchHistory (12 tests) | ✅ 21 tests passing |
| E2E coverage | 7 files but only basic page render checks | ⬜ Pending |
| Critical untested flows | Payment modal, booking calendar, review submission | ⬜ Pending |
| Service tests | 12 exist (good) — but no interaction testing | ⬜ Pending |

**Effort**: 4–6 weeks (initial 80% coverage) | **Priority**: P0 | **Status**: Started — 21 component/hook tests added (AdCard + useSearchHistory)

### 3. ⬜ Empty Messaging System — Folder Exists, Zero Implementation
**Impact**: Customers cannot communicate with landlords in-app; all inquiries happen via external WhatsApp/phone, losing engagement data.

- `src/app/(dashboard)/messages/` directory exists but is **completely empty**
- No messaging service, no components, no real-time infrastructure
- Booking inquiries, property questions, and negotiation all happen off-platform

**Effort**: 6–8 weeks (WebSocket + UI + API) | **Priority**: P0

---

## TIER 1 — Product Blockers (Pre-Growth)

### 4. ✅ Notifications UI Missing — API Exists, No Page
- Backend: `notifications.service.ts` with full CRUD (fetch, mark read, delete)
- Frontend: ✅ `/notifications` page implemented with full CRUD UI
- Push notifications: Service worker + VAPID registered, but no user-facing management
- ✅ Users can now view, mark as read, and delete notifications

**Effort**: 1–2 weeks | **Priority**: P1 | **Status**: Done

### 5. ✅ Search Alerts — CRUD API Built, No UI
- `searchAlerts.service.ts`: list, create, update, remove fully implemented
- `SearchAlertButton.tsx`: Creates alerts from search
- ✅ `/search-alerts` management page implemented — users can view, toggle, and delete alerts
- No email/push delivery on new matching ads (backend task)

**Effort**: 2–3 weeks (UI + email digest worker) | **Priority**: P1 | **Status**: UI Done, email digest pending

### 6. ✅ Hidden Power Tools — Built But Not Discoverable
Three valuable features have working APIs — now exposed in navigation:

| Tool | Service | Status |
|------|---------|--------|
| Rent Estimator | `estimator.service.ts` → `/rent-estimate` | ✅ Linked in nav (`/prix-marche`) |
| Price Heatmap | `estimator.service.ts` → `/price-heatmap` | ✅ Linked in nav (desktop + mobile + bottom nav) |
| KeyScore | `estimator.service.ts` → `/ads/{id}/keyscore` | ✅ Exposed in nav config |

**Effort**: 1–2 weeks | **Priority**: P1 | **Status**: Done

### 7. 🟡 Search Filters Gap — Missing Enterprise Filters
Currently available: city, type, bedrooms, **bathrooms**, price range, surface, parking (7 filters)

| Missing Filter | Backend Data Exists? | Competitor Standard | Status |
|----------------|---------------------|---------------------|--------|
| Bathrooms | ✅ Yes | Standard | ✅ Done |
| Furnished | ❌ No (no DB column) | Standard | ⬜ Blocked |
| Pet Friendly | ❌ No (no DB column) | Standard | ⬜ Blocked |
| WiFi / AC / Pool | ✅ Yes (amenities) | Standard | ⬜ Pending |
| Availability Date | ❌ No | Expected | ⬜ Pending |
| Verified Only | ✅ Yes | Expected | ✅ Done |
| Has 3D Tour | ✅ Yes | Differentiator | ✅ Done |
| Posted Date Range | ✅ Yes | Standard | ⬜ Pending |

**Effort**: 2–3 weeks | **Priority**: P1 | **Status**: Partially done (bathrooms, has_3d_tour, is_verified added)

### 8. ✅ Map Clustering & Advanced Map Features
- ✅ Native Mapbox GL clustering via GeoJSON source (cluster circles, count labels, click-to-zoom)
- ✅ Marker clustering for dense areas — replaces raw individual markers
- ⬜ No polygon/radius draw-to-search
- ✅ Heatmap toggle integrated into search page map (🔥 button toggles price heatmap layer, hides clusters)
- ✅ Map style toggle: Plan / Satellite / Sombre (dark) on search page

**Effort**: 2–3 weeks | **Priority**: P1 | **Status**: Done (clustering + style toggle + heatmap toggle)

---

## TIER 2 — Competitive Parity

### 9. ✅ Missing OG Images on 4 Page Types
- ✅ City pages (`/immobilier/[ville]`) — OG image added
- ✅ Property type pages (`/type-bien/[type]`) — OG image added
- ✅ Blog posts (`/blog/[slug]`) — OG image added (+ blog layout)
- ✅ Comparison pages (`/comparaison/[slug]`) — OG image added (+ index page)
- ✅ Homepage — OG image added

**Impact**: Improved CTR from social media shares. Static OG cover image on all page types.
**Effort**: 1 week | **Priority**: P2 | **Status**: Done

### 10. ⬜ Blog Content Depth — Only 3 Posts, Placeholder Content
- `blog/posts.ts`: 3 articles with body = "Contenu complet à venir" (placeholder)
- No blog categories page
- No author schema
- No featured images
- No internal linking strategy
- Competitors: 50–500+ SEO-optimized articles

**Effort**: Ongoing (content team) | **Priority**: P2

### 11. ✅ Sort Options — Full Set Implemented
Currently: Pertinence, Newest, Price ↑, Price ↓, Surface ↑, Surface ↓, Mieux notés, Populaires, Distance

| Sort | Impact | Status |
|------|--------|--------|
| Relevance (boost_score) | Text search returns random order | ✅ Done ("Pertinence") |
| Surface descending | Can't sort largest first | ✅ Done |
| Rating (reviews_avg_rating) | Find highest-rated properties | ✅ Done ("Mieux notés") |
| Distance (_geoPoint) | Sort by proximity with browser geolocation | ✅ Done ("Distance") |
| Popularity (views_count) | Find trending listings | ✅ Done ("Populaires") |

**Effort**: 1–2 weeks | **Priority**: P2 | **Status**: Done — backend (MeiliSearch sortable attrs + controller) + frontend sort menu

### 12. ✅ No Faceted Navigation — Filter Counts Missing
- ✅ Facet counts now shown on type autocomplete options, bedroom chips, and parking toggle
- ✅ `/ads/facets` endpoint called and data displayed in "More Filters" drawer
- Users can see counts before applying filters

**Effort**: 1–2 weeks | **Priority**: P2 | **Status**: Done

### 13. 🟡 hreflang & Multi-Language Preparation
- Only French (`fr_FR`) supported
- ✅ `x-default` hreflang tag added to root layout
- ✅ `fr-FR` hreflang tag present
- ⬜ No English content preparation
- ⬜ Blocks expansion to Anglophone African markets (Ghana, Nigeria)

**Effort**: 2–4 weeks (infrastructure) | **Priority**: P2 | **Status**: Partially done (hreflang tags added)

### 14. ✅ Missing BreadcrumbList Schema on Category Pages
- ✅ Implemented on blog posts
- ✅ Added to city pages, type pages, comparison pages
- Impacts Google rich snippet eligibility

**Effort**: 2–3 days | **Priority**: P2 | **Status**: Done

### 15. ✅ Homepage Canonical URL — Relative Instead of Absolute
```tsx
// Fixed:
alternates: { canonical: 'https://keyhome.app/' }  // ✅ Absolute
```

**Effort**: 5 minutes | **Priority**: P2 | **Status**: Done

---

## TIER 3 — Growth Enablers

### 16. ⬜ No Referral System
- No referral codes, tracking, or reward mechanism
- Missing viral growth loop — critical for African markets where word-of-mouth dominates
- Opportunity: Credits reward for successful referrals

**Effort**: 3–4 weeks | **Priority**: P3

### 17. ⬜ No Gamification / Engagement Loops
- Point balance displayed but no progression system
- No badges (Top Reviewer, First Booking, 10 Favorites)
- No daily login streaks
- No leaderboards
- Missing habit-forming engagement mechanics

**Effort**: 4–6 weeks | **Priority**: P3

### 18. ⬜ No Neighborhood Profiles
- Competitors (Zillow, SeLoger): Walk score, schools, crime stats, area amenities
- KeyHome: Zero neighborhood data
- Quarter-level data exists in backend but only for ad filtering
- No `/immobilier/douala/bastos` discovery pages

**Effort**: 4–6 weeks | **Priority**: P3

### 19. ✅ Agent/Landlord Public Profiles
- ✅ `GET /api/v1/agencies/{agency}` now public (policy updated) — returns agency details + active listings
- ✅ `AgencyResource` enriched with owner info, users_count
- ✅ Frontend page `/agences/[id]` with agency header, owner info, member chips, and paginated ad grid
- ✅ Frontend service `agency.service.ts` created
- ⬜ Follow agent feature — pending
- ⬜ Agent verification badges — pending

**Effort**: 3–4 weeks | **Priority**: P3 | **Status**: Core done — public profile page with listings

### 20. ✅ Recently Viewed — Local Only, No Backend Sync
- ✅ `useRecentlyViewed.ts`: localStorage + backend sync for authenticated users
- ✅ New `GET /api/v1/my/recently-viewed` endpoint added
- ✅ Merges backend (priority) + local items, deduped, max 10
- Authenticated users now have server-synced history

**Effort**: 1 week | **Priority**: P3 | **Status**: Done

### 21. ✅ Animations Don't Respect `prefers-reduced-motion`
- ✅ `PageLoader.tsx`, `AppLoader.tsx`, `PaymentSuccessScreen.tsx`, `PWAInstallPrompt.tsx` updated
- ✅ Framer Motion animations now respect `prefers-reduced-motion`
- Accessibility requirement (WCAG 2.3.3) satisfied

**Effort**: 3–4 hours | **Priority**: P3 | **Status**: Done

---

## TIER 4 — Differentiation

### 22. ⬜ No Video Tours
- Competitors support video walkthroughs alongside photos
- KeyHome has 360° tours (strong differentiator) but no standard video support

**Effort**: 2–3 weeks | **Priority**: P4

### 23. ⬜ No Mortgage/Rent Calculator
- Competitors: Built-in mortgage calculators with local bank rates
- Opportunity for African market: Customized FCFA calculators with local conditions

**Effort**: 2–3 weeks | **Priority**: P4

### 24. ✅ Export/Print Property Listings
- ✅ "Imprimer" button added to ad detail page (mobile + desktop) using `window.print()`
- ✅ Comprehensive `@media print` stylesheet in `globals.css` (hides nav/footer/dialogs, clean typography, A4 margins)
- ✅ Comparison tables print-friendly (visible overflow, bordered cells)
- ⬜ Server-side PDF generation (DomPDF) — pending
- ⬜ Email-to-friend feature — pending

**Effort**: 1–2 weeks | **Priority**: P4 | **Status**: Client-side print done

### 25. ⬜ No Multi-Channel Notifications
- Only web push + in-app (no UI)
- Missing: SMS, WhatsApp, email digest
- Critical alerts (booking confirmation, payment receipt) only reach users if they have push enabled

**Effort**: 3–4 weeks | **Priority**: P4

### 26. ✅ Hardcoded Sitemap Slugs
```ts
// Fixed — now dynamic:
const blogSlugs = BLOG_POSTS.map(p => p.slug);
const comparisonSlugs = Object.keys(COMPARISONS);
```

**Effort**: 30 minutes | **Priority**: P4 | **Status**: Done

### 27. ✅ Missing LocalBusiness Schema on City Pages
- ✅ City pages (`/immobilier/[ville]`) now include `LocalBusiness` JSON-LD
- Opportunity for Google local pack inclusion

**Effort**: 2–3 days | **Priority**: P4 | **Status**: Done (was already present)

---

## Competitor Feature Comparison

| Feature | Zillow | SeLoger | Rightmove | **KeyHome** | Changed? |
|---------|--------|---------|-----------|-------------|----------|
| Full-text search ranking | ✅ BM25 | ✅ Semantic | ✅ Relevance | ✅ Boost score sort | ✅ Was ❌ |
| Search suggestions/history | ✅ | ✅ | ✅ | ✅ localStorage history | ✅ Was ❌ |
| Saved search email alerts | ✅ | ✅ | ✅ | 🟡 UI done, email pending | ✅ Was API only |
| Map clustering | ✅ | ✅ | ✅ | ✅ GeoJSON clustering | ✅ Was ❌ |
| Polygon/radius search | ✅ | ✅ | ✅ | ❌ | |
| Neighborhood profiles | ✅ Walk score | ✅ Quartier | ✅ Area stats | ❌ | |
| Mortgage calculator | ✅ | ❌ | ✅ | ❌ | |
| 360° Virtual tours | 🟡 Some | ✅ Growing | ✅ | ✅ **Strong** | |
| Video tours | ✅ | ✅ | ✅ | ❌ | |
| In-app messaging | ✅ | ✅ | ✅ | ❌ Empty | |
| Agent profiles | ✅ | ✅ | ✅ | ❌ | |
| Amenity filters | ✅ Multiple | ✅ | ✅ | 🟡 Bathrooms + 3D tour + verified | ✅ Improved |
| Faceted nav (filter counts) | ✅ | ✅ | ✅ | ✅ Type, bedrooms, parking | ✅ Was ❌ |
| OG images / social sharing | ✅ | ✅ | ✅ | ✅ All page types | ✅ Was ❌ |
| Notifications center | ✅ | ✅ | ✅ | ✅ Full CRUD UI | ✅ Was ❌ |
| Recently viewed (cross-device) | ✅ | ✅ | ✅ | ✅ Backend sync | ✅ Was ❌ |
| Breadcrumb rich snippets | ✅ | ✅ | ✅ | ✅ All category pages | ✅ Was ❌ |
| Skip-to-content / a11y basics | ✅ | ✅ | ✅ | ✅ 9/9 WCAG items done | ✅ Was 5/9 |
| Multi-language | ✅ 40+ | ✅ Regional | ✅ | 🟡 hreflang tags ready | ✅ Was ❌ |
| Mobile app (native) | ✅ iOS/Android | ✅ | ✅ | ❌ PWA only | |
| Accessibility (WCAG AA) | ✅ | ✅ | ✅ | 🟡 ~75/100 (est.) | ✅ Was ~55 |
| `prefers-reduced-motion` | ✅ | ✅ | ✅ | ✅ All animations | ✅ Was ❌ |

**KeyHome Differentiators**: 360° tours, AI natural language search, credit-based contact unlock model, market price estimator (now exposed in nav), GeoJSON map clustering, faceted search with counts

---

## Priority Implementation Matrix

| # | Item | Priority | Effort | Impact | Status |
|---|------|----------|--------|--------|--------|
| 1 | WCAG accessibility fixes (forms, keyboard, focus) | P0 | 3–4 weeks | Legal + UX | ✅ All 9 items done |
| 2 | Frontend component test suite | P0 | 4–6 weeks | Quality | ⬜ Not started |
| 3 | In-app messaging system | P0 | 6–8 weeks | Conversion | ⬜ Not started |
| 4 | Notifications center UI | P1 | 1–2 weeks | Retention | ✅ Done |
| 5 | Search alerts management UI + email delivery | P1 | 2–3 weeks | Re-engagement | 🟡 UI done, email pending |
| 6 | Expose hidden tools (estimator, heatmap, KeyScore) | P1 | 1–2 weeks | Perceived value | ✅ Done |
| 7 | Extended search filters (bathrooms, furnished, amenities) | P1 | 2–3 weeks | Discovery | 🟡 Partial (bathrooms, 3D tour, verified) |
| 8 | Map clustering + advanced map features | P1 | 2–3 weeks | UX at scale | 🟡 Mostly done (clustering + style toggle) |
| 9 | OG images for all page types | P2 | 1 week | Social CTR | ✅ Done |
| 10 | Blog content expansion + CMS | P2 | Ongoing | SEO | ⬜ Not started |
| 11 | Additional sort options (rating, distance, relevance) | P2 | 1–2 weeks | Discovery | 🟡 Partial (2/5) |
| 12 | Faceted navigation with counts | P2 | 1–2 weeks | Search UX | ✅ Done |
| 13 | Multi-language infrastructure (hreflang) | P2 | 2–4 weeks | Market expansion | 🟡 Partial (tags only) |
| 14 | BreadcrumbList schema on category pages | P2 | 2–3 days | SEO | ✅ Done |
| 15 | Fix homepage canonical URL | P2 | 5 minutes | SEO | ✅ Done |
| 16 | Referral system with credit rewards | P3 | 3–4 weeks | Growth | ⬜ Not started |
| 17 | Gamification (badges, streaks, leaderboards) | P3 | 4–6 weeks | Engagement | ⬜ Not started |
| 18 | Neighborhood profile pages | P3 | 4–6 weeks | Discovery | ⬜ Not started |
| 19 | Agent/landlord public profiles | P3 | 3–4 weeks | Trust | ⬜ Not started |
| 20 | Backend sync for recently viewed | P3 | 1 week | Cross-device | ✅ Done |
| 21 | `prefers-reduced-motion` respect | P3 | 3–4 hours | Accessibility | ✅ Done |
| 22 | Video tour support | P4 | 2–3 weeks | Content | ⬜ Not started |
| 23 | Mortgage/rent calculator | P4 | 2–3 weeks | Engagement | ⬜ Not started |
| 24 | Export/print listings | P4 | 1–2 weeks | Utility | ⬜ Not started |
| 25 | Multi-channel notifications (SMS, WhatsApp) | P4 | 3–4 weeks | Reach | ⬜ Not started |
| 26 | Dynamic sitemap slug generation | P4 | 30 minutes | SEO | ✅ Done |
| 27 | LocalBusiness schema on city pages | P4 | 2–3 days | SEO | ✅ Done |

---

## Testing Gap Summary

| Layer | Current | Target | Gap |
|-------|---------|--------|-----|
| Backend API tests | ✅ Excellent (70+ tests) | Maintain | — |
| Frontend service tests | ✅ 12 tests | 25+ | 13 missing |
| Frontend component tests | ❌ 0 tests | 40+ | 40 missing |
| E2E tests | ⚠️ 7 files (shallow) | 15+ deep flows | 8+ missing |
| Accessibility audit | ~75/100 | 85+/100 | ~10 points |
| Visual regression | ❌ None | Key pages | Full gap |

**Priority test targets**: PaymentModal, ViewingBookingPanel, ReviewForm, AdDetailClient, SearchPage, AuthPages

---

## Quick Wins (< 1 Day Each)

1. ✅ Fix homepage canonical URL (5 minutes)
2. ✅ Dynamic sitemap slug generation (30 minutes)
3. ✅ Add `prefers-reduced-motion` checks to animations (3–4 hours)
4. ✅ Add BreadcrumbList to city/type/comparison pages (2–3 hours)
5. ✅ Add `aria-label` to all icon-only buttons (2–3 hours)
6. ✅ Link form errors with `aria-describedby` (3–4 hours)
7. ✅ Add skip-to-content navigation link (1 hour)

**All 7 quick wins completed.**

---

*Cross-reference: [Admin/Backend Gap Analysis](Enterprise-level%20Gap%20Analysis.md) (72/100) · [Owner Panel Gap Analysis](Owner-Panel-Enterprise-Gap-Analysis.md) (65/100)*
