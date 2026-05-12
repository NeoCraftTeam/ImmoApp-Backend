---
sidebar_position: 4
title: "Middleware"
---

# Middleware (`src/proxy.ts`)

## Overview

The Next.js middleware is defined in `src/proxy.ts` (exported and referenced by `src/middleware.ts`). It uses **Clerk middleware** to handle:

1. **Per-request nonce-based Content Security Policy (CSP)** headers
2. **Role-based route protection** using a `role` cookie
3. **Automatic redirects** for authenticated users
4. **Clerk authentication** via the Clerk middleware stack

The middleware runs on every request **before** any page renders, making it the first line of defence for route security.

---

## Middleware Execution Flow

```
Incoming Request
 
 Skip CSP on prefetch requests (Next.js prefetch headers)
 
 Generate cryptographic nonce (crypto.randomUUID())
 
 Build per-request CSP header with nonce
 
 Clerk.auth() → resolve user authentication state
 
 Route Protection Rules:
 / (landing) + authenticated user → redirect /home
 /owner/* + role !== 'agent' → redirect /owner/login
 /owner/* + unauthenticated → redirect /owner/login
 PRIVATE customer routes + unauthenticated → redirect /login
 PRIVATE customer routes + owner role → redirect /owner/dashboard
 
 Set x-nonce header on response (for Emotion/MUI SSR)
 
 Set Content-Security-Policy response header
```

---

## Content Security Policy

The middleware generates a **per-request nonce** and injects it into the CSP. This allows inline scripts (required by Next.js and MUI/Emotion) while preventing XSS:

```typescript
// src/proxy.ts
const nonce = Buffer.from(crypto.randomUUID()).toString('base64');

const cspHeader = `
 default-src 'self';
 script-src 'self' 'nonce-${nonce}' 'strict-dynamic' https://clerk.keyhome.cm;
 style-src 'self' 'nonce-${nonce}' 'unsafe-inline';
 img-src 'self' data: https: blob:;
 font-src 'self' https://fonts.gstatic.com;
 connect-src 'self' https://api.keyhome.cm https://clerk.keyhome.cm;
 frame-src 'self' https://js.stripe.com https://checkout.flutterwave.com;
 object-src 'none';
 base-uri 'self';
 form-action 'self';
 upgrade-insecure-requests;
`;
```

The nonce is passed via the `x-nonce` response header so that `src/app/layout.tsx` can inject it into the `<script>` tag and Emotion's style insertion:

```typescript
// src/app/layout.tsx
import { headers } from 'next/headers';

export default function RootLayout({ children }) {
 const nonce = headers().get('x-nonce') ?? '';
 return (
 <html>
 <head>
 <script nonce={nonce} dangerouslySetInnerHTML={{ __html: themeScript }} />
 </head>
 <body>
 <MuiEmotionRegistry nonce={nonce}>
 {children}
 </MuiEmotionRegistry>
 </body>
 </html>
 );
}
```

---

## Route Protection Rules

### Landing Page Redirect

```typescript
// Authenticated users should not see the landing page
if (userId && pathname === '/') {
 return NextResponse.redirect(new URL('/home', request.url));
}
```

### Owner Route Guard

```typescript
const roleCookie = request.cookies.get('role')?.value;
const isOwner = roleCookie === 'agent';

if (pathname.startsWith('/owner') && !isOwner) {
 // Block non-owners from accessing owner panel
 const loginUrl = new URL('/owner/login', request.url);
 loginUrl.searchParams.set('redirect', pathname);
 return NextResponse.redirect(loginUrl);
}
```

### Customer Private Route Guard

```typescript
const PRIVATE_CUSTOMER_PATHS = ['/profile', '/my', '/notifications', '/search-alerts'];

const isPrivateCustomerPath = PRIVATE_CUSTOMER_PATHS.some(p => pathname.startsWith(p));

if (isPrivateCustomerPath && !userId) {
 // Unauthenticated users redirected to login
 const loginUrl = new URL('/login', request.url);
 loginUrl.searchParams.set('redirect', pathname);
 return NextResponse.redirect(loginUrl);
}

if (isPrivateCustomerPath && isOwner) {
 // Owners should use their own panel
 return NextResponse.redirect(new URL('/owner/dashboard', request.url));
}
```

---

## Matcher Configuration

The middleware runs on all paths **except** static assets and Next.js internals:

```typescript
// src/middleware.ts
export const config = {
 matcher: [
 '/((?!_next/static|_next/image|favicon.ico|manifest.json|sw.js|icons/).*)',
 ],
};
```

---

## Clerk Integration

The Clerk middleware is used to resolve `userId` (authenticated user identifier) from the request cookies/headers. This is the Clerk session token — **separate** from the Laravel Sanctum token.

```typescript
// src/proxy.ts
import { clerkMiddleware, getAuth } from '@clerk/nextjs/server';

export default clerkMiddleware((auth, request) => {
 const { userId } = auth();
 // ... route protection logic
});
```

**Important**: The `userId` from Clerk is only used by the middleware for redirect logic. The actual API authentication uses the **Laravel Sanctum token** stored in memory by `AuthProvider`.

---

## x-nonce Header Usage

The `MuiEmotionRegistry` component (`src/components/MuiEmotionRegistry.tsx`) reads the nonce to configure Emotion's style insertion, ensuring MUI's CSS-in-JS is CSP-compliant:

```typescript
// src/components/MuiEmotionRegistry.tsx
import createCache from '@emotion/cache';

export function MuiEmotionRegistry({ nonce, children }) {
 const cache = createCache({
 key: 'mui',
 nonce, // Nonce from x-nonce header
 prepend: true,
 });
 return (
 <CacheProvider value={cache}>
 {children}
 </CacheProvider>
 );
}
```

---

## Related Documentation

- [AuthProvider](./auth-provider.md) — Role cookie management
- [Tenant Authentication](../roles/tenant/authentication.md)
- [Owner Authentication](../roles/owner/authentication.md)
