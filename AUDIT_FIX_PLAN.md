# 🛡️ ImmoApp Audit Remediation Plan

This document outlines the step-by-step plan to address critical security, performance, and reliability issues identified in the recent audit.

## 🚨 Phase 1: Critical Security & Quick Wins (Immediate)

### 1. Mobile WebView Isolation (Anti-XSS/RCE)
- [ ] **Restrict Origin Whitelist**: In `mobile/agency/App.js` and `mobile/bailleur/App.js`, change `originWhitelist` from `['*']` to explicitly trusted domains (e.g., `['https://api.keyhome.neocraft.dev', 'http://localhost*']`).
- [ ] **Validate Message Origin**: In `NativeService.js`, ensure messages (Camera/Location requests) originate *only* from trusted URLs to prevent malicious sites from triggering native device hardware.

### 2. API Security Hardening (CORS & Tokens)
- [ ] **Restrict CORS**: In `config/cors.php`, replace wildcard `*` with specific frontend domains.
- [ ] **Set Token Expiration**: In `config/sanctum.php`, change `'expiration' => null` to `'expiration' => 60` (minutes) to prevent indefinite session hijacking.

### 3. Payment Webhook Security (Replay Attacks)
- [ ] **Validate Timestamp**: Update `PaymentController::hasValidWebhookSignature` to check the `t=` timestamp in the signature header. Reject requests older than 5 minutes.
- [ ] **Rate Limit Payment Initiation**: Add `throttle:10,1` to the `/payments/initialize` route in `routes/api.php` to prevent database flooding.

### 4. GDPR & Privacy Compliance
- [ ] **Remove Data Leak**: In `app/Models/User.php`, remove the dependency on `ui-avatars.com` (which sends user names to a 3rd party). Replace with a local placeholder or privacy-friendly alternative.

---

## 🚀 Phase 2: Reliability & Performance (Short-term)

### 5. Database Optimization (Indexes)
- [ ] **Create Migration**: Add composite index on `ad` table: `(status, created_at)` to optimize the main feed query.
- [ ] **Create Migration**: Add index on `payments` table: `(user_id, status)` / `(user_id, ad_id, type)` to optimize the "Unlocked Ads" and "Status Check" queries.

### 6. Data Integrity (Safety)
- [ ] **Prevent Cascading Deletes**: Create migration to change foreign key on `ad.user_id` from `onDelete('cascade')` to `onDelete('restrict')` or `onDelete('set null')`. This prevents accidental mass deletion of ads if a user is deleted.

### 7. Global Rate Limiting
- [ ] **Review Auth Throttling**: Ensure all auth endpoints (`login`, `register`, `forgot-password`) have strict rate limits (already partially verified, need confirmation on all paths).

---

## 🏗️ Phase 3: Infrastructure & Ops (Mid-term)

### 8. Queue Performance
- [ ] **Switch to Redis**: Update `.env.example` and documentation to recommend `QUEUE_CONNECTION=redis` for production to avoid database locking during high-traffic email blasts.

### 9. API Documentation Security
- [ ] **Hide Swagger in Production**: Ensure `L5_SWAGGER_GENERATE_ALWAYS` is false and routes are protected or disabled in production environment.
