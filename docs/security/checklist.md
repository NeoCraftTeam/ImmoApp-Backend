# KeyHome — Pre-Deploy Security Checklist

> **Last updated:** April 2026
> **Source:** Enterprise Audit April 2026 + Production Readiness Report

Run this checklist before every production deployment.

---

## 🔴 Critical (Block deploy if any fail)

- [ ] `APP_DEBUG=false` in production `.env`
- [ ] `APP_KEY` is set and not the default value
- [ ] `APP_ENV=production`
- [ ] Flutterwave keys are production keys (not `_TEST_` prefix)
- [ ] `CLERK_SECRET_KEY` is production key (`sk_live_` prefix)
- [ ] Database password is strong and not default
- [ ] `SANCTUM_STATEFUL_DOMAINS` matches production frontend domain
- [ ] No hardcoded secrets in code (`git grep -r "sk_live\|FLWSECK\|password" --include="*.php" app/`)

---

## 🟠 High (Fix before deploy)

- [ ] All pending migrations are backward-compatible
- [ ] `php artisan test` — all tests pass
- [ ] `vendor/bin/phpstan analyse` — no new errors
- [ ] No `dd()`, `dump()`, `var_dump()` left in code
- [ ] `error` key NOT present in any API catch block responses
- [ ] New API endpoints have FormRequest validation
- [ ] New API endpoints have Policy authorization
- [ ] Admin-only routes protected with `can:admin-access` middleware

---

## 🟡 Medium (Fix within 24h of deploy)

- [ ] Sentry DSN configured and receiving events
- [ ] Queue workers all running post-deploy (`docker compose ps`)
- [ ] MeiliSearch index synced if models changed (`php artisan scout:import`)
- [ ] `config:cache`, `route:cache`, `view:cache` cleared and rebuilt
- [ ] Rate limits verified for new endpoints
- [ ] File upload endpoints have MIME type and size validation
- [ ] New Filament resources have `canAccess()` policy guard

---

## 🔵 Infrastructure (DevOps checklist)

- [ ] SSL certificate valid (auto-renewed by Traefik/Let's Encrypt)
- [ ] Database backup taken before deploy: `php artisan backup:run --only-db`
- [ ] Docker images pushed to registry before deploy
- [ ] Rollback plan ready (previous image tag identified)
- [ ] Monitoring dashboards (Grafana, Sentry) confirm baseline before deploy

---

## 🔒 Known Security Posture (April 2026)

All items below were identified in the Enterprise Audit and **have been fixed**:

| # | Finding | Fixed |
|---|---------|-------|
| SEC-01 | Health check endpoint unauthenticated | ✅ Protected with `auth:sanctum + can:admin-access` |
| SEC-02 | Admin registration open | ✅ Requires `auth:sanctum + can:admin-access` |
| SEC-03 | OTP rate-limited only by IP | ✅ Composite `user_id:ip` key |
| SEC-04 | Debug error leakage in API responses | ✅ `error` key removed from all catch blocks |
| SEC-05 | CORS too broad | ✅ Explicit methods/headers, tightened subdomain regex |
| SEC-06 | `TRUSTED_PROXIES` via `env()` at boot | ✅ Moved to `config/proxy.php` |
| SEC-07 | `UserRequest` allowed `admin` role | ✅ Privilege escalation vector closed |
| SEC-08 | No global JSON error envelope | ✅ `withExceptions` handlers for 401/403/404/429/500 |
| SEC-09 | `SanitizeInput` used field-name allowlist | ✅ Changed to denylist — all strings sanitized |
| SEC-10 | `exclude_ids` unbounded | ✅ Max 50 UUIDs |

**Open / In-progress:**
| # | Finding | Status | Priority |
|---|---------|--------|---------|
| SEC-11 | CSP `unsafe-inline` + `unsafe-eval` | 🔄 In progress — nonce-based CSP planned | High |
| SEC-12 | Frontend localStorage auth token | 🔄 Migration to httpOnly cookies planned | Medium |
| SEC-13 | Mobile SSL pinning | 📋 Backlog | Low |

---

## After Deploy — Smoke Tests

```bash
# API health
curl -H "Authorization: Bearer $ADMIN_TOKEN" https://api.keyhome.app/api/health

# Public ad listing
curl "https://api.keyhome.app/api/v1/ads?per_page=3" | jq '.data | length'

# Auth endpoint
curl -X POST https://api.keyhome.app/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@invalid.com","password":"wrong"}' | jq '.success'
# Expected: false (not 500)

# Rate limit active
for i in {1..5}; do curl -X POST https://api.keyhome.app/api/v1/auth/login -s -o /dev/null -w "%{http_code}\n"; done
# Expected: 200/422 then 429
```
