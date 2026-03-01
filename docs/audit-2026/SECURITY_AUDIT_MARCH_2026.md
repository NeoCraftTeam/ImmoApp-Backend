# Comprehensive Security & Best Practices Audit Report

**Application**: KeyHome ImmoApp Backend  
**Stack**: Laravel 12 / PHP 8.4 / Filament 4 / PostgreSQL 15 / Redis / Meilisearch / Docker  
**Date**: 1 March 2026  
**Branch**: `feature/points-system`

---

## Table of Contents

- [Executive Summary](#executive-summary)
- [Section 1: Audit Findings](#section-1-audit-findings)
  - [Critical](#critical)
  - [High](#high)
  - [Medium](#medium)
  - [Low](#low)
  - [Informational](#informational)
- [Section 2: Recommendations](#section-2-recommendations)

---

## Executive Summary

The application has a **solid security foundation** — MFA on all panels, HMAC webhook verification with replay protection, pessimistic locking on payments, proper CSRF/Sanctum configuration, and good policy coverage for core models.

The **critical findings** center on mass assignment over-exposure in `$fillable` arrays (User, Payment, Ad models), missing Nginx hardening (security headers, PHP execution blocking in storage), and unencrypted sessions.

The **high-priority items** around token expiration, race conditions in point crediting, and Telescope production exposure should be addressed promptly.

**Positive security patterns observed:**
- MFA (App + Email) on all 3 Filament panels (required for Admin)
- Webhook HMAC signature verification with replay protection (±300s)
- Payment idempotency via `lockForUpdate()` + terminal state checks
- `EnsureUserIsActive` middleware on all API routes
- `canAccessPanel()` role-gating on all panels
- CSRF protection on all non-API routes
- Sentry PII disabled by default
- Proper `$hidden` arrays on all models
- `APP_DEBUG=false` default
- `composer audit`: zero known CVEs

---

## Section 1: Audit Findings

### Critical

---

#### SEC-001 | Critical | Security — Mass Assignment

**Title**: User model `$fillable` includes privilege-escalating fields

**Description**: `app/Models/User.php` includes `role`, `point_balance`, `is_active`, and `email_verified_at` in `$fillable`. Any controller accepting unguarded input (e.g. `$request->all()`, `$request->validated()` from a permissive FormRequest) could allow a user to escalate to ADMIN, grant themselves unlimited credits, self-activate a deactivated account, or bypass email verification.

**Impact**: Full privilege escalation, financial fraud, authentication bypass.

**Evidence**: `$fillable` array in User model — `role`, `point_balance`, `is_active`, `email_verified_at`.

---

#### SEC-002 | Critical | Security — Mass Assignment

**Title**: Payment model `$fillable` includes `status`, `amount`, `transaction_id`

**Description**: `app/Models/Payment.php` has `status` in `$fillable`. If any code path mass-assigns user input, an attacker could mark their own payment as `SUCCESS` without actually paying.

**Impact**: Financial fraud — free unlocks, free subscriptions, arbitrary payment status manipulation.

**Evidence**: Payment model `$fillable` array.

---

#### SEC-003 | Critical | Infrastructure — PHP Execution in Storage

**Title**: Nginx allows PHP execution in `/storage/` directory

**Description**: `.docker/nginx/conf.d/default.conf` serves files from `/storage/` via an `alias` directive but does **not** block `.php` file execution. The global `location ~ \.php$` block will match requests to `/storage/malicious.php` and execute them via PHP-FPM.

**Impact**: Remote Code Execution — an attacker who manages to upload a PHP file (via any file upload vulnerability) gains full server access.

**Evidence**: `default.conf` L27-30 — storage location has no `.php` deny rule.

---

#### SEC-004 | Critical | Infrastructure — Missing Security Headers

**Title**: Nginx configuration missing all HTTP security headers

**Description**: No `X-Frame-Options`, `X-Content-Type-Options`, `Content-Security-Policy`, `Strict-Transport-Security`, `Referrer-Policy`, or `Permissions-Policy` headers are set.

**Impact**: Clickjacking, MIME-sniffing attacks, XSS amplification, no HSTS enforcement, referer leakage.

**Evidence**: `.docker/nginx/conf.d/default.conf` — no `add_header` directives.

---

#### SEC-005 | Critical | Security — Session Encryption

**Title**: Session data stored unencrypted in database

**Description**: `SESSION_ENCRYPT=false` in environment examples and `config/session.php` defaults to `false`. All session data (auth tokens, CSRF tokens, flash data) is stored as plaintext in the `sessions` table.

**Impact**: If the database is compromised, all active sessions are exposed. Combined with no DB SSL (SEC-010), this is a significant data exposure risk.

**Evidence**: `config/session.php` — `'encrypt' => env('SESSION_ENCRYPT', false)`.

---

### High

---

#### SEC-006 | High | Security — Mass Assignment

**Title**: Ad model `$fillable` includes `status`, `is_boosted`, `boost_score`, `boost_expires_at`

**Description**: `app/Models/Ad.php` allows mass-assigning ad status (bypassing moderation workflow PENDING→AVAILABLE) and boost fields (free ad promotion without payment).

**Impact**: Bypassing content moderation, free ad boosting causes financial loss and unfair marketplace advantage.

**Evidence**: Ad model `$fillable` array.

---

#### SEC-007 | High | Security — Race Condition

**Title**: `PointService::credit()` lacks row-level locking

**Description**: `app/Services/PointService.php` `credit()` method does not use `lockForUpdate()` before incrementing `point_balance`, unlike `deduct()` which correctly uses pessimistic locking. Rapid concurrent requests could credit points multiple times.

**Impact**: Financial fraud — duplicate point crediting from a single payment.

**Evidence**: `PointService::credit()` method vs `PointService::deduct()` method.

---

#### SEC-008 | High | Security — Token Expiration

**Title**: Registration tokens have no expiration

**Description**: `AuthController.php` L296 creates Sanctum tokens at registration without an expiration date, while login tokens correctly expire in 7 days. A registration token persists indefinitely.

**Impact**: Compromised registration tokens remain valid forever, providing persistent unauthorized access.

**Evidence**: `AuthController.php` L296 — `createToken()` with no `expiresAt` argument.

---

#### SEC-009 | High | Infrastructure — Telescope in Production

**Title**: Telescope enabled by default and in production dependencies

**Description**: `laravel/telescope` is in `require` (not `require-dev`) and `TELESCOPE_ENABLED` defaults to `true`. If the env var is missing in production, Telescope records all requests, queries, exceptions, and mail content. Only a single hardcoded email gate protects the dashboard.

**Impact**: Full application introspection including SQL queries, request payloads, mail content, and exception stack traces exposed to anyone who bypasses the email gate.

**Evidence**: `composer.json` `require` section; `config/telescope.php` L9 — `'enabled' => env('TELESCOPE_ENABLED', true)`.

---

#### SEC-010 | High | Security — Database Connection

**Title**: PostgreSQL SSL mode set to `prefer` instead of `require`

**Description**: `config/database.php` sets `'sslmode' => 'prefer'` which falls back to unencrypted connections if SSL negotiation fails. In the Docker setup, DB traffic traverses the bridge network unencrypted.

**Impact**: Database credentials and query data transmitted in plaintext within the container network — susceptible to network sniffing.

**Evidence**: `config/database.php` PostgreSQL connection `sslmode`.

---

#### SEC-011 | High | Security — Password Hashing

**Title**: User password cast is `string` instead of `hashed`

**Description**: `app/Models/User.php` casts `password` as `'string'`. Laravel 10+ supports `'hashed'` cast which auto-hashes on assignment. Currently, every code path must manually call `Hash::make()` — if any path misses it, plaintext passwords get stored.

**Impact**: Plaintext password storage if any code path misses manual hashing.

**Evidence**: User model `casts()` method — `'password' => 'string'`.

---

#### SEC-012 | High | Security — Bcrypt Rounds

**Title**: Example environment uses BCRYPT_ROUNDS=4

**Description**: `.env.example` sets `BCRYPT_ROUNDS=4`. If copied to production without modification, passwords are hashed with only 4 rounds — trivially brute-forceable (microseconds per attempt vs. ~250ms for the recommended 12 rounds).

**Impact**: Mass password cracking if the database is breached.

**Evidence**: `.env.example` `BCRYPT_ROUNDS=4`.

---

#### SEC-013 | High | Security — Missing Policies

**Title**: Subscription, SubscriptionPlan, PointTransaction, and ActivityLog have no model policies

**Description**: These admin-only models rely solely on the panel-level `canAccessPanel()` gate. If any future API endpoint or Filament action exposes these models, there is no defense-in-depth authorization.

**Impact**: Unauthorized CRUD operations if a new route or action bypasses panel protection.

**Evidence**: No policy files for `Subscription`, `SubscriptionPlan`, `PointTransaction`, `Activity` in `app/Policies/`.

---

### Medium

---

#### SEC-014 | Medium | Security — Rate Limiting

**Title**: Multiple endpoints missing rate limiting

**Description**: Several sensitive endpoints have no `throttle` middleware:
- `POST /payments/verify/{ad}` — payment verification polling
- `POST /payments/webhook` — external webhook (DDoS vector)
- `GET /payments/callback` — payment callback
- `POST /subscriptions/cancel` — subscription cancellation
- `POST /credits/verify-purchase` — credit verification
- `GET/POST auth/oauth/{provider}/redirect|callback` — OAuth flows

**Impact**: Brute-force attacks, resource exhaustion, webhook abuse.

**Evidence**: `routes/api.php` — missing `throttle` middleware on listed routes.

---

#### SEC-015 | Medium | Security — Input Validation

**Title**: 11+ controller methods use inline validation instead of FormRequest classes

**Description**: Multiple methods in `AuthController`, `SocialAuthController`, `ReviewController`, `AdController`, `CreditController` use `$request->validate()` or `request()->validate()` inline. Two existing FormRequests (`ReviewRequest`, `PaymentRequest`) are created but unused.

**Impact**: Inconsistent validation, harder to audit and maintain, potential for validation bypass in complex methods.

**Evidence**: AuthController L775, L1345, L1391, L1459; ReviewController L96; CreditController L124, L206.

---

#### SEC-016 | Medium | Security — Account Enumeration

**Title**: Registration endpoint reveals email existence

**Description**: `AuthController` L272 returns `409 "Cette adresse email est déjà utilisée"` when a duplicate email is used at registration, allowing attackers to enumerate registered accounts.

**Impact**: Account enumeration enables targeted phishing and credential stuffing attacks.

**Evidence**: `AuthController.php` L272.

---

#### SEC-017 | Medium | Security — Open Redirect

**Title**: `/verify-email` callback has potential URL parsing bypass

**Description**: `routes/web.php` L30-46 validates redirect targets using `parse_url()` against a host allowlist. However, `parse_url()` can be fooled by crafted URLs (e.g., `//evil.com\@keyhome.neocraft.dev`).

**Impact**: Open redirect for phishing attacks.

**Evidence**: `routes/web.php` L30-46 — `parse_url($verifyUrl, PHP_URL_HOST)`.

---

#### SEC-018 | Medium | Security — SSO Session

**Title**: Panel SSO creates permanent session with `remember: true`

**Description**: The `PanelSsoController` logs users in with `remember: true`, creating a long-lived "remember me" session from a one-time 60-second signed URL. No rate limiting on the SSO endpoint.

**Impact**: A leaked signed URL could establish a persistent session.

**Evidence**: `routes/web.php` L17 — PanelSsoController reference.

---

#### SEC-019 | Medium | Infrastructure — Upload Size Mismatch

**Title**: Nginx allows 100MB uploads while application limits to 10MB

**Description**: `.docker/nginx/conf.d/default.conf` sets `client_max_body_size 100M` while Spatie Media Library is configured for 10MB max. The 90MB gap allows large payloads to reach PHP-FPM, consuming memory and disk before being rejected.

**Impact**: Denial of service through memory/disk exhaustion.

**Evidence**: Nginx `100M` vs `config/media-library.php` `10MB`.

---

#### SEC-020 | Medium | Security — Webhook Payload

**Title**: `$request->all()` used in payment webhook handler

**Description**: `PaymentController` L205 ingests the entire webhook payload unfiltered. While used read-only currently, this pattern risks processing unexpected fields.

**Impact**: Potential for injection of unexpected data into application logic.

**Evidence**: `PaymentController.php` L205.

---

#### SEC-021 | Medium | Security — Secure Cookie

**Title**: `SESSION_SECURE_COOKIE` has no default value

**Description**: `config/session.php` `'secure' => env('SESSION_SECURE_COOKIE')` with no fallback. If the env var is missing, cookies are sent over HTTP.

**Impact**: Session cookies transmitted in plaintext, susceptible to interception.

**Evidence**: `config/session.php` L172.

---

#### SEC-022 | Medium | Security — Redis Transport

**Title**: Redis connections not configured for TLS

**Description**: `config/database.php` Redis configuration uses no TLS scheme. If Redis is on a remote host, data including cached sessions and queued jobs traverses in plaintext.

**Impact**: Cache/queue data interception on untrusted networks.

**Evidence**: `config/database.php` Redis connection section.

---

#### SEC-023 | Medium | Data Integrity — Bailleur Tenant Scoping

**Title**: Bailleur panel resources lack `$tenantOwnershipRelationshipName`

**Description**: Unlike Agency panel resources, Bailleur resources don't set Filament's `$tenantOwnershipRelationshipName`. They rely solely on `LandlordScope`. If the scope is accidentally removed, all data leaks across bailleur accounts.

**Impact**: Data leak between individual landlord accounts if global scope fails.

**Evidence**: Bailleur `AdResource`, `PaymentResource` — no `$tenantOwnershipRelationshipName`.

---

#### PERF-001 | Medium | Performance

**Title**: Nginx rate limiting not configured

**Description**: No `limit_req` or `limit_conn` directives in Nginx. Application-level throttle middleware can be bypassed by bot floods that overwhelm PHP-FPM before reaching Laravel.

**Impact**: DDoS vulnerability — service degradation under flood.

**Evidence**: `.docker/nginx/conf.d/default.conf` — no rate limit directives.

---

### Low

---

#### SEC-024 | Low | Security — Dependency Management

**Title**: Wildcard version constraints and missing security meta-package

**Description**: `flowframe/laravel-trend: *` and `laravel/pulse: *` in `composer.json` use unconstrained versions. `roave/security-advisories` is not installed.

**Impact**: Breaking changes or malicious packages could be pulled. Known CVEs not blocked at install time.

**Evidence**: `composer.json` `require` section.

---

#### SEC-025 | Low | Security — Telescope Data Exposure

**Title**: Telescope only hides `_token` from request parameters

**Description**: `TelescopeServiceProvider.php` only hides `_token`. Passwords, API keys, and other sensitive POST fields are recorded in Telescope entries.

**Impact**: Sensitive data stored in Telescope's database tables, accessible to anyone with dashboard access.

**Evidence**: `Telescope::hideRequestParameters(['_token'])`.

---

#### SEC-026 | Low | Configuration

**Title**: Mail defaults to `log` driver with no SMTP TLS enforcement

**Description**: `config/mail.php` default mailer is `log`. If production `.env` mis-sets `MAIL_MAILER`, all emails (password resets, verification links) go to log files instead of being delivered. Also, no enforced TLS for SMTP transport.

**Impact**: Silent email delivery failure; potential plaintext SMTP credentials in transit.

**Evidence**: `config/mail.php`.

---

#### MAINT-001 | Low | Maintainability

**Title**: Abandoned dependency `doctrine/annotations`

**Description**: `composer audit` reports `doctrine/annotations` as abandoned with no suggested replacement.

**Impact**: No security patches for abandoned package.

**Evidence**: `composer audit` output.

---

### Informational

---

#### INFO-001 | Informational | Security

**Title**: `composer audit` reports zero known vulnerabilities

**Description**: No known CVEs in current dependency lockfile as of audit date.

**Evidence**: `composer audit` output — "No security vulnerability advisories found."

---

#### INFO-002 | Informational | Security

**Title**: Strong foundational security patterns observed

**Description**: Several positive security practices are in place:
- MFA (App + Email) on all 3 Filament panels (required for Admin)
- Webhook HMAC signature verification with replay protection
- Payment idempotency via `lockForUpdate()` + terminal state checks
- `EnsureUserIsActive` middleware on all API routes
- `canAccessPanel()` role-gating on all panels
- CSRF protection on all non-API routes
- Sentry PII disabled by default
- Proper `$hidden` arrays on all models
- `APP_DEBUG=false` default

---

## Section 2: Recommendations

### Critical Priority

| Ref | Category | Recommendation | Rationale | References |
|-----|----------|---------------|-----------|------------|
| SEC-001 | Security Enhancement | Remove `role`, `point_balance`, `is_active`, `email_verified_at`, login metadata, and timestamps from User `$fillable`. Set them explicitly in service methods (e.g., `$user->role = UserRole::ADMIN`). | Prevents privilege escalation and financial fraud via mass assignment. | [OWASP Mass Assignment](https://cheatsheetseries.owasp.org/cheatsheets/Mass_Assignment_Cheat_Sheet.html) |
| SEC-002 | Security Enhancement | Remove `status`, `amount`, `transaction_id` from Payment `$fillable`. Set payment status only via service methods or observers after verification. | Prevents fraudulent payment status manipulation. | Laravel docs: Mass Assignment |
| SEC-003 | Infrastructure | Add to Nginx `/storage/` block: `location ~* \.php$ { deny all; return 403; }` nested inside the storage location, or add a global rule to deny PHP execution outside `index.php`. | Prevents Remote Code Execution via uploaded PHP files. | [OWASP File Upload](https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html) |
| SEC-004 | Infrastructure | Add security headers to Nginx: `X-Frame-Options DENY`, `X-Content-Type-Options nosniff`, `X-XSS-Protection "1; mode=block"`, `Referrer-Policy strict-origin-when-cross-origin`, `Content-Security-Policy`, `Strict-Transport-Security "max-age=31536000; includeSubDomains"`, `Permissions-Policy`. | Mitigates clickjacking, XSS, MIME-sniffing, and enforces HSTS. | [OWASP Secure Headers](https://cheatsheetseries.owasp.org/cheatsheets/HTTP_Headers_Cheat_Sheet.html) |
| SEC-005 | Configuration | Set `SESSION_ENCRYPT=true` in production `.env`. Ensure `APP_KEY` is set. | Protects session data at rest in the database. | Laravel docs: Session Configuration |

### High Priority

| Ref | Category | Recommendation | Rationale | References |
|-----|----------|---------------|-----------|------------|
| SEC-006 | Security Enhancement | Remove `status`, `is_boosted`, `boost_score`, `boost_expires_at` from Ad `$fillable`. Manage via `AdStatus::transitionTo()` and `AdBoostService` only. | Prevents moderation bypass and fraudulent ad boosting. | OWASP Mass Assignment |
| SEC-007 | Security Enhancement | Add `lockForUpdate()` to `PointService::credit()` identical to `deduct()`: query `User::query()->lockForUpdate()->findOrFail($user->id)` before incrementing. | Prevents race-condition double-crediting. | [OWASP Race Conditions](https://owasp.org/www-community/attacks/Race_Conditions) |
| SEC-008 | Security Enhancement | Add 7-day expiration to registration tokens: `$user->createToken('...', ['*'], now()->addDays(7))`. | Limits exposure window for compromised tokens. | Laravel Sanctum docs |
| SEC-009 | Configuration | Move `laravel/telescope` to `require-dev`. Set `TELESCOPE_ENABLED=false` as default in config. Add role-based gate instead of hardcoded email. | Prevents production data exposure through debug tooling. | [Laravel Telescope docs](https://laravel.com/docs/telescope) |
| SEC-010 | Infrastructure | Set `'sslmode' => env('DB_SSLMODE', 'require')` in PostgreSQL config. | Encrypts database traffic. | PostgreSQL SSL docs |
| SEC-011 | Security Enhancement | Change User model password cast from `'string'` to `'hashed'`. | Defense-in-depth against plaintext password storage. | [Laravel Hashed Cast](https://laravel.com/docs/eloquent-mutators#hashed-casting) |
| SEC-012 | Configuration | Change `.env.example` to `BCRYPT_ROUNDS=12`. Add a deployment check that rejects `BCRYPT_ROUNDS < 10`. | Ensures production password hashing is brute-force resistant. | [OWASP Password Storage](https://cheatsheetseries.owasp.org/cheatsheets/Password_Storage_Cheat_Sheet.html) |
| SEC-013 | Security Enhancement | Create policies for `Subscription`, `SubscriptionPlan`, `PointTransaction`, and `Activity` models. | Defense-in-depth — policies enforce authorization regardless of access path. | Laravel Authorization docs |

### Medium Priority

| Ref | Category | Recommendation | Rationale | References |
|-----|----------|---------------|-----------|------------|
| SEC-014 | Security Enhancement | Add `throttle` middleware: `throttle:30,1` to payment verify/callback, `throttle:120,1` to webhooks, `throttle:5,1` to subscription cancel and credit verify-purchase. | Prevents brute-force and resource exhaustion. | [OWASP DoS Prevention](https://cheatsheetseries.owasp.org/cheatsheets/Denial_of_Service_Cheat_Sheet.html) |
| SEC-015 | Code Quality | Extract all inline `$request->validate()` calls into dedicated FormRequest classes. Wire up existing unused `ReviewRequest` and `PaymentRequest`. | Centralized validation, easier auditing. | Laravel Form Request docs |
| SEC-016 | Security Enhancement | Return a generic `422` for duplicate emails at registration, or document the accepted trade-off. | Prevents account enumeration. | [OWASP Authentication](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html) |
| SEC-017 | Security Enhancement | Replace `parse_url()` with `filter_var($verifyUrl, FILTER_VALIDATE_URL)` + strict host extraction. | Prevents URL parsing bypasses for open redirect. | [OWASP Redirects](https://cheatsheetseries.owasp.org/cheatsheets/Unvalidated_Redirects_and_Forwards_Cheat_Sheet.html) |
| SEC-018 | Security Enhancement | Change PanelSsoController to `remember: false`. Add `throttle:5,1` to the SSO route. | Limits session persistence from one-time links. | Laravel Authentication docs |
| SEC-019 | Infrastructure | Reduce Nginx `client_max_body_size` to `15M`. | Prevents memory/disk exhaustion attacks. | Nginx docs |
| SEC-020 | Code Quality | Replace `$request->all()` with `$request->only('name', 'entity')` in webhook handler. | Explicit field picking reduces attack surface. | OWASP Input Validation |
| SEC-021 | Configuration | Set `'secure' => env('SESSION_SECURE_COOKIE', true)` as default. | Ensures session cookies only sent over HTTPS. | [OWASP Session Management](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html) |
| SEC-022 | Infrastructure | Configure Redis TLS or ensure Redis is only accessible on Docker internal network. | Encrypts cache/queue traffic. | Redis TLS docs |
| SEC-023 | Data Integrity | Add `$tenantOwnershipRelationshipName` to Bailleur panel resources. | Defense-in-depth against scope removal. | Filament Multi-tenancy docs |
| PERF-001 | Infrastructure | Add Nginx rate limiting: `limit_req_zone` and `limit_req` directives. | Network-level DDoS mitigation. | Nginx rate limiting docs |

### Low Priority

| Ref | Category | Recommendation | Rationale | References |
|-----|----------|---------------|-----------|------------|
| SEC-024 | Dependency Mgmt | Pin `flowframe/laravel-trend` and `laravel/pulse` to specific versions. Add `roave/security-advisories`. | Prevents unexpected updates, blocks known CVEs. | [Roave Security Advisories](https://github.com/Roave/SecurityAdvisories) |
| SEC-025 | Configuration | Expand Telescope hidden parameters: `password`, `password_confirmation`, `current_password`, `new_password`, `secret_key`, `token`, `api_key`. | Prevents sensitive data recording. | Laravel Telescope docs |
| SEC-026 | Configuration | Set `MAIL_MAILER=smtp` and `MAIL_SCHEME=tls` explicitly in production. | Ensures email delivery and encrypted SMTP. | Laravel Mail docs |
| MAINT-001 | Dependency Mgmt | Monitor `doctrine/annotations` for removal. Check if parent packages have newer versions. | Abandoned packages receive no patches. | Composer docs |

---

## Severity Distribution

| Severity | Count |
|----------|-------|
| Critical | 5 |
| High | 8 |
| Medium | 11 |
| Low | 4 |
| Informational | 2 |
| **Total** | **30** |
