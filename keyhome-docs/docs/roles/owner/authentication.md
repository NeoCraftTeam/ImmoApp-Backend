---
sidebar_position: 2
title: "Owner Authentication"
---

# Owner Authentication

## Feature Description

Owners (Bailleurs/Agents) have a **dedicated authentication flow** completely separate from the customer flow. The owner panel lives under `/owner/*` and uses its own login, registration, and OAuth callback pages. When an owner logs in, a **role cookie** is set to ensure the middleware routes them to the correct panel.

---

## Owner Registration Flow

Owners can register as either:
- **Individual (`UserType.INDIVIDUAL`)** — a private landlord
- **Agency (`UserType.AGENCY`)** — a real estate agency

```
/owner/register
 
 Step 1: Account type selection (Individual / Agency)
 Step 2: Personal details (name, email, phone, password)
 Step 3: Agency details (if agency type: name, registration number)
 Step 4: City selection
 
 POST /auth/registerAgent → { access_token, user }
 
 Email verification → /verify-email → OTP → finalize
```

---

## Owner Login Flow

```
/owner/login
 
 POST /auth/login (login_context: 'owner')
 { access_token, user, role: 'agent', type }
 
 setRoleCookie('agent') ← Middleware reads this to guard /owner/* routes
 
 redirect → /owner/dashboard
```

---

## OAuth Flow (Owner)

Owners can also use social login (same Clerk OAuth providers as customers). The `/owner/auth` page handles the OAuth callback:

```
/owner/auth (OAuth callback)
 
 POST /auth/clerk/exchange
 
 if new user → /complete-profile or /verify-otp
 
 if agent role → setRoleCookie('agent') → /owner/dashboard
```

---

## Middleware Role Guard

`src/proxy.ts` enforces that:
- `/owner/*` routes require `role=agent` cookie → 403 or redirect to `/owner/login` for non-owners
- Authenticated customers visiting `/owner/*` are redirected to `/home`
- Authenticated owners visiting customer routes (`/profile`, `/home`) are redirected to `/owner/dashboard`

```typescript
// src/proxy.ts (inferred logic)
if (pathname.startsWith('/owner') && !isOwnerRole) {
 return NextResponse.redirect(new URL('/owner/login', request.url));
}
if (!pathname.startsWith('/owner') && isOwnerRole && isPrivateCustomerRoute) {
 return NextResponse.redirect(new URL('/owner/dashboard', request.url));
}
```

---

## Frontend Files Involved

| File | Role |
|---|---|
| `src/app/(owner)/owner/login/page.tsx` | Owner login page |
| `src/app/(owner)/owner/register/page.tsx` | Owner registration |
| `src/app/(owner)/owner/forgot-password/page.tsx` | Owner password reset |
| `src/app/(owner)/owner/auth/page.tsx` | OAuth callback handler |
| `src/app/(owner)/layout.tsx` | Owner layout with OwnerThemeProvider |
| `src/providers/AuthProvider.tsx` | Shared auth context |
| `src/lib/owner-auth-flow.ts` | Owner-specific auth utilities |
| `src/lib/owner-auth-assets.ts` | Owner auth page assets |
| `src/proxy.ts` | Role-based middleware |

---

## Backend API Endpoints

** Inferred from frontend code**

| Method | Endpoint | Body | Response |
|---|---|---|---|
| `POST` | `/api/v1/auth/login` | `{ email, password, login_context: 'owner' }` | `{ access_token, user, role: 'agent', panel_sso_url? }` |
| `POST` | `/api/v1/auth/registerAgent` | Agent registration fields + UTM | `{ access_token, user }` |
| `POST` | `/api/v1/auth/forgot-password` | `{ email }` | `{ message }` |
| `POST` | `/api/v1/auth/reset-password` | `{ token, email, password, password_confirmation }` | `{ message }` |

---

## Related Documentation

- [Tenant Authentication](../tenant/authentication.md) — Shared auth service
- [Owner Dashboard](./dashboard.md)
