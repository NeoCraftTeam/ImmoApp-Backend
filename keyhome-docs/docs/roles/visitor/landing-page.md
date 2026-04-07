---
sidebar_position: 2
title: "Landing Page"
---

# Landing Page

## Feature Description

The **Landing Page** (`/`) is the primary marketing entry point for unauthenticated visitors. It showcases the platform's value proposition with animated statistics, feature highlights, testimonials, and prominent call-to-action buttons for both property seekers and owners.

Authenticated users are **automatically redirected** to `/home` by the middleware before the landing page renders.

---

## User Journey

1. Visitor arrives at `/`
2. Middleware checks authentication:
 - If authenticated → redirect to `/home`
 - If unauthenticated → render `LandingPage` component
3. Visitor sees the hero section with a search bar and two CTAs
4. Animated stat counters load (using `useCountUp` with intersection observer)
5. Platform statistics are fetched from the API and displayed
6. Visitor can:
 - Enter a search query → navigated to `/search?q=...`
 - Click "Voir les annonces" → navigated to `/home`
 - Click "Publier une annonce" → navigated to `/owner/register`
 - Browse the feature section, testimonials, blog preview, agency section
7. Footer with links to legal pages, blog, social media

---

## Frontend Files Involved

| File | Role |
|---|---|
| `src/app/page.tsx` | Root route — renders `LandingPage` |
| `src/app/layout.tsx` | Root layout — ClerkProvider, PWA meta, analytics |
| `src/components/landing/LandingPage.tsx` | Main landing page composition component |
| `src/components/landing/HeroSection.tsx` | Hero with search bar and CTAs |
| `src/components/landing/StatsSection.tsx` | Platform statistics (animated counters) |
| `src/components/landing/FeaturesSection.tsx` | Feature highlights grid |
| `src/components/landing/TestimonialsSection.tsx` | User testimonials carousel |
| `src/components/landing/BlogPreviewSection.tsx` | Recent blog articles preview |
| `src/components/landing/AgenciesSection.tsx` | Featured agencies |
| `src/components/landing/CtaSection.tsx` | Final call-to-action for owners |
| `src/hooks/useLandingStats.ts` | Fetches platform statistics |
| `src/hooks/useCountUp.ts` | Animated number counter |
| `src/hooks/useLandingTestimonials.ts` | Fetches testimonials data |
| `src/lib/constants.ts` | `APP_NAME`, `CURRENCY`, format utilities |
| `src/proxy.ts` | Middleware redirect for authenticated users |

---

## Key Code Snippets

### Statistics Fetch (useLandingStats)

```typescript
// src/hooks/useLandingStats.ts
export function useLandingStats() {
 const { data, isLoading } = useQuery({
 queryKey: ['landing-stats'],
 queryFn: () => adsService.getStats(),
 staleTime: 1000 * 60 * 15, // 15-minute cache
 });

 return {
 stats: data,
 isLoading,
 };
}
```

### Animated Counter (useCountUp)

```typescript
// src/hooks/useCountUp.ts
export function useCountUp({ end, duration = 2000, decimals = 0 }: CountUpOptions) {
 const [value, setValue] = useState(0);
 const ref = useRef<HTMLElement>(null);

 useEffect(() => {
 const observer = new IntersectionObserver(([entry]) => {
 if (entry.isIntersecting) {
 // Ease-out cubic easing animation
 // Respects prefers-reduced-motion
 animateCount(0, end, duration, decimals, setValue);
 observer.disconnect();
 }
 });
 if (ref.current) observer.observe(ref.current);
 return () => observer.disconnect();
 }, [end, duration, decimals]);

 return { value, ref };
}
```

### Middleware Redirect

```typescript
// src/proxy.ts
if (auth.userId && pathname === '/') {
 return NextResponse.redirect(new URL('/home', request.url));
}
```

---

## Backend API Endpoints

** Inferred from frontend code**

| Method | Endpoint | Description | Response |
|---|---|---|---|
| `GET` | `/api/v1/stats/landing` | Platform-wide statistics | `{ ads_count: number, cities_count: number, users_count: number }` |

### Response Shape

```json
{
 "ads_count": 12450,
 "cities_count": 24,
 "users_count": 8920
}
```

---

## SEO & Performance

- **Metadata**: Set in `src/app/layout.tsx` with `generateMetadata()` — title, description, og:image, manifest
- **PWA manifest**: Linked via `<link rel="manifest" href="/manifest.json">`
- **JSON-LD**: `Organization` structured data via `src/components/seo/JsonLd.tsx`
- **Web Vitals**: Tracked via `src/components/seo/WebVitals.tsx`
- **Service Worker**: Registered for offline support and PWA installation
- **Analytics**: Vercel Analytics + Speed Insights injected at root layout level
