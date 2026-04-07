---
sidebar_position: 2
title: "AuthProvider"
---

# AuthProvider

## Overview

`AuthProvider` (`src/providers/AuthProvider.tsx`) is the **global authentication context** for the entire application. It manages:

- Dual authentication (email/password Sanctum + Clerk OAuth)
- In-memory token storage (XSS protection)
- Session migration from legacy tokens
- Role-based redirects (owner vs customer)
- Logout overlay (3.5-second animation)
- CSRF token management

---

## Context Interface

```typescript
interface AuthContextType {
 // State
 user: User | null;
 token: string | null;
 isLoading: boolean;
 isAuthenticated: boolean;
 isLoggingOut: boolean;

 // Actions
 login(email: string, password: string): Promise<void>;
 loginOwner(email: string, password: string): Promise<void>;
 loginWithOAuth(provider: string, options?: OAuthOptions): Promise<void>;
 logout(redirectTo?: string): Promise<void>;
 setUser(user: User): void;
 refreshUser(): Promise<void>;
 finalizeAuth(token: string, user: User, panelSsoUrl?: string): void;
 getClerkToken(): Promise<string | null>;
}
```

---

## Usage

```typescript
import { useAuth } from '@/providers/AuthProvider';

function MyComponent() {
 const { user, isAuthenticated, login, logout } = useAuth();

 if (!isAuthenticated) {
 return <button onClick={() => login('email@example.com', 'password')}>Login</button>;
 }

 return (
 <div>
 <p>Welcome, {user?.display_name}</p>
 <button onClick={() => logout()}>Logout</button>
 </div>
 );
}
```

---

## Token Storage Strategy

The AuthProvider uses **in-memory storage** for the Sanctum Bearer token — never `localStorage` or `sessionStorage`. This prevents XSS attacks from stealing authentication tokens.

```typescript
// src/lib/auth-session.ts
let inMemoryToken: string | null = null;

export function persistInMemoryToken(token: string) {
 inMemoryToken = token;
}

export function getInMemoryToken(): string | null {
 return inMemoryToken;
}

export function clearInMemoryToken() {
 inMemoryToken = null;
}
```

The Axios interceptor in `src/lib/api.ts` reads this token for every request:

```typescript
// src/lib/api.ts
api.interceptors.request.use((config) => {
 const token = getInMemoryToken();
 if (token) {
 config.headers['Authorization'] = `Bearer ${token}`;
 }
 return config;
});
```

---

## Role Cookie

When the user is an owner (`role === 'agent'`), a `role` cookie is set so that `src/proxy.ts` can guard owner routes **at the middleware level** (before React renders):

```typescript
// src/lib/auth-session.ts
export function setRoleCookie(role: string) {
 document.cookie = `role=${role}; path=/; SameSite=Lax`;
}

export function clearRoleCookie() {
 document.cookie = 'role=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT';
}
```

---

## Authentication Flows

### Email/Password Login

```typescript
const login = async (email: string, password: string) => {
 const result = await authService.login({
 email,
 password,
 login_context: 'customer',
 });
 finalizeAuth(result.access_token, result.user, result.panel_sso_url);
};
```

### OAuth (Clerk) Flow

```typescript
const loginWithOAuth = async (provider: string, options?: OAuthOptions) => {
 const { redirect_url } = await authService.getOAuthRedirectUrl(provider, {
 redirect_uri: `${window.location.origin}/sso-callback`,
 ...options,
 });
 window.location.href = redirect_url;
};
```

After the OAuth callback (`/sso-callback`):

```typescript
// Called from sso-callback page
const { token, user, panel_sso_url } = await authService.clerkExchange();
finalizeAuth(token, user, panel_sso_url);
```

### finalizeAuth (Common Entry Point)

```typescript
const finalizeAuth = (token: string, user: User, panelSsoUrl?: string) => {
 persistInMemoryToken(token);
 setUser(user);
 setRoleCookie(user.role);

 // Admin → Filament panel via SSO
 if (user.role === 'admin' && panelSsoUrl) {
 window.location.href = panelSsoUrl;
 return;
 }
 // Owner → Owner panel
 if (user.role === 'agent') {
 window.location.href = '/owner/dashboard';
 return;
 }
 // Customer → home feed
 router.push('/home');
};
```

### Logout

```typescript
const logout = async (redirectTo?: string) => {
 setIsLoggingOut(true); // Triggers LogoutOverlay component
 try {
 await authService.logout(); // POST /auth/logout → invalidates backend session
 } finally {
 clearInMemoryToken();
 clearRoleCookie();
 setUser(null);
 await delay(3500); // Wait for overlay animation
 window.location.href = redirectTo ?? '/login';
 }
};
```

---

## 401 Handling

The Axios interceptor dispatches an `auth:expired` custom event on 401 responses. The AuthProvider listens for this event and triggers logout:

```typescript
// src/lib/api.ts
api.interceptors.response.use(null, async (error) => {
 if (error.response?.status === 401) {
 window.dispatchEvent(new Event('auth:expired'));
 }
 return Promise.reject(error);
});

// AuthProvider listens:
useEffect(() => {
 const handler = () => logout('/login?reason=expired');
 window.addEventListener('auth:expired', handler);
 return () => window.removeEventListener('auth:expired', handler);
}, []);
```

---

## refreshUser

Fetches the latest user data from the API and updates the context. Called after profile updates.

```typescript
const refreshUser = async () => {
 const user = await authService.me();
 setUser(user);
};
```

---

## Provider Placement

`AuthProvider` is placed inside `QueryProvider` (TanStack Query) but outside all route groups, wrapping the entire application from `src/app/layout.tsx`.

---

## Related

- [Tenant Authentication](../roles/tenant/authentication.md)
- [Owner Authentication](../roles/owner/authentication.md)
- [Admin Panel SSO](../roles/admin/panel-sso.md)
- [Middleware](./middleware.md)
