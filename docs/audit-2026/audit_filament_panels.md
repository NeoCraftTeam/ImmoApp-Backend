# Audit Report — Filament Backoffice Panels (Admin / Agency / Bailleur)

## Application Overview

Three Filament panels serve different user personas. Agency and Bailleur panels are loaded inside WebView wrappers by the React Native mobile apps.

| Attribute | Admin | Agency | Bailleur |
|---|---|---|---|
| **Path** | `/admin` | `/agency` | `/bailleur` |
| **MFA** | ✅ Required (App + Email) | ❌ None | ❌ None |
| **Registration** | ❌ Disabled | ✅ Open (`CustomRegister`) | ✅ Open (`CustomRegister`) |
| **Tenant** | — | `Agency` model | — (no tenant) |
| **Access Control** | `canAccessPanel` → `isAdmin()` | `canAccessPanel` → Agent + Agency type | `canAccessPanel` → Agent + Individual type |
| **Resources** | 12 resources + 3 widgets | 3 resources (Ads, Payments, Reviews) | 3 resources (Ads, Payments, Reviews) |
| **Mobile Bridge** | ❌ | ✅ render hooks + mobile-bridge.blade.php | ✅ render hooks + mobile-bridge.blade.php |

---

## 🔴 Critical Findings

### FC-1. `mobile-bridge.blade.php` Sets Data on ALL Livewire Components Without Origin Validation

| Attribute | Value |
|---|---|
| **File** | [mobile-bridge.blade.php](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/resources/views/filament/mobile-bridge.blade.php) |
| **Severity** | 🔴 Critical |

**Evidence:**
```javascript
window.addEventListener('message', (event) => {
  // No origin check on event.origin
  const message = typeof event.data === 'string' ? JSON.parse(event.data) : event.data;
  if (message && message.type === 'LOCATION_RECEIVED') {
    const components = Livewire.all();
    components.forEach(component => {
      component.set('data.latitude', latitude);
      component.set('data.longitude', longitude);
    });
  }
});
```

Any page that can embed this Filament page in an `<iframe>` (or any extension/injected script) can send a `postMessage` with type `LOCATION_RECEIVED` and **set arbitrary `data.*` properties on every Livewire component on the page**. There is:
- No `event.origin` check
- No validation of latitude/longitude values (could inject strings)
- **Blanket `component.set('data.*')` on ALL components** — could affect non-location form fields if component naming collides

**Remediation:**
```diff
 window.addEventListener('message', (event) => {
+    // Only accept messages from our native app
+    if (event.origin !== 'null' && !['https://keyhomeback.neocraft.dev'].includes(event.origin)) return;
     const message = typeof event.data === 'string' ? JSON.parse(event.data) : event.data;
     if (message && message.type === 'LOCATION_RECEIVED') {
-        const components = Livewire.all();
-        components.forEach(component => {
-            component.set('data.latitude', latitude);
-            component.set('data.longitude', longitude);
-        });
+        const { latitude, longitude } = message.data;
+        if (typeof latitude !== 'number' || typeof longitude !== 'number') return;
+        // Target only the ad creation/edit component
+        const adForm = Livewire.find(document.querySelector('[wire\\:id]')?.getAttribute('wire:id'));
+        if (adForm) {
+            adForm.set('data.latitude', latitude);
+            adForm.set('data.longitude', longitude);
+        }
     }
 });
```

---

### FC-2. Admin Can Set `email_verified_at` and `role` Without Restriction

| Attribute | Value |
|---|---|
| **File** | [UserResource.php](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/app/Filament/Admin/Resources/Users/UserResource.php#L83-L107) |
| **Severity** | 🔴 Critical |

**Evidence:**
```php
DateTimePicker::make('email_verified_at'),  // Can bypass email verification
Select::make('role')
    ->options(UserRole::class)              // Can set any user as admin
    ->required(),
```

An admin can:
1. **Bypass email verification** by manually setting `email_verified_at` to any date
2. **Create new admins** without any elevated privilege check (no superadmin concept)
3. **Promote any user to admin** role via the edit form

While admins are trusted, there's no audit trail or confirmation for role escalation. Combined with the self-registration flow, if an attacker ever gains admin access, they can silently create backdoor admin accounts.

**Remediation:**
- Make `email_verified_at` read-only (display only via `TextEntry`)
- Add confirmation dialog for role changes to `admin`
- Log all role changes to an audit table
- Consider a `superadmin` role for the most sensitive operations

---

### FC-3. Inline `<script>` in renderHook — XSS Surface

| Attribute | Value |
|---|---|
| **Files** | [AgencyPanelProvider.php](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/app/Providers/Filament/AgencyPanelProvider.php#L64), [BailleurPanelProvider.php](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/app/Providers/Filament/BailleurPanelProvider.php#L63) |
| **Severity** | 🔴 Critical |

**Evidence:**
```php
->renderHook(
    'panels::body.start',
    fn (): string => '<script>if(window.location.search.includes("app_mode=native") || window.ReactNativeWebView) { document.body.classList.add("is-mobile-app"); }</script>',
)
```

This inline `<script>` violates CSP best practices and reads from `window.location.search` directly. An attacker could craft a URL with specific query parameters. Additionally, the `window.ReactNativeWebView` check can be spoofed by any page injecting this property.

**Remediation:**
- Move this logic to an external JavaScript file loaded via Filament's `->assets()` method
- Use a signed token or header from the native app instead of URL query detection
- Apply CSP nonce to any remaining inline scripts

---

## 🟠 High Findings

### FH-1. Agency/Bailleur Panels Have No MFA

| Attribute | Value |
|---|---|
| **Files** | [AgencyPanelProvider.php](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/app/Providers/Filament/AgencyPanelProvider.php), [BailleurPanelProvider.php](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/app/Providers/Filament/BailleurPanelProvider.php) |
| **Severity** | 🟠 High |

Admin panel enforces MFA (`isRequired: true`). Agency and Bailleur panels have **no MFA at all**. These panels manage real ads, payments, and customer data — account takeover gives full access to business-critical data.

**Remediation:** Add MFA (at minimum email-based) to both panels:
```php
->multiFactorAuthentication([
    EmailAuthentication::make(),
], isRequired: true)
```

---

### FH-2. `preserveFilenames()` on Avatar Upload — Path Traversal Risk

| Attribute | Value |
|---|---|
| **Files** | [EditProfile.php](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/app/Filament/Pages/Auth/EditProfile.php#L22), [UserResource.php](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/app/Filament/Admin/Resources/Users/UserResource.php#L66) |
| **Severity** | 🟠 High |

**Evidence:**
```php
FileUpload::make('avatar')
    ->preserveFilenames()  // Original filename used as-is
    ->directory('avatars')
```

`preserveFilenames()` uses the original filename from the upload. A crafted filename like `../../storage/framework/.env` could attempt path traversal. Laravel's storage abstraction mitigates some risk, but `preserveFilenames()` is still dangerous.

**Remediation:** Remove `preserveFilenames()` — let Filament generate random filenames (the default), or use `->getUploadedFileNameForStorageUsing()` with sanitization.

---

### FH-3. CustomRegister Race Condition — User Created as CUSTOMER Before Promotion

| Attribute | Value |
|---|---|
| **File** | [CustomRegister.php](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/app/Filament/Pages/Auth/CustomRegister.php#L62-L87) |
| **Severity** | 🟠 High |

**Evidence:**
```php
$user = User::create([
    'role' => UserRole::CUSTOMER,  // Created as CUSTOMER first
    'is_active' => true,
]);
// Then promoted...
$agencyService->promoteToAgency($user, $agencyName);
```

The user is created as `CUSTOMER` with `is_active: true`, then promoted in a separate call. If the promotion fails (exception, timeout), the user exists as an active customer who **cannot access the panel they registered on** (since `canAccessPanel` checks role). `AgencyService` uses `DB::transaction()` for its own operations, but the `User::create()` call is **outside** that transaction.

**Remediation:** Wrap the entire `handleRegistration` method in a DB transaction:
```php
return DB::transaction(function () use ($data, $panelId) {
    $user = User::create([...]);
    // promote...
    return $user;
});
```

---

### FH-4. No `maxSize` on Image Uploads

| Attribute | Value |
|---|---|
| **Files** | All `SpatieMediaLibraryFileUpload` usages in Ad resources |
| **Severity** | 🟠 High |

**Evidence:**
```php
SpatieMediaLibraryFileUpload::make('images')
    ->collection('images')
    ->multiple()
    ->maxFiles(10)
    // Missing: ->maxSize(2048) or ->acceptedFileTypes([...])
```

No `maxSize()` constraint — users can upload arbitrarily large images (limited only by `upload_max_filesize` in php.ini). 10 files × unlimited size = potential disk exhaustion or DoS.

**Remediation:** Add `->maxSize(5120)` (5MB per file) and `->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])`.

---

## 🟡 Medium Findings

| ID | Finding | File | Impact |
|---|---|---|---|
| FM-1 | Bailleur panel has no `->tenant()` config unlike Agency — relies only on `getEloquentQuery()` filter by `auth()->id()` | [BailleurPanelProvider.php](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/app/Providers/Filament/BailleurPanelProvider.php) | Weaker data isolation |
| FM-2 | Agency/Bailleur resources almost **100% duplicated** (Ads, Payments, Reviews) | Both resource dirs | Maintenance burden, bug divergence |
| FM-3 | Admin panel `globalSearch(false)` is fine, but Agency/Bailleur don't explicitly disable it | Panel providers | Could leak cross-tenant data via search |
| FM-4 | `getNavigationBadge()` uses `::count()` without cache — N+1 on every page load | All resources | Performance, DB load |
| FM-5 | Ad form uses `$` prefix for price, but currency is XAF (CFA Franc) in table display | All Ad resources | Confusing UX |
| FM-6 | `status` disabled only when `PENDING` on create — agents could set any status on existing ads by editing | [Agency AdResource](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/app/Filament/Agency/Resources/Ads/AdResource.php#L116) | Status bypass |

---

## 🟢 Low Findings

| ID | Finding | File |
|---|---|---|
| FL-1 | `'Apperçu'` typo (should be `'Aperçu'`) in Admin and Bailleur infolist sections | Ad resources |
| FL-2 | Navigation badge tooltip in English ("The number of ads") while rest of UI is French | Admin AdResource, UserResource |
| FL-3 | `UserType` filter has case mismatch: `'Agency'` vs enum value `'agency'` | [UserResource.php](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/app/Filament/Admin/Resources/Users/UserResource.php#L211) |
| FL-4 | No `->sidebarCollapsibleOnDesktop()` on Agency/Bailleur panels (inconsistent with Admin) | Panel providers |

---

## ✅ Positive Findings

| Finding | Details |
|---|---|
| Panel access control | `canAccessPanel()` correctly restricts by role + type |
| Admin MFA | Required with App + Email authentication + recovery codes |
| CSRF protection | `VerifyCsrfToken` middleware in all panel stacks |
| Session authentication | `AuthenticateSession` middleware prevents session fixation |
| Data scoping | Agency uses Filament tenant + `auth()->id()` filter; Bailleur uses `auth()->id()` filter |
| Email verification | Enabled on all three panels |
| Password reset | Available on all three panels |

---

## Remediation Plan

### 🚨 Quick Wins (< 1 hour each)

| # | Finding | Action |
|---|---|---|
| 1 | FC-1 | Add `event.origin` check + type validation in `mobile-bridge.blade.php` |
| 2 | FH-2 | Remove `preserveFilenames()` from avatar uploads |
| 3 | FH-4 | Add `->maxSize(5120)->acceptedFileTypes([...])` to all file uploads |
| 4 | FC-2 | Make `email_verified_at` read-only in UserResource |
| 5 | FL-1–FL-3 | Fix typos, badge tooltips, and enum case mismatch |

### 📋 Short-Term (1–2 weeks)

| # | Finding | Action |
|---|---|---|
| 6 | FH-1 | Add MFA (email-based) to Agency and Bailleur panels |
| 7 | FC-3 | Move inline scripts to external JS loaded via `->assets()` |
| 8 | FH-3 | Wrap `CustomRegister::handleRegistration` in DB transaction |
| 9 | FC-2 | Add confirmation dialog + audit log for role changes |
| 10 | FM-6 | Restrict `status` options based on current status (state machine) |

### 🔧 Mid-Term (1–2 months)

| # | Finding | Action |
|---|---|---|
| 11 | FM-1 | Add `->tenant()` to Bailleur panel for proper isolation |
| 12 | FM-2 | Extract shared resource traits (AdResource, PaymentResource, ReviewResource) |
| 13 | FM-3 | Explicitly disable global search on Agency/Bailleur panels |
| 14 | FM-4 | Cache navigation badge counts with short TTL |

### 🏗️ Long-Term (3–6 months)

| # | Action |
|---|---|
| 15 | Implement per-resource Filament policies (Shield or custom) |
| 16 | Add audit logging for all CRUD operations |
| 17 | Implement CSP nonces for Filament panels |
