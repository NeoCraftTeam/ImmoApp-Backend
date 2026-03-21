# KeyHome Owner Panel — Enterprise-Level Gap Analysis & Roadmap

> **Audit date:** March 21, 2026
> **Perspectives:** Product Owner · Product Tester · SEO Agent
> **Stack:** Next.js 16 (React 19) · MUI v7 · TanStack Query · Clerk Auth · Recharts
> **Score:** 65/100 — Good UX foundation, critical gaps in tenant management, financial tools, and security

---

## Current State Summary

### What the owner panel has (solid foundation)

| Area | Details |
|------|---------|
| **Pages** | 17 routes: Dashboard, Ads CRUD, Viewings, Availability, Lease Contracts, Reviews, Payments, Subscriptions, Pro Services, Profile, Settings |
| **Components** | 16 owner components + 4 dashboard analytics components |
| **Analytics** | Impressions, views, favorites, shares, contacts, phone clicks, unlocks, conversion rate, sparklines, area charts |
| **Ad Management** | Create/edit/delete, 10-image upload, 360° tour with hotspots, MapPicker, AI description enhancement |
| **Viewing System** | Availability scheduling (daily/weekly/monthly recurrence), buffer times, reservation management |
| **Lease Contracts** | CRUD, PDF generation, AI-enhanced conditions |
| **Responsive** | Full dark/light/system theme, mobile bottom nav, FAB, sidebar collapse |
| **Notifications** | Notification bell with unread count, push notification opt-in, real-time polling (60s) |
| **API Coverage** | 32 backend endpoints working, `owner.service.ts` with 20+ methods |

### Dimension Scores

| Dimension | Score | Verdict |
|-----------|:-----:|---------|
| **UI/UX Quality** | 8/10 | Excellent layouts, dark mode, skeletons, responsive — best dimension |
| **Feature Completeness** | 5/10 | Core property management works, but no tenant mgmt, messaging, financials |
| **Security** | 4/10 | Critical: default role = ADMIN, ad ownership takeover, CSP allows unsafe-inline |
| **Testing** | 3/10 | 0 tests for `owner.service.ts` (20+ endpoints), 0 browser tests, 5 backend features untested |
| **API Completeness** | 6/10 | 68% implemented — boost, KYC, bulk ops, messaging all return 404 |
| **Competitive Parity** | 5/10 | Missing every feature that Zillow/Airbnb/TurboTenant owners expect |

---

## TIER 0 — SECURITY CRITICAL (Fix Before Production)

### 1. Default Role = ADMIN on Registration

> **File:** `AuthController.php` line ~270
> **Impact:** Any registration missing `role` field silently creates an ADMIN account.

- [ ] Change default role to `USER` or `AGENT` (never ADMIN)
- [ ] Add explicit validation: `'role' => 'required|in:USER,AGENT'`
- [ ] Write regression test: _"Registration without role field does NOT create admin"_

### 2. Ad Ownership Takeover via Mass Assignment

> **File:** `AdRequest.php` + `AdController.php`
> **Impact:** `user_id` accepted in PUT requests — Owner A can transfer ads to Owner B.

- [ ] Remove `user_id` from `$fillable` on Ad model OR exclude from AdRequest validation
- [ ] Always set `user_id` from `auth()->id()` in controller, never from request
- [ ] Write test: _"PUT /ads/{id} with user_id in body is ignored"_

### 3. CSP Allows `unsafe-inline` + `unsafe-eval`

> **File:** `next.config.ts` line ~61
> **Impact:** Completely defeats XSS protection. Any injected script runs.

- [ ] Remove `'unsafe-eval'` from `script-src` (use nonces instead)
- [ ] Remove `'unsafe-inline'` from `script-src` (use nonces or hashes)
- [ ] Keep `'unsafe-inline'` in `style-src` only if MUI needs it (it does for SSR)

### 4. Token Stored in localStorage

> **File:** `AuthProvider.tsx` line ~76-77
> **Impact:** Full user object + JWT accessible to any XSS attack.

- [ ] Migrate to `httpOnly` cookies or Clerk's built-in session management
- [ ] If localStorage is required, store only non-sensitive data (user name/role)
- [ ] Never store full JWT in localStorage when CSP isn't locked down

### 5. No Image Dimension/Size Validation on Upload

> **Impact:** Users can upload 100,000×100,000 PNG (decompression bomb) or malicious files.

- [ ] Add to AdRequest: `'images.*' => 'image|mimes:jpeg,png,webp|max:5120|dimensions:max_width=8000,max_height=8000'`
- [ ] Add to tour scene upload: same validation + max file size
- [ ] Add to avatar upload: `'avatar' => 'image|mimes:jpeg,png,webp|max:2048'`

---

## TIER 1 — PRODUCT BLOCKERS (Features That Return 404)

### 6. Ad Boost/Promotion System — Routes Missing

> **Frontend calls exist but return 404.** `AdBoostService` exists in backend. Direct revenue stream blocked.

- [ ] Create `POST /my/ads/{id}/boost` route → trigger Flutterwave payment → activate boost
- [ ] Create `GET /my/boost-plans` route → return tiers (Spotlight 24h, Premium 7d, Elite 30d)
- [ ] Create `GET /my/ads/{id}/boost-status` route → current boost info
- [ ] Wire to existing `AdBoostService.autoBoostIfEligible()` + `removeExpiredBoosts()`
- [ ] UI: Boost card in Pro Services page already has CTA — just needs working endpoint

### 7. Identity Verification (KYC) — No Implementation

> **Frontend calls `POST /my/verify-identity` → 404.** Enum `VerificationStatus` exists but no KYC provider integrated.

- [ ] Integrate **Smile Identity** (best for Africa), Jumio, or Onfido
- [ ] Accept multipart: `identity_document` (file) + `selfie` (file)
- [ ] Return `{ status: 'pending' }` → async verification → webhook updates status
- [ ] Display verified badge on owner profile and all their ads
- [ ] Verified owners rank higher in search results

### 8. In-App Messaging — Completely Absent

> **Gap:** Owners can only add notes to viewing reservations. No persistent buyer↔owner communication. This is the #1 feature gap for a real estate marketplace.

- [ ] **Backend:** Create `Conversation` + `Message` models (conversation linked to Ad)
- [ ] **Backend:** Create `POST /conversations`, `GET /conversations/{id}/messages`, `POST /conversations/{id}/messages`
- [ ] **Frontend:** Chat interface in owner panel with conversation list + message thread
- [ ] **Real-time:** Laravel Reverb or Pusher for instant message delivery
- [ ] **Notifications:** Push + email for new messages
- [ ] Minimum: text messages, image sharing, "is typing" indicator, read receipts

### 9. Draft Mode for Ads

> **Gap:** No way to save incomplete listings. Owners lose progress if they close the form.

- [ ] Add `draft` status to Ad model (or use existing status enum)
- [ ] Auto-save form state every 30 seconds to `localStorage` + backend draft endpoint
- [ ] Show drafts in ads list with "Continue editing" CTA
- [ ] Drafts don't appear in public search

### 10. Ad Duplication

> **Gap:** Owners with similar properties (e.g., apartments in same building) must re-enter everything.

- [ ] Add `POST /my/ads/{id}/duplicate` endpoint → clone ad with `(Copie)` suffix
- [ ] Clone images, attributes, amenities — but NOT tours or status
- [ ] New ad starts as draft

---

## TIER 2 — PRODUCT MATURITY (Competitive Parity)

### 11. Tenant Management System

> **Gap:** Tenants are just text fields on lease contracts (`tenant_name`, `tenant_phone`). No Tenant model, no tenant history, no cross-property tracking.

- [ ] Create `Tenant` model with: name, phone, email, ID number, lease history
- [ ] `GET /my/tenants` — list tenants across all properties
- [ ] Link tenants to lease contracts and payment history
- [ ] Tenant communication thread (via messaging system from #8)

### 12. Financial Dashboard & Expense Tracking

> **Gap:** No way to track income vs expenses per property. Can't calculate ROI.

- [ ] Create `Expense` model: ad_id, amount, category (maintenance, tax, insurance, utilities), date, receipt_file
- [ ] `POST /my/ads/{id}/expenses`, `GET /my/ads/{id}/profit-loss`
- [ ] Dashboard widgets: total revenue, total expenses, net income, per-property breakdown
- [ ] Monthly/yearly revenue trends chart

### 13. Document Management

> **Gap:** Only lease contracts are downloadable. No permits, insurance docs, property titles, receipts.

- [ ] `POST /ads/{id}/documents` — upload tagged documents (type: permit, insurance, title, receipt)
- [ ] `GET /ads/{id}/documents` — list with filters
- [ ] Leverage existing Spatie MediaLibrary for storage
- [ ] Organized by property with search/filter

### 14. Bulk Operations

> **Gap:** Owners with 10+ properties must make individual API calls for every action.

- [ ] `PUT /my/ads/bulk-update` — batch visibility toggle, status change
- [ ] `DELETE /my/ads/bulk-delete` — batch delete with confirmation
- [ ] `POST /my/ads/bulk-publish` — batch publish drafts
- [ ] Frontend: checkbox selection on ads list + bulk action toolbar

### 15. Notification Preferences

> **Gap:** Settings page only has theme + push toggle. No granular control.

- [ ] Per-event toggles: new viewing request, viewing confirmed, new review, payment received, ad expired
- [ ] Per-channel: push, email, SMS, WhatsApp (channels that exist)
- [ ] `PUT /my/notification-preferences` endpoint

### 16. Lease Renewal Reminders

> **Gap:** No automated reminders when leases are approaching expiration.

- [ ] Scheduled job: check leases expiring in 30/15/7 days → notify owner
- [ ] Dashboard widget: "3 leases expiring this month"
- [ ] Quick action: "Renew" → pre-filled contract with updated dates

---

## TIER 3 — UX EXCELLENCE (Polish & Delight)

### 17. Onboarding Tour for New Owners

> **Gap:** After registration, users land on empty dashboard with no guidance.

- [ ] First-login guided tour: "Welcome! Let's create your first listing"
- [ ] Profile completion percentage widget (avatar, phone, city, first ad)
- [ ] Empty state on dashboard: "No properties yet — Add your first listing" with prominent CTA
- [ ] Checklist: Profile complete → First ad → First viewing → First review

### 18. Analytics Enhancements

> **Gap:** No period comparison, no export, no market benchmarks.

- [ ] **Period comparison:** "Views up 15% vs last month" on stat cards
- [ ] **Export:** Download analytics as CSV/PDF
- [ ] **Market comparison:** "Your rent is 12% above average for this area" (rent estimator data exists)
- [ ] **Tour analytics:** How many users started/completed the 3D tour
- [ ] **Lead source attribution:** Which channel brought each contact click

### 19. Form UX Improvements

> **Gap:** No real-time validation, no drag-and-drop upload zone, no save progress.

- [ ] Real-time field validation (on blur, not just submit)
- [ ] Visual drag-and-drop zone for image uploads with progress bars per file
- [ ] File size validation pre-upload (currently only count validation)
- [ ] Auto-save to localStorage every 30s with "Draft saved" indicator
- [ ] Image reordering via drag-and-drop

### 20. Navigation Improvements

> **Gap:** No breadcrumbs, no back button in detail pages, sidebar doesn't collapse.

- [ ] Add breadcrumbs: "Dashboard > Ads > Edit > [Ad Title]"
- [ ] Add back button on edit/detail pages
- [ ] Collapsible sidebar on desktop (icon-only mode)
- [ ] Sticky table headers on desktop
- [ ] "Load more" infinite scroll option alongside pagination

### 21. Empty States & Error UX Consistency

> **Gap:** Inconsistent empty state designs across pages. Some pages show nothing when data is empty.

- [ ] Standardize empty state component: icon + message + CTA button
- [ ] Add empty state to ads list, viewings, availability, contracts, payments
- [ ] Auto-dismiss snackbar toasts after 5s
- [ ] Add sound/vibration notification for new viewing requests
- [ ] Error boundary per-section (not just app-level)

### 22. Account Security Features

> **Gap:** No 2FA, no login history, no active sessions, no data export.

- [ ] 2FA setup page (TOTP via authenticator app)
- [ ] Login history with device/location/IP
- [ ] "Sign out all devices" button
- [ ] `GET /my/data-export` — GDPR compliance
- [ ] "Deactivate my account" with confirmation flow

---

## TIER 4 — COMPETITIVE EDGE (Market Leadership)

### 23. Co-Landlord / Team Support

> **Gap:** Only one owner per account. Real estate agencies need team access.

- [ ] Invite team members (email invitation)
- [ ] Roles: Owner (full access), Manager (CRUD ads), Viewer (read-only)
- [ ] Activity audit log: "Who did what and when"

### 24. E-Signature Integration

> **Gap:** Lease contracts generate PDF but require manual signing.

- [ ] Integrate DocuSign, HelloSign, or Yousign (for French-speaking Africa)
- [ ] "Send for signature" button on lease contract detail
- [ ] Track signing status: sent → viewed → signed → completed

### 25. Automated Workflows

> **Gap:** All actions are manual. No automation for repetitive tasks.

- [ ] Auto-hide ads after N days without activity
- [ ] Auto-send "Thank you" message after viewing
- [ ] Auto-generate monthly performance report email
- [ ] Auto-renew boost if payment method on file

### 26. Multi-Channel Distribution

> **Gap:** Listings only on KeyHome. No syndication to other platforms.

- [ ] Auto-post to Facebook Marketplace
- [ ] Export listings as Airbnb-compatible format
- [ ] Share-to-WhatsApp with pre-formatted message + images

### 27. AI-Powered Features

> **Gap:** Only AI description enhancement exists. More AI opportunities:

- [ ] **Pricing AI:** "Your rent is 12% above market — consider lowering by 8%"
- [ ] **Photo quality AI:** Score photos, suggest which to replace
- [ ] **Auto-categorize:** Detect property type and amenities from photos
- [ ] **Smart scheduling:** Suggest optimal viewing times based on tenant behavior

---

## TESTING GAPS — Critical

### Backend: Untested Owner Features (0% coverage)

| Missing Test | Risk |
|-------------|------|
| `OwnerProfileTest.php` | Profile update, photo upload, settings |
| `LeaseContractTest.php` | PDF generation, AI enhancement, contract CRUD |
| `IdentityVerificationTest.php` | KYC flow (compliance risk) |
| `BoostPlanTest.php` | Purchase, activation, expiration |
| `SubscriptionLifecycleTest.php` | Billing, renewal, downgrade |

### Frontend: 20+ Untested Endpoints

`owner.service.ts` has **zero test coverage**:
- `getAnalytics()`, `getMyAds()`, `getLeaseContracts()`, `enhanceLeaseConditions()`, `downloadLeaseContract()`, `generateLeaseContract()`, `getMyReviews()`, `getViewingReservations()`, `confirmReservation()`, `cancelReservation()`, `boostAd()`, `verifyIdentity()`, `getBoostPlans()`, `getAvailabilities()`, `createAvailability()` — all 0 tests.

### Browser/E2E: None

Missing critical owner flows:
- Login → Dashboard analytics
- Create Ad → Upload 360° Tour → Publish
- Manage Availability → View Reservations → Confirm
- Generate Lease Contract → Download PDF
- Purchase Boost Plan
- Identity Verification

---

## Priority Implementation Order

| Priority | Item | Impact | Effort |
|:--------:|------|--------|:------:|
| **P0** | Fix default role = ADMIN on registration | **Privilege escalation** | **15 min** |
| **P0** | Fix ad ownership takeover (remove `user_id` from PUT) | **Data breach** | **30 min** |
| **P0** | Remove `unsafe-eval` from CSP | **XSS vulnerability** | **1 hour** |
| **P0** | Add image upload validation (size/dimensions/mime) | **Decompression bomb** | **1 hour** |
| **P0** | Migrate token from localStorage to httpOnly cookie | **Account takeover** | **4 hours** |
| **P1** | Create boost/promotion routes (backend exists!) | **Revenue blocked** | **3 hours** |
| **P1** | Implement KYC/identity verification | **Trust blocker** | **2 days** |
| **P1** | In-app messaging system | **#1 missing feature** | **2 weeks** |
| **P1** | Draft mode + auto-save for ads | **User retention** | **1 day** |
| **P1** | Ad duplication | **Owner productivity** | **3 hours** |
| **P2** | Tenant management system | **Pro landlord blocker** | **1 week** |
| **P2** | Financial dashboard + expense tracking | **ROI visibility** | **1 week** |
| **P2** | Bulk operations on ads | **Multi-property owners** | **2 days** |
| **P2** | Document management | **Professional workflows** | **3 days** |
| **P2** | Backend test coverage (5 missing test files) | **Regression risk** | **3 days** |
| **P2** | Frontend `owner.service.ts` tests | **API contract risk** | **2 days** |
| **P3** | Onboarding tour for new owners | **Activation rate** | **2 days** |
| **P3** | Analytics: period comparison + export | **Engagement** | **2 days** |
| **P3** | Notification preferences granularity | **Retention** | **1 day** |
| **P3** | Lease renewal reminders | **Churn prevention** | **1 day** |
| **P3** | Form UX: drag-drop upload + real-time validation | **Conversion** | **2 days** |
| **P3** | Breadcrumbs + collapsible sidebar | **Navigation UX** | **1 day** |
| **P4** | Co-landlord / team support | **Enterprise accounts** | **2 weeks** |
| **P4** | E-signature integration (Yousign) | **Lease automation** | **1 week** |
| **P4** | Automated workflows | **Operational efficiency** | **2 weeks** |
| **P4** | AI pricing + photo quality scoring | **Competitive edge** | **1 week** |

---

## Bottom Line

> The owner panel has **excellent UI/UX quality** — one of the best dimensions in the entire platform. Dark mode, responsive design, analytics dashboard with sparklines, skeleton loading, 360° tours — all polished.
>
> But it's missing the features that turn casual landlords into power users:
>
> | KeyHome Today | vs. | What Owners Expect |
> |--------------|-----|-------------------|
> | Post & track ads | → | Full property management lifecycle |
> | View payment history | → | Income/expense tracking + tax reports |
> | Viewing reservations | → | Tenant communication + messaging |
> | Lease PDF generation | → | E-signature + document vault |
> | Basic analytics | → | Market comparison + revenue forecasting |
>
> ### The 5 owner panel enterprise blockers:
>
> 1. **Security: default role = ADMIN + ad ownership takeover** — fix in 1 hour, prevents catastrophic damage
> 2. **Boost routes return 404** — backend service exists, just wire up routes → instant revenue
> 3. **No messaging** — owners can't talk to interested tenants except through viewing notes
> 4. **No tenant management** — tenants are just text fields on lease contracts
> 5. **Zero test coverage** on `owner.service.ts` (20+ API methods) and 5 backend features
>
> Fix the 5 P0 security items (most under 1 hour). Then wire up boost routes (3 hours) and ad drafts/duplication (1 day). That gets you from **65/100 to ~80/100**. Then build messaging + tenant management for the enterprise leap.
