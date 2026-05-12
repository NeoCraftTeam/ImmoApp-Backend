# Clerk Vendor Strategy & Exit Plan

## Current Integration

KeyHome uses **Clerk** as the primary authentication provider for the PWA frontend.
The integration depth is as follows:

| Layer | Integration | Abstraction? |
|-------|-------------|--------------|
| `src/proxy.ts` (middleware) | `clerkMiddleware()` — runs on every request at the edge | Direct |
| `src/providers/AuthProvider.tsx` | `useAuth()`, `useUser()`, `useClerk()` from `@clerk/nextjs` | Direct |
| `app/layout.tsx` | `<ClerkProvider>` wraps the entire app | Direct |
| `src/lib/auth-token.ts` | `auth()` server-side + `useAuth()` client-side | Direct |
| `src/services/auth.service.ts` | POST `/auth/clerk/exchange` — JWT→Sanctum token exchange | Via API |
| All UI pages | Consume `useAuth()` from `AuthProvider` re-export | Via hook |

## Why the Current Coupling Is Acceptable (Short Term)

1. **Clerk is abstracted at the page level**: No page imports directly from `@clerk/nextjs`.
 All pages consume `useAuth()` from `@/providers/AuthProvider`, which re-exports a
 Clerk-compatible shape. Swapping the provider requires changes only in `AuthProvider`.

2. **Backend is auth-agnostic**: Laravel issues and validates Sanctum tokens. Clerk is only
 used as a token issuer — the backend never calls Clerk APIs directly.

3. **Dual-auth fallback exists**: `auth.service.ts` supports legacy email/password Sanctum
 auth independently of Clerk. An exit from Clerk doesn't require backend changes.

## Exit Strategy

### Scenario A: Migrate to Auth.js (NextAuth v5)

**Effort**: Medium (1–2 weeks for a single developer)

**Steps**:
1. Install `next-auth` and configure the `CredentialsProvider` + social OAuth providers
 (Google, Facebook, Apple) matching current Clerk social login options.
2. Replace `ClerkProvider` in `app/layout.tsx` with `SessionProvider` from `next-auth/react`.
3. Update `src/proxy.ts` middleware: replace `clerkMiddleware()` with NextAuth's
 `withAuth()` or a custom `middleware.ts` that reads the `next-auth.session-token` cookie.
4. Update `src/lib/auth-token.ts`: replace `auth()` from `@clerk/nextjs/server` with
 `getServerSession()` from `next-auth`.
5. Update `src/providers/AuthProvider.tsx`: the public shape (`user`, `isAuthenticated`,
 `login`, `logout`) stays the same — only the internal Clerk hooks are replaced.
6. Update `src/services/auth.service.ts`: instead of `/auth/clerk/exchange`, the backend
 needs a `/auth/nextauth/exchange` endpoint that validates the NextAuth JWT.

**Pages/components to touch**: Only `AuthProvider`, `proxy.ts`, `auth-token.ts`, `layout.tsx`.

### Scenario B: Custom Auth (Sanctum-only, no third-party)

**Effort**: Small (3–5 days)

Already partially implemented via `auth.service.ts` email/password flow.

**Steps**:
1. Remove `@clerk/nextjs` and `ClerkProvider` from the dependency tree.
2. Replace `clerkMiddleware()` in `proxy.ts` with a lightweight custom middleware that
 reads the `kh_token` cookie and validates it with `/auth/me`.
3. Update `AuthProvider` to rely purely on the email/password `authService.login()` path.
4. Remove Clerk environment variables from `.env.*`.

**Caveat**: Social login (Google, Facebook, Apple) must be re-implemented separately
via Laravel Socialite on the backend.

## Monitoring Clerk Dependency

Run this command to audit current direct Clerk imports:

```bash
grep -r "from '@clerk" keyhome-frontend-next/src --include="*.ts" --include="*.tsx" \
 | grep -v "node_modules" \
 | grep -v "providers/AuthProvider"
```

Any result that is NOT in `AuthProvider.tsx`, `proxy.ts`, `auth-token.ts`, or `layout.tsx`
represents a direct coupling that should be abstracted.

## Environment Variables to Change on Exit

```
NEXT_PUBLIC_CLERK_PUBLISHABLE_KEY → remove / replace with new provider config
CLERK_SECRET_KEY → remove
```

The backend has no Clerk environment variables — the exchange endpoint only validates
the JWT signature, which is provider-agnostic via standard RS256 verification.
