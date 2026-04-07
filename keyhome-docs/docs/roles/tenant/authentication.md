---
sidebar_position: 2
title: "Authentication"
---

# Tenant Authentication

## Feature Description

KeyHome supports **two authentication methods** for tenants:

1. **Email/Password** — Traditional sign-up and login using a Laravel Sanctum token
2. **OAuth (Social Login)** — Google, Facebook, and Apple sign-in via Clerk, exchanged for a Sanctum token

All authentication state is managed by the `AuthProvider` with in-memory token storage to protect against XSS attacks.

---

## Registration Flow

### Email/Password Registration

1. Visitor navigates to `/register`
2. Fills in: first name, last name, email, phone number, password, account type (customer / agent)
3. Optionally enters a **promo code**
4. UTM parameters are automatically captured and attached to the registration payload
5. `POST /auth/registerCustomer` or `POST /auth/registerAgent` is called
6. On success → `POST /auth/email/resend` triggers verification email
7. User is redirected to `/verify-email`
8. User enters the **OTP code** from email → `POST /auth/verify-email-otp`
9. On verification → user receives Sanctum token and is redirected to `/home`

### OAuth Registration (Clerk)

1. Visitor clicks "Continuer avec Google/Facebook/Apple" on `/register` or `/login`
2. `GET /auth/oauth/:provider/redirect` fetches the OAuth redirect URL
3. Browser is redirected to the OAuth provider
4. After consent, browser is redirected back to `/sso-callback`
5. `POST /auth/clerk/exchange` is called with the Clerk JWT
6. **If new user needing OTP**: response `{ state: 'otp_required' }` → redirected to `/verify-otp`
7. **If new user needing profile completion**: redirected to `/complete-profile`
8. **If existing user**: Sanctum token returned → finalize auth

---

## Login Flow

### Email/Password Login

```
/login page
 
 POST /auth/login → { access_token, user, role, type }
 
 if role === 'agent' (owner) → set role cookie → redirect /owner/dashboard
 
 if role === 'customer' → store token → redirect /home
```

### OAuth Login (Clerk)

```
/login page → social button click
 
 GET /auth/oauth/:provider/redirect → { redirect_url }
 
 Browser → OAuth provider → /sso-callback
 
 POST /auth/clerk/exchange → { token, user } OR { state: 'otp_required' }
 
 if otp_required → /verify-otp → POST /auth/clerk/verify-otp
 
 finalizeAuth(token, user, panelSsoUrl) → redirect based on role
```

---

## Frontend Files Involved

| File | Role |
|---|---|
| `src/app/(auth)/login/page.tsx` | Login page |
| `src/app/(auth)/register/page.tsx` | Registration page |
| `src/app/(auth)/forgot-password/page.tsx` | Password reset request |
| `src/app/(auth)/reset-password/page.tsx` | Password reset via token |
| `src/app/(auth)/verify-email/page.tsx` | Email verification prompt |
| `src/app/(auth)/verify-otp/page.tsx` | OTP code entry |
| `src/app/(auth)/complete-profile/page.tsx` | Post-OAuth profile setup |
| `src/app/(auth)/layout.tsx` | Auth layout with SplashTransition |
| `src/app/sso-callback/page.tsx` | OAuth callback handler |
| `src/components/auth/SocialLoginButtons.tsx` | Google/Facebook/Apple OAuth buttons |
| `src/providers/AuthProvider.tsx` | Global auth context |
| `src/services/auth.service.ts` | All auth API calls |
| `src/lib/auth-session.ts` | Token storage & role cookie helpers |
| `src/lib/auth-token.ts` | Axios token getter registration |
| `src/lib/utm.ts` | UTM parameter capture |
| `src/lib/register-intent.ts` | Registration intent tracking |
| `src/proxy.ts` | Middleware: redirect auth users away from auth pages |

---

## Key Code Snippets

### Login via AuthProvider

```typescript
// src/providers/AuthProvider.tsx
const login = async (email: string, password: string) => {
 const result = await authService.login({ email, password, login_context: 'customer' });
 finalizeAuth(result.access_token, result.user, result.panel_sso_url);
};

const finalizeAuth = (token: string, user: User, panelSsoUrl?: string) => {
 persistInMemoryToken(token); // In-memory only — no localStorage
 setUser(user);
 setRoleCookie(user.role); // Cookie for middleware route guard
 if (user.role === 'agent') {
 window.location.href = '/owner/dashboard';
 } else {
 router.push('/home');
 }
};
```

### Email OTP Verification

```typescript
// src/services/auth.service.ts
async verifyEmailOtp(email: string, otp: string) {
 const { data } = await api.post('/auth/verify-email-otp', { email, otp });
 return data; // { verified, access_token, user, role?, type? }
},
```

### OAuth Clerk Exchange

```typescript
// src/services/auth.service.ts
async clerkExchange(registrationIntent?: string) {
 const { data } = await api.post('/auth/clerk/exchange', {
 registration_intent: registrationIntent
 });
 return data;
 // Returns: { token, user, panel_sso_url } OR { state: 'otp_required' }
},
```

### Logout

```typescript
// src/providers/AuthProvider.tsx
const logout = async (redirectTo?: string) => {
 setIsLoggingOut(true); // Shows 3.5s animated overlay
 await authService.logout();
 clearInMemoryToken();
 clearRoleCookie();
 await delay(3500);
 window.location.href = redirectTo ?? '/login';
};
```

---

## Session Management

| Storage | What | Why |
|---|---|---|
| **In-memory** | Sanctum Bearer token | XSS protection — not accessible via `document.cookie` or `localStorage` |
| **Cookie** (`role`) | User role (`agent` / `customer`) | Accessible to Next.js middleware for route guarding |
| **Cookie** (`XSRF-TOKEN`) | CSRF token | Required by Laravel Sanctum |
| **localStorage** | Theme preference, comparator, favourites, search history | Non-sensitive UI state |

---

## Auth Guard in Layout

The `(auth)/layout.tsx` uses a **SplashTransition** component (1400ms overlay) on the first visit, then fades in:

```typescript
// src/app/(auth)/layout.tsx
// If user is authenticated and not on verify/OTP pages → redirect to /home
useEffect(() => {
 if (isAuthenticated && !isVerifyPage) {
 router.replace('/home');
 }
}, [isAuthenticated, isVerifyPage]);
```

---

## Backend API Endpoints

** Inferred from frontend code**

| Method | Endpoint | Body | Response |
|---|---|---|---|
| `POST` | `/api/v1/auth/login` | `{ email, password, login_context }` | `{ access_token, user, expires_at, role, type, panel_sso_url? }` |
| `POST` | `/api/v1/auth/registerCustomer` | Registration fields + UTM | `{ access_token, user }` |
| `POST` | `/api/v1/auth/registerAgent` | Registration fields + UTM | `{ access_token, user }` |
| `GET` | `/api/v1/auth/me` | — | `User` |
| `POST` | `/api/v1/auth/logout` | — | `{}` |
| `POST` | `/api/v1/auth/forgot-password` | `{ email }` | `{ message }` |
| `POST` | `/api/v1/auth/reset-password` | `{ token, email, password, password_confirmation }` | `{ message }` |
| `POST` | `/api/v1/auth/email/resend` | `{ email? }` | `{ message }` |
| `POST` | `/api/v1/auth/verify-email-otp` | `{ email, otp }` | `{ verified, access_token, user, role?, type? }` |
| `POST` | `/api/v1/auth/clerk/exchange` | `{ registration_intent? }` | `{ token, user, panel_sso_url }` or `{ state: 'otp_required' }` |
| `POST` | `/api/v1/auth/clerk/verify-otp` | `{ otp }` | `{ state, token, user }` |
| `POST` | `/api/v1/auth/clerk/complete-profile` | `{ phone_number?, city_id? }` | `{ token, user, panel_sso_url }` |
| `GET` | `/api/v1/auth/oauth/:provider/redirect` | `?redirect_uri=` | `{ redirect_url }` |
| `POST` | `/api/v1/auth/check-email` | `{ email }` | `{ available: boolean }` |
| `POST` | `/api/v1/auth/onboarding-complete` | — | `{}` |

---

## Error Handling

The `AuthProvider` dispatches a global `auth:expired` event on 401 responses (via the Axios interceptor in `src/lib/api.ts`). The layout listens for this event and redirects to `/login` with the current path as `redirect` query parameter.

```typescript
// src/lib/api.ts
api.interceptors.response.use(null, (error) => {
 if (error.response?.status === 401) {
 window.dispatchEvent(new Event('auth:expired'));
 }
 return Promise.reject(error);
});
```
