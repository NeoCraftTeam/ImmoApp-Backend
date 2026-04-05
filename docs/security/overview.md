# KeyHome — Security Overview

> **Last audited:** April 1, 2026
> **Current security score:** 92/100 (up from 88/100 — March 2026)
> **All P0/P1 findings from Enterprise Audit 2026: ✅ Fixed**

---

## Threat Model

KeyHome is a multi-tenant B2C/B2B SaaS platform handling:
- Financial transactions (Flutterwave XAF/XOF payments)
- PII (user identity, contact details, location data)
- Rental/property data with paywall-protected contact reveal

**Primary threats:**
1. Unauthorized access to paywalled contact details (revenue bypass)
2. Payment manipulation (price tampering, double-spend)
3. Privilege escalation (customer → agent → admin)
4. Data exfiltration via IDOR or broken authorization
5. Account takeover via credential stuffing / OTP brute-force

---

## Security Layers

### Authentication
- Clerk JWT (primary) — industry-standard OAuth with PKCE
- Sanctum Bearer tokens — scoped by role, prefixed (`kh_{role}_`)
- MFA (TOTP + email OTP) on all Filament admin panels
- Composite rate limiting on OTP: `user_id:ip` key (5/min)

### Authorization
- 16 Eloquent Policies (one per key model)
- `EnsureTokenMatchesRole` — Sanctum ability enforced per route
- `EnsureCorrectRoleForPanel` — panel-level role guard
- `RoleScopedSession` — isolates admin/agency session namespaces

### Input Security
- `SanitizeInput` middleware — denylist approach, all string inputs XSS-stripped except `EXEMPT_FIELDS`
- FormRequest validation on every mutation endpoint
- `exclude_ids` bounded to max 50 UUIDs (DoS prevention)
- File uploads: MIME type + size validation on all Filament resources

### API Security
- Rate limits: 60/min (guest) → 300/min (customer) → 500/min (agent w/ sub)
- Global JSON error envelope — no HTML 404/403 on API routes
- `error` key absent from all responses — no stack trace leakage
- CORS: explicit allowed methods/headers, tightened subdomain regex

### Payment Security
- Amount resolved server-side only — client amount ignored
- `lockForUpdate()` on Payment row — prevents double-spend race condition
- Flutterwave webhook signature verified on every callback
- Idempotency guard: webhook skipped if `payment.status !== 'pending'`

### Infrastructure Security
- `APP_DEBUG=false` enforced in production
- `TRUSTED_PROXIES` via `config/proxy.php` (not `env()` at boot)
- Health check endpoint requires admin Sanctum token
- Admin registration endpoint requires existing admin auth

---

## Known Open Items

| # | Issue | Priority | Plan |
|---|-------|----------|------|
| SEC-11 | CSP `unsafe-inline` + `unsafe-eval` in `SecurityHeaders` middleware | High | Nonce-based CSP — next sprint |
| SEC-12 | Frontend auth token in `localStorage` (XSS risk) | Medium | httpOnly cookie migration — planned |
| SEC-13 | Mobile SSL certificate pinning absent | Low | Next mobile release |

---

## Security Test Coverage

`tests/Feature/Security/SecurityTest.php` — 16 test cases:
- Health check requires auth
- Admin registration requires existing admin
- UserRequest cannot escalate to admin role
- IDOR prevention on ad ownership
- Global error envelope (no raw exceptions)
- OTP rate limiting per user:ip
- Input sanitization on XSS payloads

```bash
# Run security tests only
php artisan test tests/Feature/Security/
```

---

## Reporting a Vulnerability

**Do NOT open a public GitLab/GitHub issue.**

Email: `security@keyhome.app`
Include: affected endpoint, reproduction steps, potential impact.
Response SLA: 24 hours acknowledgement, 72 hours triage.

---

## References

- [Enterprise Audit 2026](../LiveDocs/Enterprise-Full-Audit-2026.md)
- [Security Checklist](./checklist.md)
- [Auth Flows](../architecture/auth-flows.md)
- [Payment System](../architecture/payment-system.md)
