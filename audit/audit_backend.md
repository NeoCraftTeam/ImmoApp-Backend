# ImmoApp SaaS — End-to-End Audit Report

## Executive Summary

This report is the result of a thorough audit of the **ImmoApp (KeyHome)** SaaS backend. The application is a Laravel 12 real-estate platform with PostgreSQL/PostGIS, FedaPay payment processing, Sanctum API auth, Filament admin panels, and a Next.js frontend.

**5 Critical**, **7 High**, **10 Medium**, and **8 Low** severity findings were identified. The most urgent issues involve **privilege escalation**, **broken authorization policies**, and **information disclosure** that could be exploited immediately in a production environment.

---

## 🔴 Critical Findings

### C-1. Privilege Escalation — Default Role `'admin'` in Registration

| Attribute | Value |
|---|---|
| **File** | [AuthController.php](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/app/Http/Controllers/Api/V1/AuthController.php#L270) |
| **Severity** | 🔴 Critical |
| **CVSS Est.** | 9.8 |

**Evidence:** Line 270 of `registerUser()`:
```php
'role' => $data['role'] ?? 'admin', // Valeur par défaut
```
If the `role` key is missing from `$data`, the user is created as an **admin**. Because `RegisterRequest` marks `role` as `'nullable'`, any registration where role is omitted silently creates an admin account.

**Impact:** Any unauthenticated user could register with admin privileges by simply not sending the `role` field in the `registerCustomer` endpoint — the `registerCustomer` method sets `$data['role'] = 'customer'`, but `registerUser` then does `array_merge($request->validated(), $data)`. If the validated data also contains a `role` field, the merge order could override the caller's intent.

**Remediation:**
```diff
-'role' => $data['role'] ?? 'admin',
+'role' => $data['role'] ?? 'customer',
```
And remove `'admin'` from the `RegisterRequest` allowed values:
```diff
-'role' => 'nullable|string|in:customer,admin,agent',
+'role' => 'nullable|string|in:customer,agent',
```

---

### C-2. Broken Authorization — Agents Can Delete ANY User

| Attribute | Value |
|---|---|
| **File** | [UserPolicy.php](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/app/Policies/UserPolicy.php#L52-L59) |
| **Severity** | 🔴 Critical |
| **CVSS Est.** | 9.1 |

**Evidence:**
```php
public function delete(User $user, User $model): bool
{
    if ($user->isAdmin()) {
        return true;
    }
    return $user->isAgent() && $user->id !== $model->id;
}
```
Any agent can delete **any other user** in the system — including admins, other agents, and customers — as long as it's not themselves.

**Same flaw for `forceDelete()` and `restore()`** — agents can permanently delete or restore ANY user.

**Impact:** An agent could delete admin accounts, other agent accounts, or perform denial-of-service by mass-deleting users.

**Remediation:**
```diff
 public function delete(User $user, User $model): bool
 {
     if ($user->isAdmin()) {
         return true;
     }
-    return $user->isAgent() && $user->id !== $model->id;
+    // Agents should only manage users within their own agency
+    return false;
 }

 public function forceDelete(User $user, User $model): bool
 {
-    return $user->isAgent() && $user->id !== $model->id;
+    return $user->isAdmin();
 }

 public function restore(User $user, User $model): bool
 {
-    return $user->isAgent() && $user->id !== $model->id;
+    return $user->isAdmin();
 }
```

---

### C-3. Mass-Assignment — Ad Owner Takeover via `user_id`

| Attribute | Value |
|---|---|
| **Files** | [AdRequest.php](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/app/Http/Requests/AdRequest.php#L76-L117), [AdController.php](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/app/Http/Controllers/Api/V1/AdController.php#L574) |
| **Severity** | 🔴 Critical |
| **CVSS Est.** | 8.7 |

**Evidence:** `AdRequest` validates `user_id` as `'required|exists:users,id'` on POST and `'sometimes|exists:users,id'` on PUT. The `update()` method then does:
```php
$ad->update($data); // $data includes user_id from validated()
```
An authenticated user who owns an ad can transfer ownership to any user by sending `user_id` in an update request. Or worse — since the `AdPolicy::update()` only checks `$user->id === $ad->user_id`, once ownership is transferred, the original owner loses access.

On creation, `$data['user_id'] ?? auth()->id()` allows a user to create ads attributed to **other users**.

**Remediation:**
- Remove `user_id` from validated data before mass-assignment in `update()`
- Force `user_id = auth()->id()` on creation, ignore client-submitted values
- Remove `user_id` from `AdRequest` PUT/PATCH rules entirely

---

### C-4. Information Disclosure — Error Messages Leaked to Clients

| Attribute | Value |
|---|---|
| **Files** | Multiple controllers |
| **Severity** | 🔴 Critical |
| **CVSS Est.** | 7.5 |

**Evidence:** Several controllers expose `$e->getMessage()` directly to clients without checking `APP_DEBUG`:

| File | Line | Code |
|---|---|---|
| [UserController.php](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/app/Http/Controllers/Api/V1/UserController.php#L343) | 343 | `'error' => $e->getMessage()` |
| [UserController.php](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/app/Http/Controllers/Api/V1/UserController.php#L631) | 631 | `'error' => $e->getMessage()` |
| [UserController.php](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/app/Http/Controllers/Api/V1/UserController.php#L751) | 751 | `'error' => $e->getMessage()` |

Even where `config('app.debug')` IS checked (e.g., `AdController` lines 332, 638), the check is inconsistent across the codebase. The comments in `UserController` say "optionnel, à cacher en prod" but the code still leaks.

**Impact:** Stack traces, database column names, SQL errors, and internal paths are exposed to attackers.

**Remediation:** Replace all instances with:
```php
'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred.',
```

---

### C-5. `RegisterRequest` Allows `role: admin` in Public Registration

| Attribute | Value |
|---|---|
| **File** | [RegisterRequest.php](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/app/Http/Requests/RegisterRequest.php#L42) |
| **Severity** | 🔴 Critical |
| **CVSS Est.** | 9.0 |

**Evidence:**
```php
'role' => 'nullable|string|in:customer,admin,agent',
```
The `RegisterRequest` accepts `admin` as a valid role, and since it's used by the public `registerCustomer` and `registerAgent` endpoints, a malicious user could submit `role=admin` in the request body. Combined with the `array_merge` order in `registerUser()`, this could override the role set by the caller.

**Remediation:**
```diff
-'role' => 'nullable|string|in:customer,admin,agent',
+'role' => 'nullable|string|in:customer,agent',
```

---

## 🟠 High Findings

### H-1. AdPolicy — Admin Cannot Update Ads

| Attribute | Value |
|---|---|
| **File** | [AdPolicy.php](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/app/Policies/AdPolicy.php#L30-L33) |
| **Severity** | 🟠 High |

**Evidence:**
```php
public function update(User $user, Ad $ad): bool
{
    return $user->id === $ad->user_id;
}
```
Admins cannot update any ad — only the owner can. This is likely unintentional.

**Remediation:** Add admin override: `return $user->isAdmin() || $user->id === $ad->user_id;`

---

### H-2. Docker — Weak Default Credentials & Exposed Services

| Attribute | Value |
|---|---|
| **File** | [docker-compose.yml](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/docker-compose.yml#L75-L120) |
| **Severity** | 🟠 High |

**Evidence:**
- **PostgreSQL**: `POSTGRES_PASSWORD: ${DB_PASSWORD:-password}`
- **PgAdmin**: `PGADMIN_DEFAULT_PASSWORD: ${PGADMIN_PASSWORD:-admin}`, exposed on port `5050`
- **Meilisearch**: `MEILI_MASTER_KEY=${MEILISEARCH_KEY:-masterKey}`
- **Grafana**: `GF_SECURITY_ADMIN_PASSWORD=${GRAFANA_PASSWORD:-admin}`
- **Redis**: No authentication configured

**Impact:** If environment variables aren't set, services run with trivially-guessable passwords. PgAdmin on port 5050 gives direct database access.

**Remediation:**
- Remove fallback passwords, require explicit env vars
- Move `pgadmin` port behind Traefik with auth, or remove from production compose
- Add Redis `requirepass` configuration
- Use a separate `docker-compose.production.yml` without dev services

---

### H-3. No Token Revocation on Password Change/Reset

| Attribute | Value |
|---|---|
| **File** | [AuthController.php](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/app/Http/Controllers/Api/V1/AuthController.php) |
| **Severity** | 🟠 High |

**Evidence:** The `resetPassword` and `updatePassword` methods change the password but do NOT revoke existing API tokens. If an account is compromised, changing the password doesn't invalidate attacker sessions.

**Remediation:** Add `$user->tokens()->delete();` after password update, then issue a fresh token.

---

### H-4. Open Redirect in Email Verification Flow

| Attribute | Value |
|---|---|
| **File** | [web.php](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/routes/web.php#L25-L31) |
| **Severity** | 🟠 High |

**Evidence:**
```php
Route::get('/verify-email', function (Request $request) {
    if (!$request->has('verify_url')) {
        abort(400, 'Missing verify_url');
    }
    return redirect($request->query('verify_url'));
});
```
This route blindly redirects to any URL provided in `verify_url` — a classic open redirect vulnerability that can be used for phishing.

**Remediation:** Validate that `verify_url` is an allowed domain before redirecting.

---

### H-5. No Rate Limiting on Search/Autocomplete/Facets Endpoints

| Attribute | Value |
|---|---|
| **File** | [api.php](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/routes/api.php) |
| **Severity** | 🟠 High |

**Evidence:** Public endpoints like `/ads/search`, `/ads/autocomplete`, `/ads/facets`, and `/ads/nearby` have **no rate limiting**. These endpoints hit the database and Meilisearch directly.

**Impact:** Denial of service via automated scraping or flood attacks.

**Remediation:** Apply `throttle:60,1` (or a custom rate limiter) to public search endpoints.

---

### H-6. `.gitignore` Over-Broad `public/*` Rule

| Attribute | Value |
|---|---|
| **File** | [.gitignore](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/.gitignore#L32) |
| **Severity** | 🟠 High |

**Evidence:** Line 32: `public/*` — this ignores the **entire** public directory contents, including `index.php`, `robots.txt`, `.htaccess`, and the favicon. These are essential Laravel files that should be tracked.

**Impact:** Production deployments may be missing critical entry point files. New clones/deploys will fail without `public/index.php`.

**Remediation:**
```diff
-public/*
+public/storage
+public/hot
+public/build
```

---

### H-7. `SESSION_ENCRYPT=false` in Default Config

| Attribute | Value |
|---|---|
| **File** | [.env.example](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/.env.example#L33) |
| **Severity** | 🟠 High |

**Evidence:** `SESSION_ENCRYPT=false` — session data stored in the database is not encrypted. If the database is compromised, session tokens are immediately usable.

**Remediation:** Set `SESSION_ENCRYPT=true` in `.env.example` and production environments.

---

## 🟡 Medium Findings

### M-1. CORS `max_age = 0` Comment Misleading & Performance Hit

**File:** [cors.php](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/config/cors.php#L38)
**Evidence:** `'max_age' => 0, // 24 hours (improves performance)` — The comment says 24 hours but the value is 0, which means browsers re-send preflight OPTIONS requests on every cross-origin request.
**Fix:** Set to `86400` (24h) or `3600` (1h).

### M-2. `supports_credentials = false` Breaks Cookie-Based Auth

**File:** [cors.php](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/config/cors.php)
**Evidence:** If Sanctum SPA auth is used, `supports_credentials` must be `true`. Currently `false`, which prevents the frontend from sending cookies.
**Fix:** Set `'supports_credentials' => true` if using SPA authentication.

### M-3. Sanctum Token Expiration Too Long (7 Days)

**File:** [sanctum.php](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/config/sanctum.php)
**Evidence:** `'expiration' => 10080` (7 days) with no refresh mechanism. A stolen token remains valid for a week.
**Fix:** Reduce to 60-120 minutes and implement token refresh endpoint.

### M-4. `UserPolicy::view()` Has No Parameters — Allows Anyone to View Any User

**File:** [UserPolicy.php](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/app/Policies/UserPolicy.php#L22-L25)
**Evidence:**
```php
public function view(): bool
{
    return true;
}
```
This doesn't even receive the user being viewed — it unconditionally returns `true`, exposing all user profiles.

### M-5. Missing `is_active` Check on Payment/Ad Operations

There is no middleware or check to verify `is_active` status on API operations other than login. A deactivated user's existing tokens still work.

### M-6. `ad->delete()` Is Hard Delete, Not Soft Delete

**File:** [AdController.php](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/app/Http/Controllers/Api/V1/AdController.php#L776)
**Evidence:** Despite having `softDeletes()` on the `ad` migration, `$ad->delete()` will soft-delete but the response message says "définitivement supprimée."
**Note:** This isn't a bug per se, but the endpoint is documented as a permanent deletion.

### M-7. No Image Virus/Malware Scanning on Upload

**Files:** `AdController`, `AuthController`, `UserController`
**Evidence:** Images are uploaded and stored directly via Spatie Media Library with only MIME type validation. No ClamAV or similar scanning.

### M-8. `password_reset_tokens` Table Has No Expiration Column

**File:** [create_user.php](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/database/migrations/2025_08_17_060328_create_user.php#L31-L35)
**Evidence:** Only `email`, `token`, and `created_at` are stored. Token expiration is handled at the application level via config, but if the config is misconfigured, tokens never expire.

### M-9. CSP Allows `unsafe-eval` and `unsafe-inline`

**File:** [next.config.ts](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/keyhome-frontend-next/next.config.ts#L61)
**Evidence:** `script-src 'self' 'unsafe-eval' 'unsafe-inline'` — defeats much of CSP's XSS protection value.
**Fix:** Use nonce-based CSP or strict-dynamic where possible.

### M-10. FedaPay Webhook Has No Replay Protection

**File:** [PaymentController.php](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/app/Http/Controllers/Api/V1/PaymentController.php)
**Evidence:** The webhook verifies the HMAC signature but doesn't check for replay attacks (no timestamp validation, no idempotency key tracking). A captured valid webhook could be replayed.

---

## 🟢 Low Findings

| ID | Finding | File |
|---|---|---|
| L-1 | User migration `down()` drops `'user'` table but creates `'users'` table | [create_user.php](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/database/migrations/2025_08_17_060328_create_user.php#L52) |
| L-2 | Redundant individual `images.0` through `images.9` rules in `AdRequest` | [AdRequest.php](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/app/Http/Requests/AdRequest.php#L90-L99) |
| L-3 | `ads_nearby()` accepts `?int $user` but UUIDs are used for user IDs | [AdController.php](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/app/Http/Controllers/Api/V1/AdController.php#L941) |
| L-4 | `AdPolicy::adsNearby()` excludes agents from nearby search | [AdPolicy.php](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/app/Policies/AdPolicy.php#L54-L62) |
| L-5 | Logs record sensitive data (email, IP, user_agent) with no rotation policy | Multiple controllers |
| L-6 | No DB index on `users.email` for login lookups (only unique constraint) | [create_user.php](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/database/migrations/2025_08_17_060328_create_user.php) |
| L-7 | `UserController::show()` calls `authorize('view', User::class)` instead of `authorize('view', $userId)` — policy never receives the target user | [UserController.php](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/app/Http/Controllers/Api/V1/UserController.php#L424) |
| L-8 | `city_id` foreign key uses `onDelete('cascade')` — deleting a city deletes all users in it | [create_user.php](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/database/migrations/2025_08_17_060328_create_user.php#L25) |

---

## Prioritized Remediation Plan

### 🚨 Quick Wins (Do Today — < 1 hour each)

| Priority | Finding | Action | Est. Time |
|---|---|---|---|
| 1 | C-1 | Change default role from `'admin'` to `'customer'` | 5 min |
| 2 | C-5 | Remove `admin` from `RegisterRequest` allowed roles | 5 min |
| 3 | C-4 | Wrap all `$e->getMessage()` with `config('app.debug')` check | 20 min |
| 4 | C-2 | Fix `UserPolicy` delete/forceDelete/restore to admin-only | 10 min |
| 5 | H-4 | Validate `verify_url` domain in web.php redirect route | 10 min |
| 6 | H-7 | Set `SESSION_ENCRYPT=true` | 2 min |
| 7 | M-1 | Fix CORS `max_age` to `86400` | 2 min |

### 📋 Short-Term (This Sprint — 1-2 weeks)

| Priority | Finding | Action | Est. Time |
|---|---|---|---|
| 8 | C-3 | Remove `user_id` from AdRequest PUT rules and force `auth()->id()` on create | 30 min |
| 9 | H-1 | Add admin override to `AdPolicy::update()` | 10 min |
| 10 | H-3 | Add `$user->tokens()->delete()` on password change/reset | 20 min |
| 11 | H-5 | Add rate limiting to search/autocomplete/facets/nearby endpoints | 30 min |
| 12 | H-6 | Fix `.gitignore` to not exclude entire `public/` directory | 10 min |
| 13 | M-4 | Fix `UserPolicy::view()` to receive and check the target user | 15 min |
| 14 | M-5 | Add `is_active` middleware check for all authenticated routes | 30 min |
| 15 | L-1 | Fix migration `down()` to drop `'users'` not `'user'` | 5 min |
| 16 | L-3 | Change `ads_nearby()` parameter type from `?int` to `?string` | 5 min |
| 17 | L-7 | Fix `UserController::show()` to pass model instance to authorize | 10 min |

### 🔧 Mid-Term (1-2 Months)

| Priority | Finding | Action |
|---|---|---|
| 18 | H-2 | Create separate `docker-compose.prod.yml`, remove dev services, require explicit env vars |
| 19 | M-3 | Reduce Sanctum token expiration and implement refresh-token flow |
| 20 | M-10 | Add webhook replay protection (timestamp validation + idempotency keys) |
| 21 | M-2 | Audit and correctly configure `supports_credentials` for SPA auth |
| 22 | M-9 | Replace `'unsafe-eval'` and `'unsafe-inline'` in CSP with nonce-based approach |
| 23 | L-8 | Change city FK to `onDelete('set null')` instead of cascade |
| 24 | — | Write comprehensive authorization tests for all policies |
| 25 | — | Expand security test coverage (role escalation, mass assignment, IDOR) |

### 🏗️ Long-Term (3-6 Months)

| Priority | Finding | Action |
|---|---|---|
| 26 | M-7 | Integrate ClamAV or similar for upload scanning |
| 27 | — | Implement API versioning strategy with deprecation policy |
| 28 | — | Add audit logging for all admin/destructive operations |
| 29 | — | Implement GDPR data export/deletion self-service |
| 30 | — | Set up WAF (Web Application Firewall) rules |
| 31 | — | Penetration test by external security firm |
| 32 | — | Add Redis authentication and TLS for inter-service communication |
| 33 | L-5 | Implement structured logging with PII masking and log rotation |

---

## Verification Plan

### Automated Tests (existing)

The existing test suite (`tests/Feature/`) currently has 11 test files. Run with:
```bash
cd /Users/feze/Developer/Laravel/ImmoApp-Backend
php artisan test
```

Existing security-relevant tests:
- `CriticalSecurityTest.php` — admin registration guards, webhook signature validation
- `SecurityTest.php` — login rate limiting (minimal)

### New Tests Needed (after remediation)

After fixing the critical/high issues, the following tests should be added to `tests/Feature/CriticalSecurityTest.php`:

1. **Role Escalation Test**: Verify `registerCustomer` with `role=admin` in body still creates a customer
2. **Agent Delete Test**: Verify agent cannot delete another user (should get 403)
3. **Mass-Assignment Test**: Verify ad update with `user_id` field doesn't change ownership
4. **Error Leak Test**: Verify 500 responses don't contain stack traces when `APP_DEBUG=false`
5. **Open Redirect Test**: Verify `/verify-email?verify_url=https://evil.com` is rejected

### Manual Verification

After implementing quick wins, verify in the test environment:
1. Register a customer without sending `role` → confirm the user has `role=customer`, not `admin`
2. Log in as an agent → try `DELETE /api/v1/users/{admin-id}` → confirm 403
3. Create an ad → try `PUT /api/v1/ads/{id}` with `user_id=<other-user>` → confirm ownership unchanged

> [!IMPORTANT]
> **Findings C-1 through C-5 should be fixed before the next production deployment.** These represent active privilege escalation vectors.
