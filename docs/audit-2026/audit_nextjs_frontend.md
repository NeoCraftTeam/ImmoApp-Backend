# Audit Report — KeyHome Next.js Frontend (`keyhome-frontend-next`)

## Application Overview

| Attribute | Value |
|---|---|
| **Framework** | Next.js 16.1.6 (App Router) |
| **UI Library** | MUI v7 + Emotion |
| **State/Data** | TanStack React Query, React Context |
| **HTTP Client** | Axios |
| **Validation** | Zod + React Hook Form |
| **Maps** | Mapbox GL + react-map-gl |
| **Auth** | Sanctum bearer tokens via localStorage |

---

## 🔴 Critical Findings

### NC-1. Auth Token Stored in `localStorage` — XSS Extractable

| Attribute | Value |
|---|---|
| **File** | [api.ts](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/keyhome-frontend-next/src/lib/api.ts#L17) |
| **Severity** | 🔴 Critical |

**Evidence:**
```typescript
const token = localStorage.getItem('token');
config.headers.Authorization = `Bearer ${token}`;
```
And in [AuthProvider.tsx](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/keyhome-frontend-next/src/providers/AuthProvider.tsx#L76):
```typescript
localStorage.setItem('token', newToken);
localStorage.setItem('user', JSON.stringify(newUser));
```

`localStorage` is accessible to **any JavaScript running on the page** — including XSS payloads, injected third-party scripts, and browser extensions. A single XSS vulnerability gives full account takeover.

**Remediation:**
- Store the token in an **httpOnly, Secure, SameSite cookie** via Sanctum SPA authentication
- Use Sanctum's cookie-based `web` guard instead of API tokens for the frontend
- Remove all `localStorage.setItem('token', ...)` calls

---

### NC-2. Full User Object in `localStorage` — Data Leakage

| Attribute | Value |
|---|---|
| **File** | [AuthProvider.tsx](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/keyhome-frontend-next/src/providers/AuthProvider.tsx#L77) |
| **Severity** | 🔴 Critical |

**Evidence:**
```typescript
localStorage.setItem('user', JSON.stringify(newUser));
```
The full `User` object (email, phone number, role, city, etc.) is stored in localStorage. This data persists across sessions, is readable by any JS, and survives after logout if `localStorage.removeItem` fails or is skipped.

**Remediation:**
- Store only a minimal session indicator client-side (e.g., user ID)
- Fetch user details from `/auth/me` on app load
- If user data must be cached, use sessionStorage (scoped to tab) instead

---

### NC-3. CSP Allows `unsafe-eval` and `unsafe-inline`

| Attribute | Value |
|---|---|
| **File** | [next.config.ts](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/keyhome-frontend-next/next.config.ts#L61) |
| **Severity** | 🔴 Critical |

**Evidence:**
```
script-src 'self' 'unsafe-eval' 'unsafe-inline' https://api.mapbox.com;
style-src 'self' 'unsafe-inline' https://api.mapbox.com;
```
Both `unsafe-eval` and `unsafe-inline` **completely defeat the purpose of CSP** for XSS protection. Combined with NC-1 (token in localStorage), this is a very high risk.

**Remediation:**
- Remove `'unsafe-eval'` — use Next.js `'strict-dynamic'` or nonce-based CSP (Next.js 16 supports `nonce` via `next/headers`)
- Remove `'unsafe-inline'` from `script-src` — use nonces or hashes
- `'unsafe-inline'` in `style-src` is harder to avoid with MUI (Emotion uses runtime injection) but can be mitigated with `@emotion/cache` + nonces

---

## 🟠 High Findings

### NH-1. Client-Only Auth Guards — No Server-Side Protection

| Attribute | Value |
|---|---|
| **Files** | [dashboard/layout.tsx](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/keyhome-frontend-next/src/app/%28dashboard%29/layout.tsx#L14-L18), [auth/layout.tsx](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/keyhome-frontend-next/src/app/%28auth%29/layout.tsx#L12-L16) |
| **Severity** | 🟠 High |

**Evidence:**
```typescript
useEffect(() => {
  if (!isLoading && !isAuthenticated) {
    router.replace('/login');
  }
}, [isAuthenticated, isLoading, router]);
```
Route protection is purely client-side via `useEffect`. The HTML/JS of protected pages is **always delivered to the client** — only the redirect is client-side. Dashboard content briefly renders before redirect, and an attacker can intercept or disable JavaScript.

**Remediation:** Use Next.js middleware (`middleware.ts`) to check the auth cookie server-side and redirect before page render.

---

### NH-2. No CSRF Token on State-Changing API Calls

| Attribute | Value |
|---|---|
| **File** | [api.ts](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/keyhome-frontend-next/src/lib/api.ts) |
| **Severity** | 🟠 High |

**Evidence:** The Axios client sends bearer tokens but never fetches or sends a CSRF token. If migrating to Sanctum SPA (cookie-based) auth, CSRF protection becomes essential but isn't implemented.

**Remediation:**
- When using Sanctum SPA auth: call `/sanctum/csrf-cookie` before login
- Include `X-XSRF-TOKEN` header from cookies on every mutation
- Set `withCredentials: true` on the Axios instance

---

### NH-3. Login Displays Raw API Error Messages

| Attribute | Value |
|---|---|
| **File** | [login/page.tsx](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/keyhome-frontend-next/src/app/%28auth%29/login/page.tsx#L42-L45) |
| **Severity** | 🟠 High |

**Evidence:**
```typescript
setError(
  axiosErr?.response?.data?.message ||
    'Identifiants incorrects. Veuillez réessayer.'
);
```
API error messages (including potential internal errors) are displayed directly in the UI. Backend error leaks (see backend audit C-4) are forwarded to the user.

**Remediation:** Map API error codes to predefined French messages; never display raw `data.message`.

---

### NH-4. Mapbox Token Committed to Repository

| Attribute | Value |
|---|---|
| **File** | [.env.local](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/keyhome-frontend-next/.env.local#L2) |
| **Severity** | 🟠 High |

**Evidence:**
```
NEXT_PUBLIC_MAPBOX_TOKEN=pk.eyJ1IjoibmVvY3JhZnR0ZWFtIiwiYSI6ImNtbGk1eDUzNjBleTAzZHNldmpxMDFhemcifQ.FkGfYbbT-7KH1FO5diHh_w
```
The Mapbox public token is committed in `.env.local`. While `NEXT_PUBLIC_` tokens are client-visible by design, the `.env.local` file should be in `.gitignore` and the token should have URL restriction configured in Mapbox dashboard.

**Remediation:**
- Add `.env.local` to `.gitignore`
- Rotate the Mapbox token
- Apply URL restrictions in the Mapbox Dashboard

---

## 🟡 Medium Findings

| ID | Finding | File | Impact |
|---|---|---|---|
| NM-1 | `FavoritesProvider` stores full `Ad` objects in localStorage — unbounded growth can reach quota | [FavoritesProvider.tsx](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/keyhome-frontend-next/src/providers/FavoritesProvider.tsx#L35) | Storage quota exceeded, browser slowdown |
| NM-2 | `login()` stores token then makes a second call to `/auth/me` — race condition if interceptor fires before user state | [auth.service.ts](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/keyhome-frontend-next/src/services/auth.service.ts#L22-L26) | Flash of unauthenticated state |
| NM-3 | `connect-src` in CSP includes `http://localhost:8000` — allows dev traffic in production | [next.config.ts](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/keyhome-frontend-next/next.config.ts#L61) | CSP bypass in prod |
| NM-4 | Image `remotePatterns` includes `http://keyhome.test` and `http://localhost:8000` — dev origins in production config | [next.config.ts](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/keyhome-frontend-next/next.config.ts#L8-L11) | Potential SSRF vector |
| NM-5 | No error boundary — unhandled promise rejections (e.g., from API calls) can crash the React tree | App-wide | White screen on errors |
| NM-6 | `ads.service.ts` uses `PUT` with FormData but doesn't append `_method: 'PUT'` — Laravel `multipart/form-data` needs POST+`_method` | [ads.service.ts](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/keyhome-frontend-next/src/services/ads.service.ts#L69) | Ad updates may fail silently |

---

## 🟢 Low Findings

| ID | Finding | File |
|---|---|---|
| NL-1 | `suppressHydrationWarning` on `<html>` masks real hydration issues | [layout.tsx](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/keyhome-frontend-next/src/app/layout.tsx#L25) |
| NL-2 | No loading/skeleton states on `(dashboard)/home` — content pops in after query resolves | Dashboard pages |
| NL-3 | No `<meta name="viewport">` explicit declaration (Next.js adds a default, but good to be explicit) | [layout.tsx](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/keyhome-frontend-next/src/app/layout.tsx) |
| NL-4 | `QueryProvider` uses default `staleTime: 0` — every navigation refetches, no query deduplication benefit | [QueryProvider.tsx](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/keyhome-frontend-next/src/providers/QueryProvider.tsx) |

---

## Remediation Plan

### 🚨 Quick Wins (< 1 hour each)

| # | Finding | Action |
|---|---|---|
| 1 | NM-3, NM-4 | Remove `http://localhost:*` and `http://keyhome.test` from CSP and `remotePatterns` in production |
| 2 | NH-4 | Add `.env.local` to `.gitignore`, rotate Mapbox token, apply URL restrictions |
| 3 | NH-3 | Replace raw API error display with predefined i18n error messages |
| 4 | NM-1 | Store only favorite ad IDs in localStorage, fetch full objects on-demand |

### 📋 Short-Term (1–2 weeks)

| # | Finding | Action |
|---|---|---|
| 5 | NC-1, NC-2, NH-2 | Migrate from localStorage bearer tokens to Sanctum SPA cookie-based auth |
| 6 | NH-1 | Add `middleware.ts` for server-side auth checks |
| 7 | NM-6 | Fix `ads.service.ts` update to use POST+`_method: 'PUT'` for FormData |
| 8 | NM-5 | Add React Error Boundary at the layout level |

### 🔧 Mid-Term (1–2 months)

| # | Finding | Action |
|---|---|---|
| 9 | NC-3 | Implement nonce-based CSP (remove `unsafe-eval`/`unsafe-inline`) |
| 10 | — | Add comprehensive E2E tests with Playwright or Cypress |
| 11 | NL-4 | Configure sensible `staleTime`/`gcTime` defaults in QueryProvider |

### 🏗️ Long-Term (3–6 months)

| # | Action |
|---|---|
| 12 | Implement incremental static regeneration for public ad pages |
| 13 | Add structured analytics (Plausible, Fathom) with privacy compliance |
| 14 | Performance audit with Web Vitals monitoring |
