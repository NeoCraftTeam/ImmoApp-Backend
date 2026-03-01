# Filament Admin Panel — Comprehensive UI/UX Audit

**Date:** 1 mars 2026  
**Scope:** All 3 Filament panels (Admin, Agency, Bailleur) — 105+ PHP files, 20+ resources  
**Filament version:** v4 (Schemas\Schema API)  
**Evaluator:** Automated code-level heuristic review

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Usability](#2-usability)
3. [Learnability](#3-learnability)
4. [Efficiency](#4-efficiency)
5. [Error Prevention & Recovery](#5-error-prevention--recovery)
6. [Aesthetic Appeal & Consistency](#6-aesthetic-appeal--consistency)
7. [Accessibility](#7-accessibility)
8. [Critical Bugs & Data Integrity Issues](#8-critical-bugs--data-integrity-issues)
9. [Prioritized Action Plan](#9-prioritized-action-plan)

---

## 1. Executive Summary

The KeyHome Filament panels are well-architected with clean separation across three role-based panels (Admin, Agency, Bailleur). Strengths include the `SharedAdResource` trait for DRY ad forms/tables/infolists, custom native-bridge components for mobile WebView integration, well-structured CRUD resources following Filament v4's `Schemas/Tables` separation pattern, and a polished subscription pricing page.

The analysis covers **20+ resources** including the recently added Points System (PointPackages, PointTransactions), Subscription Plans, PropertyAttributes, ActivityLogs, and UnlockedAds resources, in addition to core resources (Users, Ads, Agencies, Payments, Reviews, Cities, PendingAds).

**35 distinct issues** were identified across the six heuristic categories, including **3 critical data integrity bugs**, multiple consistency problems (mixed languages, 5+ date formats, non-functional interactive elements), and accessibility gaps.

| Category | Strengths | Weaknesses | Critical |
|---|---|---|---|
| Usability | 9 | 10 | 1 |
| Learnability | 6 | 6 | 0 |
| Efficiency | 6 | 5 | 0 |
| Error Prevention | 5 | 7 | 2 |
| Aesthetic & Consistency | 6 | 10 | 0 |
| Accessibility | 3 | 5 | 0 |

---

## 2. Usability

### Strengths

| # | Finding | Location |
|---|---------|----------|
| U-S1 | **SharedAdResource trait** (514 lines) provides a consistent, feature-rich ad creation experience across all three panels — image uploads (Spatie, max 10, reorderable), OpenStreetMap picker with geolocation, 7-section infolist (Aperçu, Détails, Caractéristiques, Équipements, Disponibilité, Premium Info, Meta). | `app/Filament/Resources/Ads/Concerns/SharedAdResource.php` |
| U-S2 | **PendingAdResource** has well-designed approve/decline workflows with confirmation modals, email notifications to authors, markdown editor for decline reasons with `minLength(20)`, and semantic status badge coloring. | `Admin/Resources/PendingAds/PendingAdResource.php` |
| U-S3 | **ManageSubscription page** is a comprehensive custom Blade page (845 lines) with period toggle, featured plan highlighting ("Le + populaire"), comparison table, FAQ section, and FedaPay payment integration. | `Agency/Pages/ManageSubscription.php` + Blade template |
| U-S4 | **EditProfile** includes avatar with circle cropper and image editor, phone section with WhatsApp toggle, providing a polished self-service experience. | `Pages/Auth/EditProfile.php` |
| U-S5 | **Agency/Bailleur TopAdsTable** widget provides actionable analytics — top ads sorted by view count with favorites/contacts counts directly on the dashboard, with group-by ad title. | `Bailleur/Widgets/TopAdsTable.php`, `Agency/Widgets/TopAdsTable.php` |
| U-S6 | **PendingAdResource** uses `->poll('15s')` auto-refresh, danger-colored navigation badge showing count, and a polished empty state ("Toutes les annonces ont été traitées. 🎉"). | `Admin/Resources/PendingAds/PendingAdResource.php` |
| U-S7 | **Ad form** uses `->inputMode('numeric')` on price/surface/bedroom fields and `'capture' => 'environment'` for native camera integration via HTML attributes. | `SharedAdResource.php` |
| U-S8 | **PropertyAttributeForm** implements live slug generation (`->live(onBlur: true)->afterStateUpdated()`) and provides a curated icon picker with 25 descriptive options keyed to Heroicons. | `PropertyAttributes/Schemas/PropertyAttributeForm.php` |
| U-S9 | **PointPackageForm** uses well-organized sections ("Informations du pack" with 2-column layout, "Paramètres" for toggles), `TagsInput` for features list, and clear helper texts explaining each field. | `PointPackages/Schemas/PointPackageForm.php` |

### Weaknesses

| # | Severity | Finding | Location | Recommendation |
|---|----------|---------|----------|----------------|
| U-W1 | **High** | **AgencyForm logo field is a plain `TextInput`**, not a `FileUpload`. Admins must manually paste a URL instead of uploading a logo image. | `Admin/Resources/Agencies/Schemas/AgencyForm.php:23` | Replace `TextInput::make('logo')` with `FileUpload::make('logo')->disk('public')->directory('agency-logos')->image()->avatar()`. |
| U-W2 | **High** | **Agency owner select shows raw ID** — `Select::make('owner_id')->relationship('owner', 'id')` displays the numeric ID instead of the owner's name. | `AgencyForm.php:24` | Change to `->relationship('owner', 'firstname')->getOptionLabelFromRecordUsing(fn ($r) => $r->fullname)->searchable()->preload()`. |
| U-W3 | **High** | **AgencyInfolist shows `owner.id`** instead of owner name. Combined with the form issue, agency management is unusable for identifying owners. | `AgencyInfolist.php:25` | Replace `TextEntry::make('owner.id')` with `TextEntry::make('owner.fullname')->label('Propriétaire')`. |
| U-W4 | **Medium** | **Admin PaymentResource has a full editable form** (type, amount, transaction_id, status selects) but table only exposes `ViewAction::make()`. The form is dead code that misleads developers about mutability of payment records. | `Admin/Resources/Payments/PaymentResource.php` | Either convert form to infolist-only or add EditAction if mutation is intended. |
| U-W5 | **Medium** | **Global search is disabled** on the Admin panel (`->globalSearch(false)`). Admins managing 15+ resources have no quick lookup capability. | `AdminPanelProvider.php:51` | Enable global search and ensure all resources have meaningful `$recordTitleAttribute` values. |
| U-W6 | **Medium** | **StatsOverview "En Attente" stat card has `cursor-pointer`** CSS but no click URL/action, creating a misleading affordance — users expect it to navigate to the pending ads list. | `Admin/Widgets/StatsOverview.php:69-71` | Add `->url(PendingAdResource::getUrl())` to link to the pending queue. |
| U-W7 | **Medium** | **UserResource hidden columns** — `type` and `role` have `->visible(false)` despite being available as SelectFilter. Users can filter by role/type but cannot see the matched values in the table results. | `Admin/Resources/Users/UserResource.php:168-173` | Change to `->toggleable(isToggledHiddenByDefault: true)` so columns can be activated on demand. |
| U-W8 | **Medium** | **PointTransactionsTable `ad.id` column** shows raw ad ID instead of title — `TextColumn::make('ad.id')->label('Annonce')->limit(8)`. Users need to identify which ad a transaction relates to. | `PointTransactions/PointTransactionsTable.php` | Change to `TextColumn::make('ad.title')->label('Annonce')->limit(40)->placeholder('—')`. |
| U-W9 | **Medium** | **SubscriptionPlanForm uses `KeyValue` for features** which produces key-value pairs, but `ManageSubscription.blade.php` iterates features as a flat array (`@foreach($plan->features ?? [] as $feature)`). The data structure mismatch means features entered in admin won't render correctly on the subscription page. | `SubscriptionPlans/Schemas/SubscriptionPlanForm.php:90-95` vs `manage-subscription.blade.php` | Switch to `TagsInput::make('features')` or `Repeater` to match the flat array expected by the Blade template. |
| U-W10 | **Low** | **Bailleur PaymentResource table** shows only 4 columns (date, amount, status, ref) without the ad title or payment type, giving landlords insufficient context. | `Bailleur/Resources/Payments/PaymentResource.php` | Add `TextColumn::make('ad.title')->label('Annonce')` and `TextColumn::make('type')->badge()`. |

---

## 3. Learnability

### Strengths

| # | Finding | Location |
|---|---------|----------|
| L-S1 | **Consistent navigation structure** — all three panels use logical `$navigationGroup` labels (Gestion, Annonces, Mon Compte, Retours, Système de Crédits, Configuration, Abonnements) with appropriate Heroicons and `$navigationSort` ordering. | All resource files |
| L-S2 | **CustomRegister** is panel-aware — conditionally shows agency name field only on the agency panel, reducing cognitive load for bailleur registration. | `Pages/Auth/CustomRegister.php:75-80` |
| L-S3 | **Empty states** are descriptive and friendly: PendingAdResource ("Toutes les annonces ont été traitées. 🎉"), ReviewResource ("Les avis de vos clients apparaîtront ici."), with appropriate empty state icons. | Various resources |
| L-S4 | **Phone input** uses `->placeholder('+237 6XX XXX XXX')` showing the expected Cameroon phone format, reducing input errors. | `NativePhoneInput.php` |
| L-S5 | **PointTransactionsTable** uses user-friendly grouping with real-time balance display: `"John Doe — Solde : 42 crédits"`, and translated type badges (Achat, Déblocage, Bonus, Remboursement). | `PointTransactions/PointTransactionsTable.php` |
| L-S6 | **ActivityLogResource** provides translated entity names (`Ad => 'Annonce'`, `User => 'Utilisateur'`, etc.) and categorized event badges (Création/Modification/Suppression) with appropriate colors, with collapsible JSON diff for modifications. | `ActivityLogs/ActivityLogResource.php` |

### Weaknesses

| # | Severity | Finding | Location | Recommendation |
|---|----------|---------|----------|----------------|
| L-W1 | **High** | **Mixed language labels across the interface.** Form labels oscillate between French and English: `'Email address'` (EN) next to `'Mot de Passe'` (FR), `'Phone number copied to clipboard!'` (EN) next to `'Numéro de téléphone'` (FR). Infolist labels use `'Ad'`, `'User'`, `'Payment ID'` in English. | `UserResource.php:83,156-161`, `UnlockedAdResource.php` infolist | Standardize all labels, placeholders, and messages to French. Audit every `->label()`, `->copyMessage()`, `->placeholder()` across all resources. |
| L-W2 | **Medium** | **Admin ReviewResource `recordTitleAttribute` is `'user_id'`** — breadcrumbs display a raw numeric ID instead of a meaningful title. | `Admin/Resources/Reviews/ReviewResource.php:42` | Change to `'rating'` or implement `getRecordTitle()` returning "Avis #X — 4/5". |
| L-W3 | **Medium** | **AgencyResource `recordTitleAttribute` is `'Agency'`** (a static string, not a column name) — this will never resolve to actual record data, breaking breadcrumbs. | `Admin/Resources/Agencies/AgencyResource.php:38` | Change to `'name'`. |
| L-W4 | **Medium** | **Agency Dashboard is empty** — extends `BaseDashboard` with only a custom title but no `getWidgets()` override. Despite having StatsOverview, TopAdsTable, and AdViewsChart widget classes, they're never displayed. | `Agency/Pages/Dashboard.php` | Add `getWidgets()` returning the three Agency widget classes, matching the Bailleur dashboard. |
| L-W5 | **Medium** | **UnlockedAdResource `recordTitleAttribute` is `'ad_id'`** — displays a numeric foreign key instead of meaningful content. | `UnlockedAds/UnlockedAdResource.php:42` | Change to implement `getRecordTitle()` or use a computed attribute. |
| L-W6 | **Low** | **ManageSettings** uses a 2-step email verification flow for saving settings, but the UX progression is non-obvious: Save → sends code → must click separate "Confirmer avec le code" header action. | `Admin/Pages/ManageSettings.php` | Add a step indicator or inline code input below the form. |

---

## 4. Efficiency

### Strengths

| # | Finding | Location |
|---|---------|----------|
| E-S1 | **SPA mode** is enabled on the Admin panel, eliminating full page reloads for snappy navigation. | `AdminPanelProvider.php:57` |
| E-S2 | **Deferred loading** (`->deferLoading()`) is used on the UserResource table, preventing slow initial page loads. | `UserResource.php:121` |
| E-S3 | **Import/Export actions** are available on User, Ad, City, Payment, and UnlockedAd resources, enabling bulk operations. | Various admin resources |
| E-S4 | **Slide-over modals** with `Width::FourExtraLarge` for ad editing keep context visible without full page navigation. | `Agency/Bailleur AdResource` ManageAds pages |
| E-S5 | **Eager loading** is consistently applied (`->with(...)`) preventing N+1 query issues across all resources, including PointTransactions (`->modifyQueryUsing(fn ($q) => $q->with(['user', 'ad']))`). | All resources with relationships |
| E-S6 | **PropertyAttributesTable** uses `->reorderable('sort_order')` for drag-and-drop reordering and `ToggleColumn` for instant inline activation — no need to open an edit form for quick changes. | `PropertyAttributes/Tables/PropertyAttributesTable.php` |

### Weaknesses

| # | Severity | Finding | Location | Recommendation |
|---|----------|---------|----------|----------------|
| E-W1 | **High** | **Admin dashboard widgets perform uncached raw SQL on every render.** 9 widgets fire simultaneously: `AdsByCityChart` and `AdsByTypeChart` use `DB::raw()` joins. No polling interval is set, so these re-trigger on every Livewire update. | `Admin/Widgets/*.php` | Add `protected static ?string $pollingInterval = '60s'` and `Cache::remember()` with a 5-min TTL for aggregation queries. |
| E-W2 | **Medium** | **Bailleur/Agency StatsOverview widgets fire 4-5 separate count queries** (views, favorites, contacts, impressions) individually on each render. | `Bailleur/Widgets/StatsOverview.php`, `Agency/Widgets/StatsOverview.php` | Consolidate into a single `selectRaw('SUM(CASE WHEN type = ? THEN 1 ELSE 0 END) as ...')` query. |
| E-W3 | **Medium** | **Agency/Bailleur AdViewsChart** runs 2 separate queries per render and uses identical PHP loop for date generation. Code is 100% duplicated between both panels. | `Bailleur/Widgets/AdViewsChart.php`, `Agency/Widgets/AdViewsChart.php` | Extract to a shared `Concerns\AdViewsChartTrait` or abstract base widget. |
| E-W4 | **Medium** | **ActivityLogResource uses subquery for admin-only scope** — `whereIn('causer_id', function ($query) { ... })` runs a subselect on every page load. With growth, this will degrade. | `ActivityLogs/ActivityLogResource.php:53-57` | Use a join or cache admin IDs. Consider a `log_name` scope instead. |
| E-W5 | **Low** | **Database notifications poll at 30s interval** on the Admin panel. Combined with activity log polling at 30s, this creates 2 polling loops. | `AdminPanelProvider.php:58`, `ActivityLogResource.php` table poll | Align polling intervals or use event-driven updates. |

---

## 5. Error Prevention & Recovery

### Strengths

| # | Finding | Location |
|---|---------|----------|
| EP-S1 | **Unsaved changes alerts** are enabled on the Admin panel, preventing accidental data loss when navigating away from forms. | `AdminPanelProvider.php:59` |
| EP-S2 | **Decline action requires `minLength(20)`** on the reason field, ensuring moderators provide substantive feedback. | `PendingAdResource.php:163` |
| EP-S3 | **Soft-delete + TrashedFilter** is consistently applied across all deletable resources, enabling recovery. | UserResource, AdResource, AgencyResource, UnlockedAdResource, etc. |
| EP-S4 | **Subscription cancellation uses `wire:confirm`** with a clear French warning before executing. | `manage-subscription.blade.php` |
| EP-S5 | **PointPackageForm** enforces proper constraints — `->minValue(0)` on price, `->minValue(1)` on points_awarded, `->maxLength()` on text fields. | `PointPackages/Schemas/PointPackageForm.php` |

### Weaknesses

| # | Severity | Finding | Location | Recommendation |
|---|----------|---------|----------|----------------|
| EP-W1 | **Critical** | **Double-hashing bug.** `UserResource` form uses `->dehydrateStateUsing(fn (string $state): string => Hash::make($state))` but the User model already has `'password' => 'hashed'` cast. Passwords saved via this admin form will be double-hashed, rendering accounts inaccessible. | `UserResource.php:94` | Remove the `dehydrateStateUsing` call — the model's `hashed` cast already handles hashing. |
| EP-W2 | **Critical** | **PendingAdResource decline action calls `$record->delete()`** (soft-delete) without updating the status from `PENDING`. If a declined ad is restored, it re-appears in the moderation queue without re-review. | `PendingAdResource.php:180-182` | Add `$record->forceFill(['status' => AdStatus::UNAVAILABLE])->save()` before `$record->delete()`. |
| EP-W3 | **High** | **ManageSubscription `verifyPayment` trusts URL query parameters** (`request()->query('status')` and `request()->query('id')`) to initiate payment verification on page mount. Although it verifies with FedaPay API, the transaction_id is user-controllable. | `ManageSubscription.php:54-56` | Validate transaction_id format. Use signed URLs from the callback. |
| EP-W4 | **Medium** | **Ad form price field** uses `TextInput` with `->inputMode('numeric')` but no `->numeric()` validation rule or `->minValue(0)` — negative prices or non-numeric strings can be submitted. | `SharedAdResource.php` (price field) | Add `->numeric()->minValue(0)` to the price field. |
| EP-W5 | **Medium** | **Rating field in Admin ReviewResource** is `TextInput::make('rating')->numeric()->minValue(1)->maxValue(5)` — no step constraint. Users can enter fractional values like `2.7`. | `Admin/Resources/Reviews/ReviewResource.php:64-67` | Add `->step(1)` to enforce integer ratings. |
| EP-W6 | **Medium** | **SubscriptionResource allows editing status/dates directly** without validation logic — an admin could set `starts_at` after `ends_at`, or set status to `active` for an expired date range. | `SubscriptionResource.php` form | Add `->after('starts_at')` rule on `ends_at` and cross-field validation. |
| EP-W7 | **Low** | **ManageSubscription** instantiates `new \App\Services\SubscriptionService` directly in `cancelSubscription()` and `verifyPayment()` instead of using the container (`app(SubscriptionService::class)`) as done in `mount()`. | `ManageSubscription.php:216, 130` | Use `app()` consistently for DI/testability. |

---

## 6. Aesthetic Appeal & Consistency

### Strengths

| # | Finding | Location |
|---|---------|----------|
| AC-S1 | **Distinct panel color theming** — Admin (Amber), Agency (#2563eb blue), Bailleur (#10b981 green) provides clear visual identity per role. | All three PanelProviders |
| AC-S2 | **Custom brand Blade components** for Agency ("KeyHome Agency" in blue) and Bailleur ("KeyHome Owner" in green) with `hue-rotate` on the shared logo provide visual cohesion with minimal duplication. | `resources/views/filament/agency/brand.blade.php`, `bailleur/brand.blade.php` |
| AC-S3 | **ManageSubscription Blade** is a craft-grade custom UI with CSS design tokens (`--sub-primary`, `--sub-surface`, etc.), dark mode support, responsive breakpoints (900px + 480px), gradient banners, and smooth transitions. | `manage-subscription.blade.php` |
| AC-S4 | **Consistent badge usage** — `->badge()` is applied on status, type, role, payment_method, and point transaction type columns across all panels for visual scanning. Color mappings are consistent (success=active, warning=pending, danger=expired/unavailable). | Various resources |
| AC-S5 | **Native UI CSS** includes dark mode via `prefers-color-scheme`, `prefers-reduced-motion`, skeleton loading, and offline indicator for the WebView experience. | `resources/css/native-ui.css` |
| AC-S6 | **Newer resources follow better patterns** — PointPackagesTable, SubscriptionPlansTable, and PropertyAttributesTable use proper French labels, `->money('XAF')`, toggleable date columns, and `->defaultSort()`. | Recently added resources |

### Weaknesses

| # | Severity | Finding | Location | Recommendation |
|---|----------|---------|----------|----------------|
| AC-W1 | **High** | **Inconsistent date formats across resources.** At least 5 formats coexist: `dateTime()` (Laravel default), `'M j, Y H:i'` (English), `'d/m/Y H:i'` (French without separator), `'d/m/Y à H:i'` (French proper), `->since()` (relative). Some tables (`UserResource`) mix `dateTime()` and `'M j, Y H:i'` in the same view. | Throughout all resources | Standardize on `'d/m/Y à H:i'` for all timestamp columns. Use `->since()` only for contextual "time ago" supplementary columns. |
| AC-W2 | **High** | **3 chart widgets have commented-out headings**, rendering them as unlabeled chart blocks on the dashboard: `RevenueChart`, `AdsByCityChart`, `AdsByTypeChart` all have `// protected static ?string $heading = ...`. | `Admin/Widgets/RevenueChart.php:16`, `AdsByCityChart.php:16`, `AdsByTypeChart.php:16` | Uncomment the headings. Charts without titles are meaningless at a glance. |
| AC-W3 | **Medium** | **Mixed emoji usage in labels.** TopAdsTable uses emojis (`'👁 Vues'`, `'❤️ Favoris'`, `'📞 Contacts'`) while all other tables use Heroicons. Inconsistent with the design system. | `Bailleur/Widgets/TopAdsTable.php`, `Agency/Widgets/TopAdsTable.php` | Replace emoji labels with `->icon('heroicon-o-eye')` / `->icon('heroicon-o-heart')` / `->icon('heroicon-o-phone')`. |
| AC-W4 | **Medium** | **AgenciesTable lacks visual identity** — shows only name, owner, timestamps. No logo image, no member count, no subscription status. | `Admin/Resources/Agencies/Tables/AgenciesTable.php` | Add `ImageColumn::make('logo')`, member/ad counts, subscription status badge. |
| AC-W5 | **Medium** | **Bailleur/Agency panels use 100% duplicated widget code** (StatsOverview, TopAdsTable, AdViewsChart) with only cosmetic label differences ("Mes Biens" vs "Mes Annonces"). | `Bailleur/Widgets/*` vs `Agency/Widgets/*` | Extract shared logic into base widget classes or traits, parameterizing only the labels. |
| AC-W6 | **Medium** | **UserResource `copyMessage` is in English** (`'Phone number copied to clipboard!'`, `'Email copied to clipboard!'`) while all surrounding UI is French. | `UserResource.php:156-161` | Change to `'Numéro copié !'` and `'Email copié !'`. |
| AC-W7 | **Medium** | **Currency code inconsistency** — `'XAF'` is used in most places, but SubscriptionResource uses `'XOF'` (`->money('XOF', divideBy: 1, locale: 'fr_FR')`). FCFA in CEMAC zone is XAF, not XOF (which is West African CFA). | `SubscriptionResource.php` table column | Change to `->money('XAF')` consistently across all resources. |
| AC-W8 | **Medium** | **SubscriptionPlansTable boost_score badge** says "crédits" (`"+{$state} crédits"`) but it represents a visibility score, not credits. The PointPackagesTable correctly uses "crédits" for actual credit amounts. | `SubscriptionPlans/Tables/SubscriptionPlansTable.php:43` | Change to `"+{$state} pts"` or `"Score +{$state}"`. |
| AC-W9 | **Low** | **Navigation icons are inconsistent across panels** — `Heroicon::InboxArrowDown` (Agency ads), `Heroicon::Home` (Bailleur ads); `Heroicon::CurrencyDollar` (Admin payments), `Heroicon::Banknotes` (Agency payments), `Heroicon::CreditCard` (Bailleur payments). | Various resources | Standardize: one icon for "Ads", one for "Payments", one for "Reviews" across all panels. |
| AC-W10 | **Low** | **Inline `style` attributes** in brand Blade templates (`style="height: 2.5rem; filter: hue-rotate(160deg);"`) instead of Tailwind classes. | `brand.blade.php` files | Convert to Tailwind: `class="h-10 w-auto"` with CSS custom properties for filters. |

---

## 7. Accessibility

### Strengths

| # | Finding | Location |
|---|---------|----------|
| A-S1 | **`prefers-reduced-motion` media query** in `native-ui.css` disables all animations and transitions, sets `animation-duration: 0.01ms`, and stops the spinner for users with motion sensitivity. | `resources/css/native-ui.css` |
| A-S2 | **Form inputs use `->label()`** consistently across all resources, ensuring screen readers associate labels with fields. Helper texts provide additional context. | All form definitions |
| A-S3 | **Color-coded badges always include text labels** (e.g., "Actif", "En attente", "Expiré") rather than relying on color alone. | Various table columns |

### Weaknesses

| # | Severity | Finding | Location | Recommendation |
|---|----------|---------|----------|----------------|
| A-W1 | **High** | **Star ratings use Unicode characters** (`★★★☆☆`) without `aria-label`. Screen readers will read individual star characters. Used in both Agency and Bailleur ReviewResource tables (4 instances). | `Agency/Reviews/ReviewResource.php`, `Bailleur/Reviews/ReviewResource.php` | Add `->extraAttributes(['aria-label' => fn ($state) => "{$state} étoiles sur 5"])`. The infolist entries already append "(4/5)" text — table columns should do the same. |
| A-W2 | **Medium** | **ManageSubscription Blade (845 lines) has no ARIA landmarks.** Plain `<div>` elements without `role="region"`, `aria-label`. Comparison table lacks `<caption>` and `scope="col"` on headers. Cancel button has no `aria-describedby` linking to the confirmation text. | `manage-subscription.blade.php` | Add `aria-label` to major sections, `<caption>` to comparison table, `scope="col"` to table headers. |
| A-W3 | **Medium** | **Emoji in column labels** (`'👁 Vues'`, `'❤️ Favoris'`, `'📞 Contacts'`) may produce confusing screen reader announcements like "eye Vues" or "telephone Contacts". | `TopAdsTable.php` columns (both panels) | Use `->icon()` with Heroicons instead, or wrap emoji in `aria-hidden="true"` spans. |
| A-W4 | **Medium** | **Color alone conveys meaning** in engagement rate stat: `->color($engagementRate > 5 ? 'success' : 'gray')`. Users who cannot distinguish green from gray miss the threshold significance. | `Agency/Bailleur StatsOverview.php` | Add descriptive text: `->description($engagementRate > 5 ? 'Bon engagement' : 'Engagement faible')` alongside the percentage. |
| A-W5 | **Low** | **`native-ui.css` disables `user-select` on inputs** (`.is-native-app input { user-select: none; }`) — prevents assistive technology users from selecting and copying text from input fields. | `resources/css/native-ui.css` | Remove `input` from the `user-select: none` rule; keep only for `button` and `[role="button"]`. |

---

## 8. Critical Bugs & Data Integrity Issues

These items warrant immediate attention as they can cause data corruption or functional failures.

### BUG-001: Double Password Hashing (Critical)

**File:** `Admin/Resources/Users/UserResource.php:94`  
**Impact:** Any password set via the admin form is hashed twice, rendering the account inaccessible.  
**Root cause:** `->dehydrateStateUsing(fn (string $state): string => Hash::make($state))` combined with User model's `'password' => 'hashed'` cast.  
**Fix:** Remove the `dehydrateStateUsing` call entirely.

### BUG-002: Declined Ads Retain PENDING Status (Critical)

**File:** `Admin/Resources/PendingAds/PendingAdResource.php:180-182`  
**Impact:** Soft-deleted declined ads that are restored will re-appear as pending without re-review.  
**Root cause:** `$record->delete()` is called without updating the status to a terminal state.  
**Fix:** Add `$record->forceFill(['status' => AdStatus::UNAVAILABLE])->save()` before `$record->delete()`.

### BUG-003: Agency `recordTitleAttribute` is a Static String (Medium)

**File:** `Admin/Resources/Agencies/AgencyResource.php:38`  
**Impact:** `protected static ?string $recordTitleAttribute = 'Agency'` tries to find a column named `Agency` which doesn't exist, breaking breadcrumbs and global search.  
**Fix:** Change to `'name'`.

### BUG-004: SubscriptionPlan Features Data Structure Mismatch (Medium)

**File:** `SubscriptionPlans/Schemas/SubscriptionPlanForm.php:90-95` vs `manage-subscription.blade.php`  
**Impact:** Admin edits features as `KeyValue` (key-value pairs), but subscription page iterates as a flat array (`@foreach($plan->features ?? [] as $feature)`). Features may not display correctly.  
**Fix:** Align both sides — use `TagsInput` (flat array) in admin, or update Blade to iterate key-value pairs.

### BUG-005: Wrong Currency Code (Low)

**File:** `SubscriptionResource.php` — `->money('XOF', divideBy: 1, locale: 'fr_FR')`  
**Impact:** Displays amounts with West African CFA (XOF) symbol instead of Central African CFA (XAF). Misrepresents the currency.  
**Fix:** Change to `->money('XAF')`.

---

## 9. Prioritized Action Plan

### P0 — Immediate (Data Integrity / Functional Bugs) — ~45 min

| # | Action | Effort |
|---|--------|--------|
| 1 | Fix double password hashing in UserResource (BUG-001) | 5 min |
| 2 | Fix declined ad status before deletion (BUG-002) | 5 min |
| 3 | Fix AgencyResource `recordTitleAttribute` (BUG-003) | 1 min |
| 4 | Fix features data structure mismatch (BUG-004) | 10 min |
| 5 | Fix currency code XOF → XAF (BUG-005) | 2 min |
| 6 | Fix AgencyForm: replace logo TextInput with FileUpload | 10 min |
| 7 | Fix AgencyForm: owner_id select to show fullname | 5 min |
| 8 | Fix AgencyInfolist: owner.id → owner.fullname | 2 min |

### P1 — High Priority (UX / Consistency) — ~3 hr

| # | Action | Effort |
|---|--------|--------|
| 9 | Standardize all labels/messages to French (audit all `->label()`, `->copyMessage()`) | 1-2 hr |
| 10 | Standardize date formats to `'d/m/Y à H:i'` across all resources | 30 min |
| 11 | Uncomment chart headings (Revenue, AdsByCity, AdsByType) | 5 min |
| 12 | Enable global search on Admin panel + fix all `recordTitleAttribute` values | 15 min |
| 13 | Add click URL to StatsOverview "En Attente" stat | 5 min |
| 14 | Register widgets on Agency Dashboard (match Bailleur pattern) | 10 min |
| 15 | Change UserResource hidden columns to toggleable | 5 min |
| 16 | Fix PointTransactionsTable `ad.id` → `ad.title` | 2 min |
| 17 | Fix SubscriptionPlansTable boost_score label from "crédits" to "pts" | 2 min |
| 18 | Add Star rating ARIA labels for accessibility | 15 min |

### P2 — Medium Priority (Code Quality / Performance) — ~5 hr

| # | Action | Effort |
|---|--------|--------|
| 19 | Add cache layer to admin dashboard widget queries | 1 hr |
| 20 | Consolidate Bailleur/Agency duplicated widgets into shared base | 2 hr |
| 21 | Consolidate StatsOverview interaction queries into single SQL | 30 min |
| 22 | Replace emoji column labels with Heroicons in TopAdsTable | 15 min |
| 23 | Add ARIA landmarks to ManageSubscription Blade | 30 min |
| 24 | Add validation (numeric, minValue) to ad price field | 5 min |
| 25 | Clean up dead form code in Admin PaymentResource | 15 min |
| 26 | Add Bailleur payment table columns (ad title, type) | 10 min |
| 27 | Add cross-field validation to SubscriptionResource (starts_at < ends_at) | 15 min |
| 28 | Optimize ActivityLogResource admin scope query | 15 min |

### P3 — Low Priority (Polish) — ~2 hr

| # | Action | Effort |
|---|--------|--------|
| 29 | Standardize navigation icons across panels | 15 min |
| 30 | Convert brand Blade inline styles to Tailwind | 10 min |
| 31 | Add step(1) constraint to review rating field | 2 min |
| 32 | Narrow user-select:none scope in native CSS | 10 min |
| 33 | Add contextual description text for engagement rate color | 5 min |
| 34 | Add ManageSettings step indicator for verification flow | 30 min |
| 35 | Use DI container consistently in ManageSubscription | 5 min |

---

**Total estimated effort: ~11 hours for all 35 items.**  
**P0 items alone: ~45 minutes.**  
**P0 + P1: ~4 hours for the most impactful improvements.**
