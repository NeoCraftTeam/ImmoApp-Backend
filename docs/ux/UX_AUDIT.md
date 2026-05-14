# UX Audit Report — KeyHome

**Auditor**: Senior UX Designer (first-time review)  
**Date**: 28 février 2026  
**Platform**: Web (Next.js + MUI), responsive  
**Target users**: Locataires, acheteurs, propriétaires, agents immobiliers en Afrique francophone  
**Primary goal**: Trouver un logement et contacter le propriétaire

---

## 1. FIRST IMPRESSIONS (0-5 seconds)

### Issue #1 — Fake Search Bar in Hero

| | |
|---|---|
| **Severity** | **High** |
| **Location** | `src/components/landing/HeroSection.tsx` — hero search bar |
| **Problem** | The hero search bar looks functional but redirects to `/register` instead of actually searching. City chips also all go to `/register`. |
| **Impact** | Users expect to search immediately. Being redirected to signup feels deceptive, breaks trust, and increases bounce rate. |
| **Recommendation** | Either make the search bar functional (show results with a login gate on unlock), or clearly label it as a CTA: "Inscrivez-vous pour rechercher". |
| **Effort** | Medium |

### Issue #2 — Unsubstantiated "#1" Claim

| | |
|---|---|
| **Severity** | **Medium** |
| **Location** | `src/components/landing/HeroSection.tsx` — badge "Plateforme immobilière #1 en Afrique" |
| **Problem** | The "#1 in Africa" claim is displayed alongside stats showing only 2,000 listings and 5,000 users — these modest numbers contradict the claim. |
| **Impact** | Savvy users will notice the discrepancy, eroding trust. |
| **Recommendation** | Replace with a verifiable claim: "La plateforme immobilière qui grandit le plus vite en Afrique" or simply "Plateforme immobilière panafricaine". |
| **Effort** | Easy |

### Issue #3 — No Landlord/Agent Value Proposition

| | |
|---|---|
| **Severity** | **High** |
| **Location** | Landing page overall — hero, features, how-it-works |
| **Problem** | The entire landing page speaks to renters/buyers. Landlords and agents have no dedicated messaging, yet they are essential for content supply (the marketplace). |
| **Impact** | Half of the target audience (supply-side) sees no reason to sign up. |
| **Recommendation** | Add a dedicated "Pour les propriétaires et agents" section or a dual-audience hero with toggle ("Je cherche" / "Je publie"). |
| **Effort** | Medium |

### Issue #4 — Placeholder Social Links in Footer

| | |
|---|---|
| **Severity** | **Medium** |
| **Location** | `src/components/landing/LandingFooter.tsx` — social icons |
| **Problem** | Social icons are plain letters ("F", "IN", "TW", "WA") inside divs with no `href` — they're completely non-functional. Resource links also redirect to `/register`. |
| **Impact** | Looks unfinished and unprofessional. |
| **Recommendation** | Use real SVG icons with actual social URLs, or remove the section entirely until ready. |
| **Effort** | Easy |

### Issue #5 — Testimonials Lack Credibility

| | |
|---|---|
| **Severity** | **Medium** |
| **Location** | `src/components/landing/TestimonialsSection.tsx` |
| **Problem** | All 4 testimonials are 5 stars with avatar initials only (no photos). This pattern looks fabricated. |
| **Impact** | Reduces trust instead of building it. |
| **Recommendation** | Add real user photos (even stock if needed); include at least one 4-star review; add verification badges ("Utilisateur vérifié"). |
| **Effort** | Easy |

---

## 2. NAVIGATION & INFORMATION ARCHITECTURE

### Issue #6 — No Way to Browse Without Signup

| | |
|---|---|
| **Severity** | **Critical** |
| **Location** | `src/app/(dashboard)/layout.tsx` — auth guard |
| **Problem** | All content pages (`/home`, `/search`, `/nearby`, `/ads/*`) are behind authentication. Users must create an account before seeing a single listing. |
| **Impact** | Massive drop-off for curious visitors. Every major real estate platform allows browsing first. |
| **Recommendation** | Allow anonymous browsing of listings and search. Gate only contact reveal/unlock behind auth. This is the single highest-impact change possible. |
| **Effort** | Hard |

### Issue #7 — Footer Links Point to `/register`

| | |
|---|---|
| **Severity** | **Low** |
| **Location** | `src/components/landing/LandingFooter.tsx` — "Ressources" column |
| **Problem** | "Blog", "Questions fréquentes", "Guides", "Mon compte" all link to `/register`. There is no blog, no FAQ, no guides. |
| **Impact** | Broken expectations; users expect these pages to exist. |
| **Recommendation** | Remove non-existent links, or create minimal FAQ and guide pages. |
| **Effort** | Easy |

### Issue #8 — Dashboard Footer Has Placeholder Links

| | |
|---|---|
| **Severity** | **Low** |
| **Location** | `src/components/layout/Footer.tsx` — "À propos", "Comment ça marche", "Carrières" |
| **Problem** | These links point to `#` — they go nowhere. |
| **Impact** | Minor annoyance but looks unfinished. |
| **Recommendation** | Link "Comment ça marche" to landing page anchor, remove "Carrières" until ready. |
| **Effort** | Easy |

---

## 3. ONBOARDING

### Issue #9 — 3-Step Registration is Too Heavy

| | |
|---|---|
| **Severity** | **High** |
| **Location** | `src/app/(auth)/register/page.tsx` — 8 fields across 3 steps |
| **Problem** | Step 0: account type. Step 1: firstname, lastname, email, phone, city. Step 2: password, confirm, T&C. Then verify email. Then optional OTP. That's 5+ screens before the user sees any content. |
| **Impact** | High registration abandonment. Users in Africa on mobile data don't want to fill 8 fields before knowing if the platform has what they need. |
| **Recommendation** | Reduce to email + password only for initial signup. Collect phone/name/type progressively (when they try to unlock an ad). Or allow social login to skip almost everything. |
| **Effort** | Hard |

### Issue #10 — Silent Validation on Registration Step 1

| | |
|---|---|
| **Severity** | **Medium** |
| **Location** | `src/app/(auth)/register/page.tsx` — Step 1 |
| **Problem** | The "Suivant" button is disabled when fields are invalid, but there are no per-field error messages for firstname, lastname, or email. Users don't know which field is wrong. |
| **Impact** | Users stare at a disabled button with no guidance on how to proceed. |
| **Recommendation** | Add inline error messages on blur for each field ("Le prénom est requis", "Email invalide"). |
| **Effort** | Easy |

### Issue #11 — WelcomeOverlay is Undismissable

| | |
|---|---|
| **Severity** | **Medium** |
| **Location** | `src/components/ui/WelcomeOverlay.tsx` |
| **Problem** | After registration, a full-screen animation plays for 3.8 seconds with no skip button. Users are trapped. |
| **Impact** | Frustrating for experienced/impatient users; accessibility issue for screen readers. |
| **Recommendation** | Add a "Passer" link; add `prefers-reduced-motion` support; add `aria-live="polite"` announcement. |
| **Effort** | Easy |

---

## 4. CORE USER FLOWS

### Issue #12 — No Error States on Key Pages

| | |
|---|---|
| **Severity** | **Critical** |
| **Location** | `src/app/(dashboard)/home/page.tsx`, `AdDetailClient.tsx`, `search/page.tsx` |
| **Problem** | If API calls fail, the home feed shows nothing, ad detail shows infinite skeleton, and search shows nothing — no error message, no retry button. |
| **Impact** | Users on slow African mobile connections will frequently hit this. They'll think the app is broken with no way to recover. |
| **Recommendation** | Add error states with "Quelque chose s'est mal passé" + "Réessayer" button on every data-fetching page. React Query provides `isError` for this. |
| **Effort** | Easy |

### Issue #13 — No 404 Page

| | |
|---|---|
| **Severity** | **Medium** |
| **Location** | App-wide — no `not-found.tsx` |
| **Problem** | Invalid URLs show the default Next.js 404 — unstyled, in English, with no navigation back. |
| **Impact** | Dead-end for users who land on old/broken links. |
| **Recommendation** | Create a branded `not-found.tsx` with navigation, search bar, and suggested links. |
| **Effort** | Easy |

### Issue #14 — No `error.tsx` Boundary Files

| | |
|---|---|
| **Severity** | **Medium** |
| **Location** | App-wide — no `error.tsx` files |
| **Problem** | Only a top-level React ErrorBoundary exists. Segment-level errors crash the entire page instead of showing a localized error with recovery. |
| **Impact** | A single component error takes down the whole page. |
| **Recommendation** | Add `error.tsx` files in `(dashboard)/` and `(auth)/` route groups with branded error UI and retry. |
| **Effort** | Easy |

### Issue #15 — No Confirmation Before Spending Credits

| | |
|---|---|
| **Severity** | **High** |
| **Location** | `AdDetailClient.tsx` — unlock dialog |
| **Problem** | When the user has enough credits, a single click on "Déverrouiller" immediately spends credits. No confirmation step like "Vous allez dépenser X crédits. Confirmer ?" |
| **Impact** | Accidental unlocks waste paid credits. Users will feel cheated. |
| **Recommendation** | Add a 2-step confirmation: show cost clearly, require explicit "Confirmer" click. |
| **Effort** | Easy |

### Issue #16 — Inconsistent Success Feedback Patterns

| | |
|---|---|
| **Severity** | **Low** |
| **Location** | `src/app/(dashboard)/profile/page.tsx` |
| **Problem** | Profile edit uses `Alert`, avatar update uses `Snackbar`, password change uses `Alert`. Three different feedback mechanisms on the same page. |
| **Impact** | Inconsistent UX feels unpolished. Users may miss feedback in unexpected locations. |
| **Recommendation** | Standardize on `Snackbar` for all success/error feedback on the profile page. |
| **Effort** | Easy |

---

## 5. MOBILE EXPERIENCE

### Issue #17 — AdCard Image Carousel Invisible on Touch

| | |
|---|---|
| **Severity** | **High** |
| **Location** | `src/components/ads/AdCard.tsx` — carousel arrows |
| **Problem** | Carousel navigation arrows (28x28px) have `opacity: 0` and only appear on `:hover`. Touch devices have no hover — arrows are permanently invisible. No swipe support. |
| **Impact** | Mobile users can only see the first photo of each listing. Multi-photo browsing is completely broken on mobile. |
| **Recommendation** | Always show arrows on touch devices (via `@media (hover: none)`), add swipe gesture support, and increase arrow touch targets to 44px. |
| **Effort** | Medium |

### Issue #18 — OTP Inputs Overflow on Small Screens

| | |
|---|---|
| **Severity** | **Medium** |
| **Location** | `src/app/(auth)/verify-otp/page.tsx` |
| **Problem** | 6 OTP input boxes at 52px wide + gaps = ~372px total, inside a 400px max-width container with padding. On screens < 400px, this causes horizontal overflow. |
| **Impact** | Broken layout on smaller phones (e.g., iPhone SE at 375px). |
| **Recommendation** | Use responsive widths (`width: { xs: 42, sm: 52 }`) or use `flex: 1` with `maxWidth: 52`. |
| **Effort** | Easy |

### Issue #19 — Landing Footer Not Responsive

| | |
|---|---|
| **Severity** | **Medium** |
| **Location** | `src/components/landing/LandingFooter.tsx` |
| **Problem** | Uses `gridTemplateColumns: '2fr 1fr 1fr 1fr'` with inline styles — no mobile breakpoint. On small screens, 4 columns compress into unreadable columns. |
| **Impact** | Footer is broken on mobile. |
| **Recommendation** | Switch to a responsive grid: 1 column on mobile, 2 on tablet, 4 on desktop. |
| **Effort** | Easy |

### Issue #20 — CategoryPills `justify-content: center` Bug

| | |
|---|---|
| **Severity** | **Medium** |
| **Location** | `src/components/ui/CategoryPills.tsx` |
| **Problem** | The scroll container uses `justifyContent: 'center'` with `overflowX: 'auto'`. When content overflows horizontally, `justify-content: center` causes the leftmost items to be unreachable (scrolled past the start). |
| **Impact** | First category ("Tous") may be partially hidden on narrow screens. |
| **Recommendation** | Remove `justifyContent: 'center'` from the scroll container. Center via padding if needed. |
| **Effort** | Easy |

---

## 6. ACCESSIBILITY

### Issue #21 — Missing aria-labels on Icon-Only Buttons

| | |
|---|---|
| **Severity** | **High** |
| **Location** | Multiple: password toggle (login/register), carousel arrows (AdCard), copy buttons (AdDetailClient), lightbox zoom/nav |
| **Problem** | Icon-only buttons have no `aria-label`. Screen readers announce them as "button" with no context. |
| **Impact** | App is unusable for visually impaired users on these interactions. |
| **Recommendation** | Add `aria-label` to all icon-only buttons: "Afficher le mot de passe", "Photo suivante", "Copier", etc. |
| **Effort** | Easy |

### Issue #22 — No Keyboard Navigation in Lightbox

| | |
|---|---|
| **Severity** | **Medium** |
| **Location** | `AdDetailClient.tsx` — lightbox |
| **Problem** | The fullscreen photo viewer has no keyboard handlers — arrow keys don't navigate photos, Escape doesn't close. |
| **Impact** | Keyboard-only users and power users can't navigate the gallery. |
| **Recommendation** | Add `onKeyDown` handler: Left/Right arrows for nav, Escape to close, +/- for zoom. |
| **Effort** | Easy |

### Issue #23 — No `prefers-reduced-motion` Support

| | |
|---|---|
| **Severity** | **Medium** |
| **Location** | `WelcomeOverlay.tsx`, `WelcomeModal.tsx`, landing page animations |
| **Problem** | Animations play regardless of user's motion preferences. The hero has a particle canvas, the welcome overlay has confetti, and multiple elements use Framer Motion. |
| **Impact** | Users with vestibular disorders or motion sensitivity experience discomfort. |
| **Recommendation** | Wrap animations in `@media (prefers-reduced-motion: reduce)` or use Framer Motion's `useReducedMotion()` hook. |
| **Effort** | Easy |

### Issue #24 — `color: text.disabled` Used for Visible Content

| | |
|---|---|
| **Severity** | **Medium** |
| **Location** | `src/app/(dashboard)/profile/page.tsx` — "Non connecté" text on linked accounts |
| **Problem** | `text.disabled` in MUI defaults to very low opacity (~0.38), which fails WCAG AA contrast requirements. |
| **Impact** | Text is hard to read for users with any visual impairment. |
| **Recommendation** | Use `text.secondary` instead (typically 0.6 opacity, passes AA). |
| **Effort** | Easy |

### Issue #25 — Review Comment Uses Placeholder Instead of Label

| | |
|---|---|
| **Severity** | **Low** |
| **Location** | `src/components/reviews/ReviewForm.tsx` |
| **Problem** | The comment `TextField` uses `placeholder` but no `label` or `aria-label`. Placeholders disappear when typing and are not reliable labels for accessibility. |
| **Impact** | Screen readers may not announce the field's purpose. |
| **Recommendation** | Add `label="Votre commentaire"` or `aria-label="Votre commentaire"`. |
| **Effort** | Easy |

---

## 7. CONVERSION OPTIMIZATION

### Issue #26 — Double Paywall: Signup + Credits

| | |
|---|---|
| **Severity** | **Critical** |
| **Location** | Entire funnel: landing → register → verify → home → ad → unlock |
| **Problem** | Users must: (1) find the app, (2) create an account (8 fields, 3 steps), (3) verify email, (4) browse to find an ad, (5) buy credits with real money, (6) unlock the ad. That's 6 barriers before getting value. |
| **Impact** | Extremely high funnel drop-off. Most users will abandon before step 3. |
| **Recommendation** | Short-term: allow anonymous browsing (#6). Medium-term: give generous welcome credits (1-2 free unlocks). Long-term: consider freemium model where basic contacts are visible. |
| **Effort** | Hard |

### Issue #27 — No Social Proof at Decision Points

| | |
|---|---|
| **Severity** | **Medium** |
| **Location** | `AdDetailClient.tsx` — unlock dialog, `PurchaseCreditsModal.tsx` |
| **Problem** | When users are asked to spend credits or buy a package, there's no social proof: no "X users unlocked this", no trust badges. |
| **Impact** | Users hesitate at the payment moment without validation from other users. |
| **Recommendation** | Add "256 personnes ont déverrouillé cette annonce" or "Pack le plus choisi" type social proof at purchase moments. |
| **Effort** | Medium |

### Issue #28 — Credit Package Pricing Lacks Anchoring

| | |
|---|---|
| **Severity** | **Medium** |
| **Location** | `PurchaseCreditsModal.tsx` + `PackageCard.tsx` |
| **Problem** | Packages show price and credits but don't show per-unit savings. Users must do mental math to see savings. |
| **Impact** | Users default to the cheapest option, reducing ARPU. |
| **Recommendation** | Show per-credit cost and savings: "80 FCFA/crédit — Économisez 20%" explicitly next to the price. Add a strikethrough "original" price. |
| **Effort** | Easy |

### Issue #29 — Welcome Bonus May Show "0 crédits"

| | |
|---|---|
| **Severity** | **Low** |
| **Location** | `src/components/ui/WelcomeModal.tsx` |
| **Problem** | Displays `user.point_balance ?? 0` — if the bonus hasn't been applied yet (race condition), the modal shows "0 crédits offerts". |
| **Impact** | Underwhelming first experience. |
| **Recommendation** | Use the configured bonus amount from settings, or add a brief delay/retry before showing the modal. |
| **Effort** | Easy |

---

## Priority Matrix

### 🔴 Do Immediately (Critical + Easy/Medium) — ✅ ALL DONE

| # | Issue | Effort | Status |
|---|-------|--------|--------|
| 12 | Add error states on home/ad/search | Easy | ✅ Done |
| 15 | Add credit spend confirmation step | Easy | ✅ Done |
| 21 | Add `aria-label` to all icon buttons | Easy | ✅ Done |
| 13 | Create branded 404 page | Easy | ✅ Done |
| 14 | Add `error.tsx` boundary files | Easy | ✅ Done |

### 🟠 Do Next Sprint (High + Easy/Medium) — ✅ ALL DONE

| # | Issue | Effort | Status |
|---|-------|--------|--------|
| 17 | Fix AdCard carousel on mobile (swipe + visible arrows) | Medium | ✅ Done |
| 10 | Add inline validation on registration Step 1 | Easy | ✅ Done |
| 1 | Fix fake search bar to be transparent CTA | Medium | ✅ Done |
| 3 | Add landlord/agent messaging to landing | Medium | ✅ Done |

### 🟡 Plan for V2 (Critical + Hard) — Deferred

| # | Issue | Effort | Status |
|---|-------|--------|--------|
| 6 | Allow anonymous browsing | Hard | ⏳ V2 |
| 26 | Reduce funnel barriers | Hard | ⏳ V2 |
| 9 | Simplify registration to 1 step | Hard | ⏳ V2 |

### 🟢 Quick Wins (Low effort, visible polish) — ✅ ALL DONE

| # | Issue | Effort | Status |
|---|-------|--------|--------|
| 2 | Remove "#1" claim | Easy | ✅ Done |
| 4 | Fix social link placeholders | Easy | ✅ Done |
| 5 | Improve testimonial credibility | Easy | ✅ Done |
| 7 | Remove/fix dead footer links | Easy | ✅ Done |
| 8 | Fix dashboard footer links | Easy | ✅ Done |
| 11 | Add skip button to WelcomeOverlay | Easy | ✅ Done |
| 16 | Standardize success feedback | Easy | ✅ Done |
| 18 | Fix OTP responsive sizing | Easy | ✅ Done |
| 19 | Make landing footer responsive | Easy | ✅ Done |
| 20 | Fix CategoryPills center bug | Easy | ✅ Done |
| 22 | Add keyboard nav in lightbox | Easy | ✅ Done |
| 23 | Add `prefers-reduced-motion` | Easy | ✅ Done |
| 24 | Fix `text.disabled` contrast | Easy | ✅ Done |
| 25 | Add label to review comment | Easy | ✅ Done |
| 28 | Add per-credit pricing display | Easy | ✅ Done |
| 29 | Fix welcome bonus race condition | Easy | ✅ Done |
| 27 | Add social proof at purchase points | Medium | ✅ Done |

