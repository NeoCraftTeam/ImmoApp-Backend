# KeyHome — Authentication Flows

> **Last updated:** April 2026

---

## Auth Methods Summary

| Method | Entry point | Used by |
|--------|-------------|---------|
| **Clerk JWT → Sanctum** | `POST /api/v1/auth/clerk/exchange` | Next.js PWA (primary) |
| **Email + Password** | `POST /api/v1/auth/login` | Legacy / fallback |
| **Magic Link** | `POST /api/v1/auth/magic-link` | Passwordless sign-in |
| **Social OAuth (Socialite)** | `/api/v1/auth/oauth/{provider}` | Google, Facebook, Apple |
| **Filament Session + MFA** | `/admin`, `/agency` | Admin & agency panels |

---

## Flow 1 — Clerk JWT Exchange (Primary PWA Flow)

```
User (browser)          Clerk              Laravel API
     │                    │                    │
     ├──── OAuth login ───►│                    │
     │◄─── JWT issued ─────┤                    │
     │                    │                    │
     ├────────────── POST /auth/clerk/exchange ─►│
     │               { clerk_token: "..." }      │
     │                                          │ 1. Verify JWT with JWKS
     │                                          │ 2. Find or create User
     │                                          │ 3. TokenService::createForUser()
     │                                          │    → kh_{role}_{random} token
     │◄──────────────── { token, user } ─────────┤
     │                                          │
     ├── All subsequent requests: Bearer {token}─►│
     │                                          │ auth:sanctum middleware
```

**Key:** `ClerkJwtService::verify()` calls the Clerk JWKS endpoint. Token is cached per-JTI.

---

## Flow 2 — Email/Password Login

```
POST /api/v1/auth/login
     │
     ├── LoginRequest (validation)
     │
     ├── LoginService::authenticate(LoginRequest)
     │   ├── Rate limit check (IP + email composite key)
     │   ├── Auth::attempt()
     │   ├── User active check (EnsureUserIsActive)
     │   ├── Role enforcement check
     │   ├── LogAuthenticationEvents listener
     │   └── TokenService::createForUser() → LoginResult DTO
     │
     └── Returns: { token, user, requires_mfa? }
```

---

## Flow 3 — Social OAuth (Socialite)

```
GET /api/v1/auth/oauth/{provider}/redirect
     │
     └── Redirects to provider (Google / Facebook / Apple)

GET /api/v1/auth/oauth/{provider}/callback
     │
     ├── Socialite::driver(provider)->user()
     ├── Find or create User (email match)
     ├── TokenService::createForUser()
     └── Redirect to FRONTEND_URL/auth/callback?token=...
```

---

## Flow 4 — Filament Panel (MFA)

```
/admin or /agency
     │
     ├── Session-based login (NOT Sanctum token)
     │
     ├── MFA challenge:
     │   ├── TOTP (Google Authenticator)
     │   └── Email OTP (6-digit, rate-limited per user:ip)
     │
     └── EnsureCorrectRoleForPanel middleware
         └── RoleScopedSession — isolates admin vs agency session
```

---

## Token Architecture

### Naming Convention

| Role | Token prefix | Ability |
|------|-------------|---------|
| Customer | `kh_client_` | `role:customer` |
| Agent / Agency | `kh_owner_` | `role:agent` |
| Admin (API) | `kh_admin_` | `role:admin` |

### `TokenService` (consolidated)

```php
// Create token for newly authenticated user
TokenService::createForUser(User $user, string $deviceName): LoginResult

// Rotate: revoke current token, issue new one (used after sensitive actions)
TokenService::rotateForUser(User $user, PersonalAccessToken $old): string
```

All 7 token-creation sites in the codebase use this service — no raw `createToken()` calls.

---

## Middleware Stack (API routes)

```
web.php / api.php
    │
    ├── SecurityHeaders          — HSTS, X-Frame-Options, CSP
    ├── AddRequestId             — X-Request-ID header
    ├── SanitizeInput            — denylist XSS strip on all string inputs
    ├── OptionalAuth             — sets user if token present (no failure)
    │
    └── Protected routes:
        ├── auth:sanctum         — require valid token
        ├── EnsureUserIsActive   — account not suspended
        ├── EnsureEmailIsVerified — email confirmed
        ├── EnsureTokenMatchesRole — token ability matches route
        └── RequireApiMfa        — for MFA-sensitive endpoints
```

---

## Multi-Role Session Isolation

A single user cannot hold both a `customer` and `agent` session simultaneously.

- **Sanctum abilities** — `createToken($name, ['role:customer'])` prevents using a customer token on agent routes
- **Session prefix scoping** — `RoleScopedSession` middleware namespaces the Laravel session key by role
- **`EnsureTokenMatchesRole`** — validates ability on every request

```php
// Agent route protection (owner panel)
Route::middleware(['auth:sanctum', 'owner.role', 'panel.role:owner', 'token.role:agent'])
```

---

## API Rate Limits (per minute)

| Actor | Limit | Key |
|-------|-------|-----|
| Guest | 60 req/min | IP |
| Customer | 300 req/min | user_id |
| Agent (with subscription) | 500 req/min | user_id |
| Agent (without subscription) | 300 req/min | user_id |
| Admin | Unlimited | — |
| OTP verify/resend | 5/min | user_id:ip composite |
