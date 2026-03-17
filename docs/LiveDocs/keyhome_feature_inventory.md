# KeyHome — Exhaustive Feature & Functionality Inventory

> **Generated from codebase analysis** — 130 source files across `src/app`, `src/components`, `src/services`, `src/hooks`, `src/providers`, `src/lib`, and `src/types`.

---

## Table of Contents

1. [Authentication & Identity](#1-authentication--identity)
2. [User Profile & Account Management](#2-user-profile--account-management)
3. [Property Ad Management (CRUD)](#3-property-ad-management-crud)
4. [Property Search & Discovery](#4-property-search--discovery)
5. [Ad Detail & Media Viewing](#5-ad-detail--media-viewing)
6. [3D Virtual Tour Viewer](#6-3d-virtual-tour-viewer)
7. [Favorites System](#7-favorites-system)
8. [Property Comparator](#8-property-comparator)
9. [Ad Reporting & Moderation](#9-ad-reporting--moderation)
10. [Reviews & Ratings](#10-reviews--ratings)
11. [Payment Processing (Flutterwave)](#11-payment-processing-flutterwave)
12. [Credits / Points System](#12-credits--points-system)
13. [Ad Unlocking (Contact Reveal)](#13-ad-unlocking-contact-reveal)
14. [Viewing / Appointment Booking](#14-viewing--appointment-booking)
15. [Interactive Map & Geolocation (Nearby Ads)](#15-interactive-map--geolocation-nearby-ads)
16. [Market Price Analysis & Heatmap](#16-market-price-analysis--heatmap)
17. [Rent Estimator](#17-rent-estimator)
18. [KeyScore — Ad Quality Scoring](#18-keyscore--ad-quality-scoring)
19. [Search Alerts](#19-search-alerts)
20. [Surveys & Feedback (Authenticated + Public)](#20-surveys--feedback-authenticated--public)
21. [Push Notifications (Web Push / VAPID)](#21-push-notifications-web-push--vapid)
22. [Recommendation Engine](#22-recommendation-engine)
23. [Ad View Tracking / Telemetry](#23-ad-view-tracking--telemetry)
24. [Blog / Content Marketing](#24-blog--content-marketing)
25. [SEO Comparison Pages](#25-seo-comparison-pages)
26. [City-Specific SEO Landing Pages](#26-city-specific-seo-landing-pages)
27. [JSON-LD Structured Data (Rich Snippets)](#27-json-ld-structured-data-rich-snippets)
28. [OpenGraph Image Generation](#28-opengraph-image-generation)
29. [Landing Page (Public / Unauthenticated)](#29-landing-page-public--unauthenticated)
30. [Help Center / FAQ](#30-help-center--faq)
31. [Contact Form (Multi-Step Wizard)](#31-contact-form-multi-step-wizard)
32. [Settings Page](#32-settings-page)
33. [Theme Switching (Light / Dark Mode)](#33-theme-switching-light--dark-mode)
34. [PWA Support](#34-pwa-support)
35. [Responsive Layout & Navigation](#35-responsive-layout--navigation)
36. [API Layer & Security](#36-api-layer--security)
37. [Error Handling & User Feedback](#37-error-handling--user-feedback)
38. [Legal Pages (Conditions & Privacy)](#38-legal-pages-conditions--privacy)
39. [Internationalization & Locale Formatting](#39-internationalization--locale-formatting)
40. [Landing Statistics (Real-Time Counters)](#40-landing-statistics-real-time-counters)
41. [Payment History & Transaction Tracking](#41-payment-history--transaction-tracking)
42. [Social Account Linking (OAuth)](#42-social-account-linking-oauth)
43. [Property Attributes & Amenities Catalog](#43-property-attributes--amenities-catalog)
44. [Onboarding Flow (Welcome Modal)](#44-onboarding-flow-welcome-modal)
45. [Trusted URL Redirect Guard](#45-trusted-url-redirect-guard)
46. [XSS Sanitization](#46-xss-sanitization)
47. [Unit Test Suite](#47-unit-test-suite)

---

## 1. Authentication & Identity

| File(s) | Detail |
|---|---|
| `services/auth.service.ts` | Full auth lifecycle |
| `providers/AuthProvider.tsx` | Global auth context |
| `app/(auth)/login/page.tsx` | Login form |
| `app/(auth)/register/page.tsx` | Registration form |
| `app/(auth)/forgot-password/page.tsx` | Password reset request |
| `app/(auth)/reset-password/page.tsx` | Password reset execution |
| `app/(auth)/verify-email/page.tsx` | Email verification |
| `app/(auth)/verify-otp/page.tsx` | OTP verification for Clerk |
| `app/(auth)/complete-profile/page.tsx` | Profile completion for OAuth |
| `app/(auth)/auth/callback/page.tsx` | OAuth callback handler |
| `components/auth/SocialLoginButtons.tsx` | Social login UI |
| `components/auth/CompleteOAuthProfileDialog.tsx` | Post-OAuth dialog |
| `lib/auth-token.ts` | Token management for Axios |

**Sub-features:**
- **Email/Password login** — Traditional login via `POST /auth/login`
- **Customer registration** — With firstname, lastname, email, phone, password, optional city
- **Agent registration** — Includes `type` field (`individual` | `agency`) for professional accounts
- **OAuth login (Google, Facebook, Apple)** — Via Clerk SSO; redirects through `getOAuthRedirectUrl()`
- **Clerk token exchange** — `clerkExchange()` converts Clerk JWT to backend API token
- **OTP verification** — `verifyClerkOtp()` handles two-factor for Clerk-based auth
- **OAuth profile completion** — `completeClerkProfile()` collects phone_number/city_id post-OAuth
- **Forgot password** — Sends reset email via `POST /auth/forgot-password`
- **Reset password** — Submits new password with token via `POST /auth/reset-password`
- **Update password** — Authenticated password change via `POST /auth/update-password`
- **Email verification / resend** — `resendVerification()` and `verifyEmailOtp()`
- **Onboarding completion** — `completeOnboarding()` marks user as onboarded
- **Logout** — `POST /auth/logout` + Clerk signOut
- **Role-based access** — `UserRole` enum: `admin`, `agent`, `customer`; guards in `PublishPage` and `AuthProvider`

---

## 2. User Profile & Account Management

| File(s) | Detail |
|---|---|
| `services/users.service.ts` | User CRUD (list, show, update) |
| `app/(dashboard)/profile/page.tsx` | Profile page UI |

**Sub-features:**
- **View profile** — Displays avatar, name, email, phone, city, agency info
- **Update profile** — Multipart form upload via `PUT /users/:id` (supports avatar upload)
- **User listing** — `usersService.list()` with pagination

---

## 3. Property Ad Management (CRUD)

| File(s) | Detail |
|---|---|
| `services/ads.service.ts` | All ad API calls |
| `app/(dashboard)/publish/page.tsx` | Multi-step publish wizard (5 steps) |

**Sub-features:**
- **List ads** — `GET /ads` with pagination, ordering, type filter, `exclude_ids`
- **Show ad detail** — `GET /ads/:id`
- **Create ad** — Multi-step wizard: General info → Details & Price → Location (map picker) → Photos (up to 10) → Confirmation
- **Update ad** — `PUT /ads/:id` with multipart form data (uses POST + `_method` trick for Laravel)
- **Delete ad** — `DELETE /ads/:id`
- **Property types** — Dynamic loading from `GET /ad-types` (maison, appartement, terrain, villa, commerce)
- **City & quarter selection** — Cascading dropdowns from `citiesService` and `quartersService`
- **Property attributes** — 20 toggleable amenities (WiFi, AC, pool, garden, elevator, security, etc.)
- **GPS location** — Map-based pin placement (Mapbox), drag & drop marker, geolocation button
- **Image management** — Multi-image upload, primary image designation, preview with remove
- **Deposit amount & availability date** — Optional fields for lease terms
- **Role restriction** — Only `agent` and `admin` roles can access publish page

---

## 4. Property Search & Discovery

| File(s) | Detail |
|---|---|
| `services/ads.service.ts` (`search`, `autocomplete`, `facets`) | Search API |
| `components/ads/HeroSearch.tsx` | Hero search bar on home |
| `components/ads/NaturalSearchBar.tsx` | Natural language search input |
| `app/search/` (inferred from routes) | Search results page |

**Sub-features:**
- **Keyword search** — `GET /ads/search` with params: `q`, `city`, `type`, `quarter`, `bedrooms`, `price_min/max`, `surface_min/max`, `has_parking`, `sort`, `order`
- **Autocomplete** — Real-time autocomplete on `city`, `type`, `quarter` fields via `GET /ads/autocomplete`
- **Faceted search** — `GET /ads/facets` returns available filters: cities, types, bedrooms, price/surface ranges, parking distribution
- **Natural language search bar** — AI-style search input component
- **Sort & order** — Supports ascending/descending sorting on multiple fields
- **Pagination** — Server-side pagination with `page` and `per_page` params

---

## 5. Ad Detail & Media Viewing

| File(s) | Detail |
|---|---|
| `app/ads/[id]/[slug]/page.tsx` | SSR detail page |
| `app/ads/[id]/[slug]/AdDetailClient.tsx` | Client-side detail logic |
| `components/ads/AdCard.tsx` | Listing card component |
| `components/ads/AdCardSkeleton.tsx` | Loading skeleton |
| `components/ads/StickyPropertyBar.tsx` | Sticky bottom CTA bar |
| `components/ads/SimilarAds.tsx` | Similar ads carousel |
| `components/ads/PropertyAttributes.tsx` | Attributes display (grouped by category) |
| `components/ads/AdLocationMap.tsx` | Single-ad map embed |

**Sub-features:**
- **Dynamic SEO metadata** — Server-rendered metadata with dynamic `generateMetadata()`
- **Image gallery** — Photo carousel with primary image, thumbnails
- **Property details** — Price, surface, bedrooms, bathrooms, parking, description, address
- **Property attributes display** — Grouped by category from `propertyAttributesService.list()`
- **Agent/owner info** — Shows publisher name, avatar, agency
- **Premium info (unlocked)** — Deposit amount, minimum lease, detailed charges, property condition PDF
- **Single-ad map** — Mapbox embed showing ad location
- **Similar ads** — Related listings shown below detail
- **Sticky CTA bar** — Fixed bottom bar with unlock/contact actions
- **Ad status labels** — available, reserved, rent, pending, sold, declined

---

## 6. 3D Virtual Tour Viewer

| File(s) | Detail |
|---|---|
| `components/ads/TourViewer.tsx` | Full Pannellum integration (533 lines) |
| `types/pannellum.d.ts` | TypeScript declarations |

**Sub-features:**
- **Pannellum.js integration** — Loads from JSDelivr CDN with Unpkg fallback
- **Equirectangular panoramas** — Standard 360° photo support with auto-detected FOV
- **Cubemap support** — 6-face cubemap rendering
- **Multi-resolution tiling** — Progressive tile loading (`multires`) for high-res tours
- **Hotspot navigation** — Clickable hotspots to navigate between scenes with custom tooltips
- **Scene navigation pills** — Bottom bar with named scene chips for quick jumping
- **Auto-FOV detection** — Probes image dimensions to auto-calculate HAOV/VAOV for partial panoramas
- **Cross-origin asset resolution** — Prepends backend origin to relative tour URLs
- **Fullscreen modal** — Fixed overlay with loading spinner, error retry, ESC-key close
- **Safety timeout** — 60-second fallback if panorama never loads
- **Scene fade transitions** — 1-second crossfade between scenes

---

## 7. Favorites System

| File(s) | Detail |
|---|---|
| `providers/FavoritesProvider.tsx` | Full favorites context (172 lines) |

**Sub-features:**
- **Local-first storage** — Favorites stored in localStorage immediately (max 100)
- **Server sync** — When authenticated, merges localStorage favorites with `GET /my/favorites`
- **Bidirectional sync** — Pushes local-only favorites to server; merges server-only ones locally
- **Toggle favorite** — Optimistic UI update, fire-and-forget API call (`POST /ads/:id/favorite`)
- **Remove / clear** — Individual remove or bulk clear with server-side unfavoriting
- **Guest mode** — Favorites work without authentication (localStorage only)

---

## 8. Property Comparator

| File(s) | Detail |
|---|---|
| `providers/ComparatorProvider.tsx` | Comparator context |
| `components/ads/ComparatorBar.tsx` | Floating comparator bar |

**Sub-features:**
- **Add/remove properties to compare** — Up to 3 ads max
- **Persistent selection** — Stored in localStorage (`keyhome_comparator`)
- **Max-reached warning** — Visual feedback when 3-item limit is hit
- **Floating bar** — shows selected comparison items with clear action

---

## 9. Ad Reporting & Moderation

| File(s) | Detail |
|---|---|
| `services/ad-reports.service.ts` | Report creation API |
| `components/ads/AdReportModal.tsx` | Multi-step report wizard (443 lines) |

**Sub-features:**
- **Multi-step wizard** — 4 steps: reason → scam sub-reason → payment method → done
- **5 report reasons** — Inaccurate, not real property, scam, shocking content, other
- **5 scam sub-reasons** — Off-platform payment, shared contacts, external promotion, duplicate, misleading
- **Payment method selection** — Bank transfer, card, cash, PayPal, MoneyGram, Western Union, other
- **Free-text description** — For "other" reason, minimum 10 characters
- **Rate limiting** — 429 handling with user-friendly message
- **Error masking** — SQL/internal errors are replaced with generic fallback
- **Animated transitions** — Framer Motion `AnimatePresence` between steps
- **Confirmation screen** — "We received your report" with email notification mention

---

## 10. Reviews & Ratings

| File(s) | Detail |
|---|---|
| `services/reviews.service.ts` | Review creation API |
| `components/reviews/ReviewForm.tsx` | Review submission form |
| `components/reviews/ReviewsSection.tsx` | Reviews display section |

**Sub-features:**
- **Submit review** — `POST /reviews` with `rating` (number), `comment` (optional), `ad_id`
- **Reviews section** — Displays all reviews for an ad with user info, rating, comment, date
- **Review form** — Star rating picker + optional comment textarea

---

## 11. Payment Processing (Flutterwave)

| File(s) | Detail |
|---|---|
| `services/payments.service.ts` | All payment API calls |
| `hooks/usePayment.ts` | Payment initiation hook |
| `hooks/useTransactionStatus.ts` | Payment polling hook |
| `components/payment/PaymentModal.tsx` | Payment dialog (340 lines) |
| `components/payment/PaymentMethodSelector.tsx` | Method picker |
| `components/payment/PaymentAmountDisplay.tsx` | Amount display |
| `components/payment/PaymentStatusBadge.tsx` | Status chip |
| `components/payment/PaymentSuccessScreen.tsx` | Success confirmation |
| `app/payment-success/` | Callback page |
| `app/credits/callback/page.tsx` | Credits purchase callback |

**Sub-features:**
- **Flutterwave initiation** — `POST /payments/initiate_payment` with type, method, phone, ad_id, plan_id, period
- **3 payment methods** — MTN Mobile Money, Orange Money, Bank Card (Visa/Mastercard)
- **Phone number input** — Cameroon format validation (`+237` prefix, 9 digits starting with 6/7/2)
- **Hosted checkout redirect** — Redirects to Flutterwave hosted page, stores `tx_ref` in sessionStorage
- **Payment verification polling** — Polls `POST /payments/verify_payment` every 3 seconds, 5-minute timeout
- **Terminal state detection** — Stops polling on `success`, `failed`, or `cancelled`
- **Payment cancellation** — `POST /payments/cancel_payment`
- **XAF currency formatting** — `Intl.NumberFormat('fr-CM', { currency: 'XAF' })`
- **Error handling** — Retry flow on error, user-friendly messages
- **Premium modal UI** — Gradient header, loading spinner, success/error states

---

## 12. Credits / Points System

| File(s) | Detail |
|---|---|
| `services/credits.service.ts` | Credits API (balance, packages, purchase, verify) |
| `components/layout/CreditsWidget.tsx` | Navbar credit badge |
| `components/ui/PurchaseCreditsModal.tsx` | Package purchase modal |

**Sub-features:**
- **Credit packages** — `GET /credits/packages` returns available point bundles with name, price, features, popularity flag
- **Balance display** — Real-time balance in navbar badge, polls every 30s with 15s stale time
- **Purchase credits** — `POST /credits/purchase/:packageId` initiates Flutterwave checkout
- **Verify purchase** — `POST /credits/verify-purchase` confirms and updates balance
- **Bouncing animation** — First-login users see an animated bouncing badge guiding them to credits
- **Bounce persistence** — Animation stops permanently after first click, tracked via localStorage

---

## 13. Ad Unlocking (Contact Reveal)

| File(s) | Detail |
|---|---|
| `services/payments.service.ts` (`initialize`) | Unlock API |
| Types: `UnlockResponse` | Response handling |

**Sub-features:**
- **Points-based unlock** — `POST /payments/initialize/:adId` deducts credits
- **4 response states** — `unlocked` (success), `insufficient_points` (402 with packages), `owner` (self-owned), `already_unlocked`
- **Insufficient points flow** — Returns available packages and required points, triggers purchase modal
- **Unlocked ads list** — `GET /my/unlocked-ads` returns previously unlocked properties

---

## 14. Viewing / Appointment Booking

| File(s) | Detail |
|---|---|
| `services/viewings.service.ts` | Viewing API (slots, reserve, cancel) |
| `app/(dashboard)/my/reservations/page.tsx` | Reservations management page (512 lines) |

**Sub-features:**
- **Available slots** — `GET /ads/:adId/slots?date=YYYY-MM-DD` returns time slots with availability
- **Reservation creation** — `POST /ads/:adId/reservations` with date, start/end time, optional message
- **My reservations** — `GET /my/reservations` with optional `ad_id` and `status` filters
- **Reservation cancellation** — `DELETE /reservations/:id` with optional cancellation reason
- **4 statuses** — Pending (yellow), Confirmed (green), Cancelled (red), Expired (grey)
- **3 cancellation actors** — Client, Landlord, System
- **Tab filtering** — Active (pending+confirmed), Past (cancelled+expired), All
- **Rich cards** — Date (formatted with `date-fns`/fr locale), time slot, client message, landlord notes, cancellation reason
- **Cancel dialog** — Confirmation modal with optional reason textarea

---

## 15. Interactive Map & Geolocation (Nearby Ads)

| File(s) | Detail |
|---|---|
| `app/(dashboard)/nearby/page.tsx` | Full map + list page (464 lines) |
| `components/ads/AdLocationMap.tsx` | Single ad map |

**Sub-features:**
- **Mapbox GL JS** — Full interactive map with `streets-v12` style
- **Geolocation** — Browser `navigator.geolocation` with Yaoundé fallback
- **Nearby ads** — `GET /ads/nearby` or `GET /ads/:userId/nearby` with lat/lng/radius
- **Dynamic markers** — Price-colored markers with popup (title + price + click-to-navigate)
- **Adjustable radius** — Slider from 1km to 50km
- **Type filtering** — Chips for Tous, Maisons, Appartements, Terrains, Villas, Commerces
- **Price range filter** — Dual slider from 0 to 5,000,000 FCFA
- **Desktop sidebar** — List of filtered ads alongside map
- **Mobile bottom sheet** — Drawer-based ad list with count
- **Mobile filter panel** — Animated dropdown filter panel (spring animation)
- **Relocate button** — Re-center map on user position
- **Active filter indicator** — Badge showing number of active filters
- **XSS-safe popups** — `escapeHtml()` for marker popup content

---

## 16. Market Price Analysis & Heatmap

| File(s) | Detail |
|---|---|
| `services/estimator.service.ts` (`heatmapService`) | Heatmap API |
| `components/maps/PriceHeatmapLayer.tsx` | Heatmap map layer |
| `app/(dashboard)/prix-marche/PrixMarcheClient.tsx` | Price analysis page |

**Sub-features:**
- **Price heatmap** — `GET /price-heatmap` returns features with quarter-level pricing (avg, median, min, max)
- **Visual intensity** — Heat intensity based on price per quarter
- **City & type filtering** — Filter heatmap by city and property type
- **Tabbed interface** — Switch between Heatmap and Rent Estimator

---

## 17. Rent Estimator

| File(s) | Detail |
|---|---|
| `services/estimator.service.ts` (`estimatorService`) | Estimation API |
| `components/ads/RentEstimatorWidget.tsx` | Estimator widget |

**Sub-features:**
- **Rent estimation** — `GET /rent-estimate` with city_id, type_id, surface, bedrooms
- **3-tier results** — Returns estimated min, median, max rent
- **Price per m²** — P25, P50, P75 quartile breakdowns
- **Sample count** — Shows how many ads the estimate is based on

---

## 18. KeyScore — Ad Quality Scoring

| File(s) | Detail |
|---|---|
| `services/estimator.service.ts` (`keyScoreService`) | Score API |
| `components/ads/KeyScoreBadge.tsx` | Score badge |
| `components/ads/KeyScoreSection.tsx` | Score breakdown |

**Sub-features:**
- **Quality score** — `GET /ads/:id/keyscore` returns 0-100 score with label
- **Score breakdown** — Per-criteria scoring (e.g., photos, description, price coherence) with max values
- **Badge display** — Visual badge showing the score

---

## 19. Search Alerts

| File(s) | Detail |
|---|---|
| `services/searchAlerts.service.ts` | Full CRUD for alerts |
| `components/ads/SearchAlertButton.tsx` | Save alert button |

**Sub-features:**
- **Create alert** — `POST /search-alerts` with city, type, quarter, price range, bedrooms, surface, parking, query
- **List alerts** — `GET /search-alerts` returns user's active alerts
- **Update alert** — `PUT /search-alerts/:id` modifies alert criteria
- **Delete alert** — `DELETE /search-alerts/:id`
- **Active toggle** — `is_active` field to pause/resume alerts

---

## 20. Surveys & Feedback (Authenticated + Public)

| File(s) | Detail |
|---|---|
| `services/surveys.service.ts` | Authenticated survey API |
| `services/publicSurveys.service.ts` | Public (anonymous) survey API |
| `app/sondage/` | Survey taking pages |
| `app/surveys/` | Survey-related pages |
| `components/surveys/` | Survey UI components |

**Sub-features:**
- **Active survey** — `GET /surveys/active` fetches currently active survey
- **Survey detail** — `GET /surveys/:id` with full question structure
- **Submit response** — `POST /surveys/:id/responses` with answers array, optional `anonymous` flag
- **Has-answered check** — `GET /surveys/:id/has-answered` prevents duplicate submissions
- **4 question types** — Multiple choice, checkbox, rating, text
- **Public surveys** — Separate unauthenticated API with client token deduplication (UUID in localStorage)
- **Anonymous participation** — `client_token` stored in localStorage prevents re-submission
- **Integration in Settings** — Active survey shown in settings page with completion status

---

## 21. Push Notifications (Web Push / VAPID)

| File(s) | Detail |
|---|---|
| `hooks/usePushNotifications.ts` | Full push notification hook (129 lines) |
| `components/pwa/ServiceWorkerRegistrar.tsx` | SW registration |

**Sub-features:**
- **VAPID key support** — Uses `NEXT_PUBLIC_VAPID_PUBLIC_KEY` environment variable
- **Permission request** — `Notification.requestPermission()` with state tracking
- **Subscribe** — Creates `PushManager.subscribe()`, sends keys to `POST /push/subscribe`
- **Unsubscribe** — Calls `POST /push/unsubscribe` then `subscription.unsubscribe()`
- **Dismiss** — Store dismissal in localStorage to not re-prompt
- **State tracking** — isSupported, permission, isSubscribed, isDismissed

---

## 22. Recommendation Engine

| File(s) | Detail |
|---|---|
| `services/users.service.ts` (`recommendationsService`) | Recommendations API |

**Sub-features:**
- **Personalized recommendations** — `GET /recommendations` returns recommended ads with source metadata
- **View-based feeding** — `adsService.trackView()` feeds the recommendation engine

---

## 23. Ad View Tracking / Telemetry

| File(s) | Detail |
|---|---|
| `services/ads.service.ts` (`trackView`) | Fire-and-forget telemetry |

**Sub-features:**
- **View tracking** — `POST /ads/:id/view` called once per ad detail page visit
- **Fire-and-forget** — Silently ignores errors (non-critical telemetry)
- **Recommendation feeding** — View events power the recommendation engine

---

## 24. Blog / Content Marketing

| File(s) | Detail |
|---|---|
| `app/blog/page.tsx` | Blog index page |
| `app/blog/[slug]/page.tsx` | Individual blog post page |
| `app/blog/posts.ts` | Static blog post data |
| `app/blog/layout.tsx` | Blog layout |

**Sub-features:**
- **Blog listing** — Card-based article list with category chips, dates, read time
- **Blog post rendering** — Individual post pages with full content
- **Static content** — Posts defined in `posts.ts` data file
- **Cross-links** — Links to search, city pages at bottom
- **SEO breadcrumbs** — Accueil › Blog hierarchy

---

## 25. SEO Comparison Pages

| File(s) | Detail |
|---|---|
| `app/comparaison/page.tsx` | Comparison index |
| `app/comparaison/[slug]/page.tsx` | Individual comparison |
| `app/comparaison/comparisons.ts` | Static comparison data |

**Sub-features:**
- **Comparison articles** — "Louer vs Acheter", "Douala vs Yaoundé", etc.
- **Label A vs Label B** — Visual pill badges for each comparison side
- **SEO metadata** — Custom title, description, canonical URL, OpenGraph
- **Static generation** — Pre-rendered comparison content

---

## 26. City-Specific SEO Landing Pages

| File(s) | Detail |
|---|---|
| `app/immobilier/[ville]/page.tsx` | City landing pages (245 lines) |

**Sub-features:**
- **9 African cities** — Douala, Yaoundé, Bafoussam, Abidjan, Cotonou, Lomé, Accra, Dakar, Bamako
- **Static params generation** — `generateStaticParams()` for all cities
- **Dynamic metadata** — City-specific SEO title, description, canonical, OpenGraph
- **Server-side data fetching** — Fetches ads from API with 5-minute ISR revalidation
- **Ad preview grid** — Shows up to 8 ads with thumbnails, prices, quarters
- **RealEstateAgent JSON-LD** — Per-city structured data
- **Cross-links** — Links to all other city pages
- **Long-form SEO content** — "Why search in [City] with KeyHome" text

---

## 27. JSON-LD Structured Data (Rich Snippets)

| File(s) | Detail |
|---|---|
| `components/seo/JsonLd.tsx` | 7 schema types (405 lines) |

**Sub-features:**
- **WebSite schema** — SearchAction for sitelinks search box
- **Organization schema** — Knowledge panel with social links, contact, founding date
- **RealEstateAgent schema** — Niche schema with 7 African countries, price range, service offer
- **SoftwareApplication schema** — App listing with feature list, free pricing tier
- **FAQPage schema** — 10 curated questions/answers targeting SERPs
- **HowTo schema** — 4-step guide with time estimate (5 min) and cost (500 XOF)
- **BreadcrumbList schema** — Accueil → Rechercher → Proximité → Inscription

---

## 28. OpenGraph Image Generation

| File(s) | Detail |
|---|---|
| `app/ads/[id]/[slug]/opengraph-image/route.tsx` | Dynamic OG image generation |

**Sub-features:**
- **Dynamic OG images** — Server-rendered images per ad for social media sharing
- **Ad-specific content** — Includes property title, price, photo in generated image

---

## 29. Landing Page (Public / Unauthenticated)

| File(s) | Detail |
|---|---|
| `components/landing/LandingPage.tsx` | Main landing orchestrator |
| `components/landing/HeroSection.tsx` | Hero with search |
| `components/landing/FeaturesSection.tsx` | Feature showcase |
| `components/landing/HowItWorksSection.tsx` | Step-by-step explainer |
| `components/landing/PricingSection.tsx` | Pricing display |
| `components/landing/TestimonialsSection.tsx` | Testimonial carousel |
| `components/landing/FAQSection.tsx` | FAQ accordion |
| `components/landing/CTASection.tsx` | Call-to-action |
| `components/landing/LandlordSection.tsx` | Host/landlord CTA |
| `components/landing/LandingNav.tsx` | Landing navigation |
| `components/landing/LandingFooter.tsx` | Landing footer |
| `components/landing/ThreeCanvas.tsx` | 3D background (Three.js) |
| `components/landing/PageTransition.tsx` | Page transition animation |
| `components/landing/LandingThemeContext.tsx` | Landing-specific theme |

**Sub-features:**
- **Three.js 3D canvas** — Animated 3D background
- **Page transitions** — Smooth animated transitions between sections
- **Custom theme context** — Distinct design tokens for landing
- **Responsive hero** — Full-width hero with integrated search
- **Social proof** — Live stats counters (ads, cities, users)
- **Multi-section layout** — Features, How It Works, Pricing, Testimonials, FAQ, CTA

---

## 30. Help Center / FAQ

| File(s) | Detail |
|---|---|
| `app/(dashboard)/aide/page.tsx` | Help center page (439 lines) |

**Sub-features:**
- **Searchable FAQ** — Real-time text search across questions and answers
- **3 user profiles** — Acheteur & Locataire, Vendeur & Propriétaire, Agent Immobilier
- **Category filtering** — Chip-based filtering by user profile
- **10 FAQ entries** — Comprehensive Q&A covering contact, favorites, search, viewings, publishing, costs, modifications, availability, professional accounts, agent badge
- **Guide cards** — 5 guides (transaction security, lease checklist, credits system, photos tips, agent badge)
- **Featured guide** — "Publish your first ad in 5 minutes" highlight card
- **Contact CTA** — Email + WhatsApp contact buttons with gradient hero

---

## 31. Contact Form (Multi-Step Wizard)

| File(s) | Detail |
|---|---|
| `app/(dashboard)/contact/page.tsx` | Contact page (531 lines) |

**Sub-features:**
- **3-step wizard** — Contact info → Subject → Message
- **6 predefined subjects** — General, Technical, Report ad, Partnership, Refund, Other
- **Validation per step** — Name length, email format, subject selection, message minimum
- **Animated transitions** — Framer Motion slide animations between steps
- **Progress bar** — Linear progress indicator across steps
- **WhatsApp integration** — Final submission redirects to WhatsApp with pre-formatted message
- **Contact sidebar** — Desktop sticky panel with WhatsApp, Email, Phone links
- **Response time indicator** — "Less than 2h" badge

---

## 32. Settings Page

| File(s) | Detail |
|---|---|
| `app/(dashboard)/parametres/page.tsx` | Settings page (466 lines) |

**Sub-features:**
- **User card** — Quick avatar/name/email display with link to profile
- **Theme picker** — Light/Dark/Auto toggle with visual cards
- **Linked accounts** — Google/Facebook/Apple connect/disconnect via Clerk
- **Notifications toggle** — Push notification switch (placeholder)
- **Active survey** — Shows current survey with completion status and link
- **About section** — Links to Help, Terms, Privacy, Contact
- **Logout** — Logout button with danger styling
- **Version display** — "KeyHome v1.0 — Propulsé par NeoCraftTeam"

---

## 33. Theme Switching (Light / Dark Mode)

| File(s) | Detail |
|---|---|
| `providers/ThemeProvider.tsx` | Theme provider with mode toggle |
| `theme/theme.ts` | MUI theme definition |

**Sub-features:**
- **Light/Dark mode** — Full MUI theme toggle
- **Persistent preference** — Mode preference stored across sessions
- **Custom theme tokens** — Brand colors (#F6475F), typography, component overrides

---

## 34. PWA Support

| File(s) | Detail |
|---|---|
| `components/pwa/PWAInstallPrompt.tsx` | Install prompt |
| `components/pwa/NetworkStatus.tsx` | Online/offline indicator |
| `components/pwa/ServiceWorkerRegistrar.tsx` | SW registration |

**Sub-features:**
- **PWA install prompt** — Custom UI for "Add to Home Screen"
- **Network status** — Detects online/offline state
- **Service worker** — Registration and lifecycle management

---

## 35. Responsive Layout & Navigation

| File(s) | Detail |
|---|---|
| `components/layout/Navbar.tsx` | Desktop/mobile navigation |
| `components/layout/BottomNav.tsx` | Mobile bottom tab bar |
| `components/layout/Footer.tsx` | Site footer |
| `components/layout/AdsTopBar.tsx` | Ads-specific top bar |
| `app/(dashboard)/layout.tsx` | Dashboard layout wrapper |
| `app/(auth)/layout.tsx` | Auth pages layout |

**Sub-features:**
- **Mobile bottom navigation** — 5 tabs: Accueil, Rechercher, Carte, Prix, Profil
- **Safe area padding** — iOS notch support via `env(safe-area-inset-bottom)`
- **Auth-gated navigation** — Profile tab redirects to login if unauthenticated
- **Credits widget in navbar** — Shows point balance with purchase modal trigger
- **Responsive breakpoints** — `md` breakpoint for mobile/desktop switching

---

## 36. API Layer & Security

| File(s) | Detail |
|---|---|
| `lib/api.ts` | Axios instance with interceptors |
| `lib/auth-token.ts` | Token getter module |
| `lib/trusted-redirect.ts` | URL validation |
| `lib/sanitize.ts` | XSS protection |
| `proxy.ts` | API proxy config |

**Sub-features:**
- **Axios singleton** — Centralized API client with `withCredentials: true`, 30s timeout
- **Token interceptor** — Auto-attaches Bearer token to every request
- **Clerk token bridge** — Module-level getter pattern (Axios cant use React hooks)
- **CORS credentials** — Cross-origin cookie support

---

## 37. Error Handling & User Feedback

| File(s) | Detail |
|---|---|
| `lib/error-messages.ts` | Error message extraction |
| `components/ErrorBoundary.tsx` | React error boundary |
| `app/(dashboard)/error.tsx` | Dashboard error page |
| `app/(auth)/error.tsx` | Auth error page |
| `app/(dashboard)/loading.tsx` | Dashboard loading page |
| `app/(auth)/loading.tsx` | Auth loading page |

**Sub-features:**
- **Laravel validation extraction** — Parses 422 response errors from `{ errors: {} }` format
- **Safe error messages** — `getSafeErrorMessage()` handles AxiosError, plain Error, and unknown
- **Error boundaries** — React error boundary component
- **Route-level error/loading** — Next.js `error.tsx` and `loading.tsx` for each route group

---

## 38. Legal Pages (Conditions & Privacy)

| File(s) | Detail |
|---|---|
| `app/conditions/page.tsx` | Terms of service |
| `app/conditions/layout.tsx` | Terms layout |
| `app/confidentialite/page.tsx` | Privacy policy |
| `app/confidentialite/layout.tsx` | Privacy layout |

**Sub-features:**
- **Terms of service page** — Full legal text with dedicated layout
- **Privacy policy page** — GDPR/data protection information

---

## 39. Internationalization & Locale Formatting

| File(s) | Detail |
|---|---|
| `lib/constants.ts` | Formatting utilities |

**Sub-features:**
- **Price formatting** — `formatPrice()` uses `fr-FR` locale with `FCFA` suffix
- **Compact price** — `formatPriceCompact()` converts to "75k FCFA" or "1,5M FCFA"
- **Date formatting** — `formatDate()` with `fr-FR` locale (day, month long, year)
- **Relative dates** — `formatRelativeDate()` returns "Aujourd'hui", "Hier", "Il y a X jours/semaines"
- **Text truncation** — `truncate()` with ellipsis

---

## 40. Landing Statistics (Real-Time Counters)

| File(s) | Detail |
|---|---|
| `hooks/useLandingStats.ts` | Stats fetching hook |
| `services/ads.service.ts` (`getStats`) | Stats API |

**Sub-features:**
- **3 key metrics** — Ads count, Cities count, Users count
- **API endpoint** — `GET /stats/landing`
- **15-minute cache** — `staleTime: 1000 * 60 * 15`
- **Formatted display** — Numbers with `fr-FR` thousands separator + "+" suffix
- **Auth vs Landing variants** — Slightly different label sets for authenticated users

---

## 41. Payment History & Transaction Tracking

| File(s) | Detail |
|---|---|
| `services/payments.service.ts` (`getHistory`) | History API |
| `components/payment/PaymentHistoryTable.tsx` | History table |

**Sub-features:**
- **Paginated history** — `GET /payments/history` with page parameter
- **Transaction details** — Reference, status, type, amount, gateway, method, phone, ad link, pack name, points awarded, date
- **Status display** — Via `PaymentStatusBadge` component

---

## 42. Social Account Linking (OAuth)

| File(s) | Detail |
|---|---|
| `app/(dashboard)/parametres/page.tsx` | Settings-based linking |

**Sub-features:**
- **3 providers** — Google, Facebook, Apple
- **Connect provider** — `clerkUser.createExternalAccount({ strategy, redirectUrl })`
- **Disconnect provider** — `externalAccount.destroy()`
- **Link status** — Shows linked email or "Non connecté"
- **Loading states** — Per-provider loading indicators

---

## 43. Property Attributes & Amenities Catalog

| File(s) | Detail |
|---|---|
| `services/property-attributes.service.ts` | Attributes API |
| `components/ads/PropertyAttributes.tsx` | Grouped attributes display |

**Sub-features:**
- **20 attributes** — WiFi, AC, Heating, Pets, Furnished, Pool, Garden, Balcony, Terrace, Elevator, Security, Gym, Laundry, Storage, Fireplace, Dishwasher, Washer, TV, Accessibility, Smoking
- **Grouped by category** — API returns `grouped` array with category name, slug, and nested attributes
- **Icons per attribute** — Each attribute has `icon` and `admin_icon` fields
- **Used in publishing** — Chip-based toggle selection during ad creation
- **Used in detail** — Categorized display on ad detail page

---

## 44. Onboarding Flow (Welcome Modal)

| File(s) | Detail |
|---|---|
| `components/layout/CreditsWidget.tsx` | Post-onboarding bounce |
| `providers/AuthProvider.tsx` | Onboarding state tracking |

**Sub-features:**
- **Onboarding state** — `user.onboarding_completed_at` tracks completion
- **Welcome modal** — Shown to first-time users (inferred from `kh:welcome-dismissed` event)
- **Credits attention** — Bouncing credits badge animation post-onboarding
- **Complete onboarding** — `POST /auth/onboarding-complete` marks user as onboarded

---

## 45. Trusted URL Redirect Guard

| File(s) | Detail |
|---|---|
| `lib/trusted-redirect.ts` | URL validation (72 lines) |

**Sub-features:**
- **Whitelist-based validation** — 8 default trusted hosts (keyhome.app, clerk.com, flutterwave.com, etc.)
- **Configurable hosts** — `NEXT_PUBLIC_TRUSTED_REDIRECT_HOSTS` environment variable
- **Same-origin pass-through** — Always allows same-origin redirects
- **Protocol enforcement** — HTTPS required for external; HTTP only in dev for localhost
- **Subdomain matching** — Allows subdomains of trusted hosts
- **Safe redirect function** — `redirectToTrustedUrl()` validates before `window.location.assign()`

---

## 46. XSS Sanitization

| File(s) | Detail |
|---|---|
| `lib/sanitize.ts` | HTML escaping utility |

**Sub-features:**
- **Character escaping** — Replaces `&`, `<`, `>`, `"`, `'` with HTML entities
- **Used in map popups** — Prevents XSS in dynamically injected Mapbox popup HTML

---

## 47. Unit Test Suite

| File(s) | Detail |
|---|---|
| `tests/ads.service.test.ts` | Ads service tests |
| `tests/api.test.ts` | API client tests |
| `tests/auth-token.test.ts` | Token management tests |
| `tests/auth.service.test.ts` | Auth service tests |
| `tests/cities.service.test.ts` | Cities service tests |
| `tests/constants.test.ts` | Constants/formatting tests |
| `tests/error-messages.test.ts` | Error message tests |
| `tests/payments.service.test.ts` | Payments service tests |
| `tests/reviews.service.test.ts` | Reviews service tests |
| `tests/sanitize.test.ts` | Sanitize utility tests |
| `tests/trusted-redirect.test.ts` | Redirect guard tests |
| `tests/users.service.test.ts` | Users service tests |
| `tests/setup.ts` | Test setup/bootstrap |

**Sub-features:**
- **12 test files** — Covering services, utilities, and security functions
- **Test setup** — Centralized bootstrap in `setup.ts`

---

## Summary Statistics

| Category | Count |
|---|---|
| **Total source files** | 130 |
| **Top-level features** | 47 |
| **Service modules** | 13 |
| **React components** | 50+ |
| **Custom hooks** | 4 |
| **Context providers** | 5 |
| **Route pages** | 30+ |
| **Unit test files** | 12 |
| **API endpoints consumed** | 45+ |
